<?php

namespace App\Libraries\Schedule;

use App\Models\Schedule\GoogleCalendarSettingsModel;
use App\Models\Schedule\ScheduleSyncLogModel;

/**
 * GoogleCalendarService — Wrapper Google Calendar API v3
 *
 * Implementasi manual menggunakan cURL (tanpa Google SDK) agar tidak perlu
 * composer package Google API Client yang berat.
 *
 * OAuth2 Flow:
 *   1. generateAuthUrl()  → redirect user ke Google
 *   2. handleCallback()   → tukar code → token
 *   3. Setiap request api → getAccessToken() (auto-refresh jika expired)
 *
 * Security:
 *   - Token/credential tidak pernah masuk ke log
 *   - Error message disanitasi sebelum disimpan ke DB
 *   - State OAuth divalidasi setiap callback (CSRF protection)
 */
class GoogleCalendarService
{
    private const OAUTH_AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL    = 'https://www.googleapis.com/oauth2/v3/userinfo';
    private const CALENDAR_API    = 'https://www.googleapis.com/calendar/v3';
    private const SCOPES          = [
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/userinfo.email',
    ];

    /** Timeout cURL dalam detik untuk request API (bukan token exchange) */
    private const CURL_TIMEOUT    = 20;
    /** Timeout cURL untuk token exchange (operasi lebih cepat) */
    private const CURL_TOKEN_TIMEOUT = 15;

    private array  $settings;
    private int    $settingsId;
    private string $accessToken = '';

    // ──────────────────────────────────────────────────────────────
    public function __construct(?int $settingsId = null)
    {
        $model = new GoogleCalendarSettingsModel();
        $cfg   = $settingsId
            ? $model->find($settingsId)
            : $model->getDefault();

        if (!$cfg) {
            throw new \RuntimeException('Konfigurasi Google Calendar belum tersedia.');
        }

        $this->settings   = $cfg;
        $this->settingsId = (int)$cfg['id'];
    }

    // ──────────────────────────────────────────────────────────────
    // OAuth2: hasilkan URL otorisasi untuk redirect browser user
    // ──────────────────────────────────────────────────────────────
    public function generateAuthUrl(): string
    {
        if (empty($this->settings['client_id'])) {
            throw new \RuntimeException('Client ID belum dikonfigurasi.');
        }

        $state = bin2hex(random_bytes(16));

        // Simpan state ke DB untuk verifikasi saat callback
        (new GoogleCalendarSettingsModel())->update($this->settingsId, ['oauth_state' => $state]);

        return self::OAUTH_AUTH_URL . '?' . http_build_query([
            'client_id'             => $this->settings['client_id'],
            'redirect_uri'          => $this->settings['redirect_url'],
            'response_type'         => 'code',
            'scope'                 => implode(' ', self::SCOPES),
            'access_type'           => 'offline',
            'prompt'                => 'consent',
            'state'                 => $state,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // OAuth2: tukar authorization code menjadi token
    // ──────────────────────────────────────────────────────────────
    public function handleCallback(string $code, string $state): array
    {
        // Verifikasi state (CSRF protection)
        if ($state !== ($this->settings['oauth_state'] ?? '')) {
            throw new \RuntimeException('State OAuth tidak valid. Kemungkinan serangan CSRF.');
        }

        $tokenData = $this->_post(self::OAUTH_TOKEN_URL, [
            'code'          => $code,
            'client_id'     => $this->settings['client_id'],
            'client_secret' => $this->settings['client_secret'],
            'redirect_uri'  => $this->settings['redirect_url'],
            'grant_type'    => 'authorization_code',
        ], false); // false = tidak pakai Bearer

        if (isset($tokenData['error'])) {
            throw new \RuntimeException('Token exchange gagal: ' . ($tokenData['error_description'] ?? $tokenData['error']));
        }

        // Ambil email user
        $email = $this->_getUserEmail($tokenData['access_token']);

        $model = new GoogleCalendarSettingsModel();

        // Simpan token ke DB
        $model->saveTokens($this->settingsId, $tokenData, $email);

        // Hapus oauth_state setelah berhasil — mencegah state stale dipakai ulang
        $model->update($this->settingsId, ['oauth_state' => null]);

        log_message('info', "[GoogleCalendar] Berhasil terhubung sebagai {$email}");
        (new ScheduleSyncLogModel())->log(null, 'connect', 'success',
            "Berhasil terhubung sebagai {$email}");

        return ['email' => $email];
    }

    // ──────────────────────────────────────────────────────────────
    // Buat event di Google Calendar
    // ──────────────────────────────────────────────────────────────
    public function createEvent(array $schedule): string
    {
        $this->_ensureToken();

        $calendarId = $this->settings['calendar_id'] ?: 'primary';
        $body       = $this->_buildEventBody($schedule);

        $res = $this->_post(
            self::CALENDAR_API . "/calendars/{$calendarId}/events",
            $body
        );

        if (isset($res['error'])) {
            $errMsg = $this->_extractApiError($res['error']);
            $this->_handleScopeError($res['error']);
            log_message('warning', '[GoogleCalendar] createEvent gagal untuk schedule #' . ($schedule['id'] ?? '?') . ': ' . $errMsg);
            (new ScheduleSyncLogModel())->log(
                isset($schedule['id']) ? (int)$schedule['id'] : null,
                'create', 'failed', $errMsg
            );
            throw new \RuntimeException('Gagal membuat event Google Calendar: ' . $errMsg);
        }

        log_message('info', '[GoogleCalendar] Event dibuat: ' . $res['id'] . ' untuk schedule #' . ($schedule['id'] ?? '?'));
        (new ScheduleSyncLogModel())->log(
            (int)$schedule['id'], 'create', 'success',
            "Event dibuat: {$res['id']}",
            null, $res['id'],
            json_encode($body), null // response tidak perlu disimpan untuk keberhasilan
        );

        return $res['id'];
    }

    // ──────────────────────────────────────────────────────────────
    // Update event Google Calendar
    // ──────────────────────────────────────────────────────────────
    public function updateEvent(array $schedule): void
    {
        if (empty($schedule['google_event_id'])) {
            // Belum punya event ID → buat baru
            $eventId = $this->createEvent($schedule);
            \Config\Database::connect()->table('schedules')->where('id', $schedule['id'])->update([
                'google_event_id'    => $eventId,
                'google_sync_status' => 'synced',
                'google_synced_at'   => date('Y-m-d H:i:s'),
                'google_sync_error'  => null,
            ]);
            return;
        }

        $this->_ensureToken();

        $calendarId = $this->settings['calendar_id'] ?: 'primary';
        $body       = $this->_buildEventBody($schedule);

        $res = $this->_put(
            self::CALENDAR_API . "/calendars/{$calendarId}/events/{$schedule['google_event_id']}",
            $body
        );

        if (isset($res['error'])) {
            $errMsg = $this->_extractApiError($res['error']);
            // Jika event tidak ditemukan (404), hapus ID lama → buat ulang
            if (($res['error']['code'] ?? 0) == 404) {
                log_message('warning', '[GoogleCalendar] Event ' . $schedule['google_event_id'] . ' tidak ditemukan — membuat baru.');
                \Config\Database::connect()->table('schedules')->where('id', $schedule['id'])->update(['google_event_id' => null]);
                $schedule['google_event_id'] = null;
                $this->createEvent($schedule);
                return;
            }
            log_message('warning', '[GoogleCalendar] updateEvent gagal untuk schedule #' . ($schedule['id'] ?? '?') . ': ' . $errMsg);
            (new ScheduleSyncLogModel())->log(
                isset($schedule['id']) ? (int)$schedule['id'] : null,
                'update', 'failed', $errMsg
            );
            throw new \RuntimeException('Gagal memperbarui event Google Calendar: ' . $errMsg);
        }

        log_message('info', '[GoogleCalendar] Event diperbarui: ' . $schedule['google_event_id'] . ' untuk schedule #' . ($schedule['id'] ?? '?'));
        (new ScheduleSyncLogModel())->log(
            (int)$schedule['id'], 'update', 'success',
            "Event diperbarui: {$schedule['google_event_id']}",
            null, $schedule['google_event_id'],
            json_encode($body), null
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Hapus event Google Calendar
    // ──────────────────────────────────────────────────────────────
    public function deleteEvent(string $googleEventId): void
    {
        $this->_ensureToken();

        $calendarId = $this->settings['calendar_id'] ?: 'primary';
        $url        = self::CALENDAR_API . "/calendars/{$calendarId}/events/{$googleEventId}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::CURL_TIMEOUT,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->accessToken],
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            log_message('warning', "[GoogleCalendar] deleteEvent cURL error: {$curlErr}");
            throw new \RuntimeException('Koneksi ke Google Calendar gagal: ' . $curlErr);
        }

        // 204 = sukses, 200 = sukses (jarang), 404 = sudah tidak ada → dianggap OK
        if ($httpCode !== 204 && $httpCode !== 200 && $httpCode !== 404) {
            // Coba parse body untuk pesan error yang lebih informatif
            $parsed = json_decode($body, true);
            $errMsg = $parsed['error']['message'] ?? "HTTP {$httpCode}";
            log_message('warning', "[GoogleCalendar] deleteEvent gagal untuk event {$googleEventId}: HTTP {$httpCode} — {$errMsg}");
            throw new \RuntimeException('Gagal menghapus event Google Calendar: ' . $errMsg);
        }

        log_message('info', "[GoogleCalendar] Event dihapus: {$googleEventId}");
        (new ScheduleSyncLogModel())->log(
            null, 'delete', 'success',
            "Event dihapus: {$googleEventId}", null, $googleEventId
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Test koneksi — ambil info kalender
    // ──────────────────────────────────────────────────────────────
    public function testConnection(): array
    {
        $this->_ensureToken();
        $calendarId = $this->settings['calendar_id'] ?: 'primary';
        $res = $this->_get(self::CALENDAR_API . "/calendars/{$calendarId}");
        if (isset($res['error'])) {
            $code    = $res['error']['code']    ?? 0;
            $message = $res['error']['message'] ?? 'Unknown';

            // Scope tidak mencukupi → token harus dihapus, user perlu reconnect ulang
            if ($code == 403 && str_contains(strtolower($message), 'insufficient authentication scopes')) {
                (new GoogleCalendarSettingsModel())->disconnect($this->settingsId);
                log_message('warning', '[GoogleCalendar] Scope tidak mencukupi — koneksi diputus otomatis. Perlu reconnect.');
                throw new \RuntimeException(
                    'Izin Google Calendar tidak mencukupi. ' .
                    'Token lama telah dihapus. Silakan hubungkan ulang melalui Pengaturan → Google Calendar → Hubungkan.'
                );
            }

            throw new \RuntimeException('Test koneksi gagal: ' . $message);
        }
        return ['id' => $res['id'], 'summary' => $res['summary'] ?? '', 'timeZone' => $res['timeZone'] ?? ''];
    }

    // ──────────────────────────────────────────────────────────────
    // Ambil daftar semua kalender milik akun yang terhubung
    // Mengembalikan array [ ['id'=>..., 'summary'=>..., 'primary'=>true/false], ... ]
    // ──────────────────────────────────────────────────────────────
    public function listCalendars(): array
    {
        $this->_ensureToken();

        $res = $this->_get(self::CALENDAR_API . '/users/me/calendarList');

        if (isset($res['error'])) {
            throw new \RuntimeException('Gagal mengambil daftar kalender: ' . ($res['error']['message'] ?? json_encode($res['error'])));
        }

        $list = [];
        foreach ($res['items'] ?? [] as $item) {
            $list[] = [
                'id'          => $item['id'],
                'summary'     => $item['summary'] ?? $item['id'],
                'description' => $item['description'] ?? null,
                'primary'     => (bool)($item['primary'] ?? false),
                'warna'       => $item['backgroundColor'] ?? null,
                'aksesRole'   => $item['accessRole'] ?? 'reader',
            ];
        }

        return $list;
    }

    // ──────────────────────────────────────────────────────────────
    // Pull events dari satu kalender — untuk import ke MKA
    // $calendarId : ID kalender ('primary' / email / group ID)
    // $timeMin / $timeMax : format RFC3339 "2026-01-01T00:00:00+07:00"
    // ──────────────────────────────────────────────────────────────
    public function listEvents(string $timeMin, string $timeMax, int $maxResults = 500, string $calendarId = ''): array
    {
        $this->_ensureToken();

        if ($calendarId === '') {
            $calendarId = $this->settings['calendar_id'] ?: 'primary';
        }

        $encodedId = urlencode($calendarId);
        $params    = http_build_query([
            'timeMin'      => $timeMin,
            'timeMax'      => $timeMax,
            'maxResults'   => $maxResults,
            'singleEvents' => 'true',
            'orderBy'      => 'startTime',
        ]);

        $res = $this->_get(self::CALENDAR_API . "/calendars/{$encodedId}/events?{$params}");

        if (isset($res['error'])) {
            // Kalender tidak bisa diakses — kembalikan kosong, jangan throw (agar multi-cal tetap jalan)
            log_message('warning', "[GoogleCalendar] listEvents({$calendarId}) error: " . json_encode($res['error']));
            return [];
        }

        // Tambahkan info asal kalender ke setiap event
        $items = $res['items'] ?? [];
        foreach ($items as &$ev) {
            $ev['_calendar_id'] = $calendarId;
        }

        return $items;
    }

    // ──────────────────────────────────────────────────────────────
    // Pull events dari SEMUA kalender sekaligus
    // Jika $calendarIds kosong → pakai semua kalender yang dimiliki akun
    // ──────────────────────────────────────────────────────────────
    public function listAllEvents(string $timeMin, string $timeMax, array $calendarIds = []): array
    {
        $this->_ensureToken();

        // Jika tidak ada filter kalender → ambil semua
        if (empty($calendarIds)) {
            $calendarList = $this->listCalendars();
            $calendarIds  = array_column($calendarList, 'id');
        }

        $allEvents  = [];
        $seenIds    = []; // dedup berdasarkan google event id

        foreach ($calendarIds as $calId) {
            $events = $this->listEvents($timeMin, $timeMax, 500, $calId);
            foreach ($events as $ev) {
                $eid = $ev['id'] ?? null;
                // Skip duplikat (event yang muncul di beberapa kalender karena shared/invite)
                if ($eid && isset($seenIds[$eid])) continue;
                if ($eid) $seenIds[$eid] = true;
                $allEvents[] = $ev;
            }
        }

        return $allEvents;
    }

    // ──────────────────────────────────────────────────────────────
    // Apakah sudah terhubung?
    // ──────────────────────────────────────────────────────────────
    public function isConnected(): bool
    {
        return (bool)($this->settings['is_connected'] ?? false)
            && !empty($this->settings['access_token']);
    }

    // ──────────────────────────────────────────────────────────────
    // Build event body untuk Google Calendar API
    // ──────────────────────────────────────────────────────────────
    private function _buildEventBody(array $schedule): array
    {
        $allDay = (bool)($schedule['all_day'] ?? false);

        if ($allDay) {
            $start = ['date' => $schedule['tgl_mulai']];
            // Google Calendar: end date adalah EXCLUSIVE — tambah 1 hari
            $endDate = date('Y-m-d', strtotime($schedule['tgl_selesai'] . ' +1 day'));
            $end   = ['date' => $endDate];
        } else {
            $tz    = 'Asia/Jakarta';
            $jamMulai   = $schedule['jam_mulai']   ?? '08:00:00';
            $jamSelesai = $schedule['jam_selesai']  ?? '09:00:00';
            $start = ['dateTime' => $schedule['tgl_mulai']  . 'T' . $jamMulai,   'timeZone' => $tz];
            $end   = ['dateTime' => $schedule['tgl_selesai'] . 'T' . $jamSelesai, 'timeZone' => $tz];
        }

        $body = [
            'summary'     => $schedule['judul'],
            'description' => $schedule['deskripsi'] ?? '',
            'location'    => $schedule['lokasi'] ?? '',
            'start'       => $start,
            'end'         => $end,
            'colorId'     => $this->_warnaToBcColorId($schedule['warna'] ?? $schedule['cat_warna'] ?? null),
        ];

        // Reminders
        $reminders = [];
        if (!empty($schedule['reminders'])) {
            foreach ($schedule['reminders'] as $r) {
                $reminders[] = ['method' => $r['metode'] === 'email' ? 'email' : 'popup', 'minutes' => (int)$r['menit']];
            }
        } else {
            // Default: H-1 popup
            $reminders[] = ['method' => 'popup', 'minutes' => 1440];
        }
        $body['reminders'] = ['useDefault' => false, 'overrides' => $reminders];

        // Attendees dari peserta
        if (!empty($schedule['attendees'])) {
            $body['attendees'] = array_filter(array_map(fn($a) => $a['email'] ? ['email' => $a['email']] : null, $schedule['attendees']));
        }

        return $body;
    }

    // ──────────────────────────────────────────────────────────────
    // Pastikan access_token valid, refresh jika perlu
    // ──────────────────────────────────────────────────────────────
    private function _ensureToken(): void
    {
        if (!$this->isConnected()) {
            throw new \RuntimeException('Google Calendar belum terhubung. Hubungkan dulu di Pengaturan → Google Calendar.');
        }

        $tokenData   = json_decode($this->settings['access_token'] ?? '{}', true) ?? [];
        $tokenExpiry = $this->settings['token_expires'] ? strtotime($this->settings['token_expires']) : 0;

        // Token valid jika belum expired (buffer 60 detik)
        if (!empty($tokenData['access_token']) && $tokenExpiry > time() + 60) {
            $this->accessToken = $tokenData['access_token'];
            return;
        }

        // Refresh token
        $refreshToken = $this->settings['refresh_token'];
        if (empty($refreshToken)) {
            throw new \RuntimeException('Refresh token tidak tersedia. Silakan hubungkan ulang Google Calendar di Pengaturan.');
        }

        $newToken = $this->_post(self::OAUTH_TOKEN_URL, [
            'client_id'     => $this->settings['client_id'],
            'client_secret' => $this->settings['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ], false);

        if (isset($newToken['error'])) {
            // Token dicabut atau tidak valid — disconnect otomatis agar admin tahu perlu reconnect
            $errDesc = $newToken['error_description'] ?? $newToken['error'];
            if (in_array($newToken['error'], ['invalid_grant', 'token_revoked'])) {
                (new GoogleCalendarSettingsModel())->disconnect($this->settingsId);
                log_message('warning', '[GoogleCalendar] Refresh token tidak valid (dicabut/expired). Koneksi diputus otomatis.');
                throw new \RuntimeException('Otorisasi Google Calendar telah kedaluwarsa atau dicabut. Silakan hubungkan ulang di Pengaturan → Google Calendar.');
            }
            log_message('warning', '[GoogleCalendar] Refresh token gagal: ' . $errDesc);
            throw new \RuntimeException('Pembaruan token Google Calendar gagal: ' . $errDesc);
        }

        // Simpan token baru — refresh_token tidak selalu dikembalikan ulang
        $merged = array_merge($tokenData, $newToken);
        (new GoogleCalendarSettingsModel())->saveTokens(
            $this->settingsId,
            $merged,
            $this->settings['connected_email'] ?? ''
        );

        $this->accessToken = $newToken['access_token'];
        log_message('info', '[GoogleCalendar] Access token berhasil diperbarui.');

        // Reload settings
        $this->settings = (new GoogleCalendarSettingsModel())->find($this->settingsId);
    }

    // ──────────────────────────────────────────────────────────────
    // Ambil email dari token
    // ──────────────────────────────────────────────────────────────
    private function _getUserEmail(string $accessToken): string
    {
        $info = $this->_get(self::USERINFO_URL, $accessToken);
        return $info['email'] ?? 'unknown@google.com';
    }

    // ──────────────────────────────────────────────────────────────
    // Konversi hex warna ke Google Calendar colorId (1-11)
    // ──────────────────────────────────────────────────────────────
    private function _warnaToBcColorId(?string $hex): ?string
    {
        if (!$hex) return null;
        $map = [
            '#3b82f6' => '9',  // Biru → Blueberry
            '#10b981' => '2',  // Hijau → Sage
            '#f59e0b' => '5',  // Orange → Banana
            '#ef4444' => '11', // Merah → Tomato
            '#8b5cf6' => '3',  // Ungu → Grape
            '#9ca3af' => '8',  // Abu → Graphite
            '#6366f1' => '1',  // Indigo → Lavender
        ];
        return $map[strtolower($hex)] ?? null;
    }

    // ──────────────────────────────────────────────────────────────
    // Ekstrak pesan error dari respons Google API
    // ──────────────────────────────────────────────────────────────
    /**
     * Jika error adalah "insufficient authentication scopes" (403),
     * auto-disconnect dan throw RuntimeException yang informatif.
     * Dipanggil sebelum throw umum agar pesan lebih jelas.
     */
    private function _handleScopeError(mixed $error): void
    {
        if (!is_array($error)) return;
        $code    = $error['code']    ?? 0;
        $message = $error['message'] ?? '';
        if ($code == 403 && str_contains(strtolower($message), 'insufficient authentication scopes')) {
            (new GoogleCalendarSettingsModel())->disconnect($this->settingsId);
            log_message('warning', '[GoogleCalendar] Scope tidak mencukupi — koneksi diputus otomatis.');
            throw new \RuntimeException(
                'Izin Google Calendar tidak mencukupi (scope kurang). ' .
                'Token telah dihapus. Silakan hubungkan ulang melalui Pengaturan → Google Calendar → Hubungkan.'
            );
        }
    }

    private function _extractApiError(mixed $error): string
    {
        if (is_array($error)) {
            $msg  = $error['message'] ?? json_encode($error);
            $code = $error['code']    ?? '';
            return $code ? "[{$code}] {$msg}" : $msg;
        }
        return (string)$error;
    }

    // ──────────────────────────────────────────────────────────────
    // HTTP helpers — semua menggunakan timeout dan error handling
    // ──────────────────────────────────────────────────────────────
    private function _get(string $url, ?string $token = null): array
    {
        $t  = $token ?? $this->accessToken;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::CURL_TIMEOUT,
            CURLOPT_HTTPHEADER     => $t ? ['Authorization: Bearer ' . $t] : [],
        ]);
        $body    = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException('Koneksi ke Google API gagal: ' . $curlErr);
        }

        return json_decode($body, true) ?? [];
    }

    private function _post(string $url, array $data, bool $useBearer = true): array
    {
        $headers = ['Content-Type: application/json'];
        if ($useBearer && $this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }
        $ch = curl_init($url);
        $timeout = $useBearer ? self::CURL_TIMEOUT : self::CURL_TOKEN_TIMEOUT;
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body    = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException('Koneksi ke Google API gagal: ' . $curlErr);
        }

        return json_decode($body, true) ?? [];
    }

    private function _put(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::CURL_TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
            ],
        ]);
        $body    = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException('Koneksi ke Google API gagal: ' . $curlErr);
        }

        return json_decode($body, true) ?? [];
    }
}
