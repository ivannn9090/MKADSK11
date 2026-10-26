<?php

namespace App\Controllers\Api\Schedule;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Schedule\GoogleCalendarService;
use App\Models\Schedule\GoogleCalendarSettingsModel;
use App\Models\Schedule\ScheduleModel;
use App\Models\Schedule\ScheduleSyncLogModel;

/**
 * GoogleCalendarController — Pengaturan dan OAuth2 Google Calendar.
 *
 * OAuth2 Callback Flow:
 *   1. Admin klik "Connect" → GET /api/v1/schedule/google/auth-url
 *   2. Frontend redirect browser ke URL dari langkah 1
 *   3. Google redirect ke redirect_url yang dikonfigurasi (route publik)
 *   4. WebController::googleOauthCallback() memanggil
 *      POST /api/v1/schedule/google/callback dengan code & state
 */
class GoogleCalendarController extends BaseApiController
{
    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/schedule/google/settings
    // ──────────────────────────────────────────────────────────────
    public function settings()
    {
        $this->requireRole(['admin', 'tu']);

        $cfg = (new GoogleCalendarSettingsModel())->getDefault();
        if (!$cfg) return $this->fail('Konfigurasi tidak ditemukan.', 404);

        // Jangan kirim secret & token ke frontend
        $safe = $cfg;
        $safe['client_secret'] = $cfg['client_secret'] ? '••••••••' : null;
        $safe['access_token']  = $cfg['is_connected'] ? '[tersimpan]' : null;
        $safe['refresh_token'] = $cfg['refresh_token'] ? '[tersimpan]' : null;

        return $this->success($safe);
    }

    // ──────────────────────────────────────────────────────────────
    // PUT /api/v1/schedule/google/settings
    // Simpan konfigurasi (client_id, client_secret, dst)
    // ──────────────────────────────────────────────────────────────
    public function updateSettings()
    {
        $this->requireRole(['admin']);

        $data  = $this->request->getJSON(true) ?? [];
        $model = new GoogleCalendarSettingsModel();
        $cfg   = $model->getDefault();

        $update = array_filter([
            'nama'         => $data['nama']         ?? null,
            'client_id'    => $data['client_id']    ?? null,
            'project_id'   => $data['project_id']   ?? null,
            'calendar_id'  => $data['calendar_id']  ?? null,
            'redirect_url' => $data['redirect_url'] ?? null,
            'reminder_h7'  => isset($data['reminder_h7'])  ? (int)$data['reminder_h7']  : null,
            'reminder_h3'  => isset($data['reminder_h3'])  ? (int)$data['reminder_h3']  : null,
            'reminder_h1'  => isset($data['reminder_h1'])  ? (int)$data['reminder_h1']  : null,
            'reminder_aktif' => isset($data['reminder_aktif']) ? (int)$data['reminder_aktif'] : null,
            'updated_by'   => $this->userId(),
        ], fn($v) => $v !== null);

        // client_secret hanya diupdate jika tidak kosong dan bukan placeholder
        if (!empty($data['client_secret']) && $data['client_secret'] !== '••••••••') {
            $update['client_secret'] = $data['client_secret'];
        }

        $id = $cfg ? $cfg['id'] : null;
        if ($id) {
            $model->update($id, $update);
        } else {
            $update['created_by'] = $this->userId();
            $model->insert($update);
        }

        $this->auditLog('Update Google Calendar Settings', 'google_calendar_settings');
        return $this->success(null, 'Konfigurasi Google Calendar berhasil disimpan.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/schedule/google/auth-url
    // Hasilkan URL redirect ke Google OAuth
    // ──────────────────────────────────────────────────────────────
    public function authUrl()
    {
        $this->requireRole(['admin']);

        try {
            $url = (new GoogleCalendarService())->generateAuthUrl();
            return $this->success(['auth_url' => $url]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/schedule/google/callback
    // Dipanggil setelah browser balik dari Google dengan code & state
    // Body: { "code": "...", "state": "..." }
    // ──────────────────────────────────────────────────────────────
    public function callback()
    {
        $data  = $this->request->getJSON(true) ?? [];
        $code  = $data['code']  ?? '';
        $state = $data['state'] ?? '';

        if (!$code || !$state) {
            return $this->fail('Parameter code atau state tidak lengkap.', 422);
        }

        try {
            $result = (new GoogleCalendarService())->handleCallback($code, $state);
            return $this->success($result, 'Google Calendar berhasil dihubungkan sebagai ' . $result['email']);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/schedule/google/disconnect
    // ──────────────────────────────────────────────────────────────
    public function disconnect()
    {
        $this->requireRole(['admin']);

        $model = new GoogleCalendarSettingsModel();
        $cfg   = $model->getDefault();
        if (!$cfg) return $this->fail('Konfigurasi tidak ditemukan.', 404);

        $model->disconnect((int)$cfg['id']);

        (new ScheduleSyncLogModel())->log(null, 'disconnect', 'success',
            'Google Calendar diputus oleh admin', $this->userId());

        $this->auditLog('Disconnect Google Calendar', 'google_calendar_settings');
        return $this->success(null, 'Google Calendar berhasil diputus.');
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/schedule/google/test
    // ──────────────────────────────────────────────────────────────
    public function test()
    {
        $this->requireRole(['admin', 'tu']);

        try {
            $info = (new GoogleCalendarService())->testConnection();
            return $this->success($info, 'Koneksi berhasil! Calendar: ' . ($info['summary'] ?? $info['id']));
        } catch (\Throwable $e) {
            return $this->fail('Test koneksi gagal: ' . $e->getMessage(), 422);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/schedule/google/resync-all
    // Re-sync semua agenda yang belum/gagal sync
    // ──────────────────────────────────────────────────────────────
    public function resyncAll()
    {
        $this->requireRole(['admin']);

        $model     = new ScheduleModel();
        $schedules = $model->where('sync_google', 1)
                           ->whereIn('google_sync_status', ['pending', 'failed', 'not_synced'])
                           ->where('deleted_at', null)
                           ->findAll();

        $gcal    = new GoogleCalendarService();
        $success = 0;
        $failed  = 0;

        foreach ($schedules as $s) {
            try {
                $detail  = $model->getDetail((int)$s['id']);
                if (empty($s['google_event_id'])) {
                    $eventId = $gcal->createEvent($detail);
                    $model->update($s['id'], [
                        'google_event_id'    => $eventId,
                        'google_sync_status' => 'synced',
                        'google_synced_at'   => date('Y-m-d H:i:s'),
                        'google_sync_error'  => null,
                    ]);
                } else {
                    $gcal->updateEvent($detail);
                    $model->update($s['id'], [
                        'google_sync_status' => 'synced',
                        'google_synced_at'   => date('Y-m-d H:i:s'),
                        'google_sync_error'  => null,
                    ]);
                }
                $success++;
                log_message('info', "[Schedule] ResyncAll berhasil untuk agenda #{$s['id']}");
            } catch (\Throwable $e) {
                $sanitized = mb_substr(preg_replace('/Bearer\s+\S+/i', 'Bearer [REDACTED]', $e->getMessage()), 0, 1000);
                $model->update($s['id'], [
                    'google_sync_status' => 'failed',
                    'google_sync_error'  => $sanitized,
                ]);
                $failed++;
                log_message('warning', "[Schedule] Re-sync #{$s['id']} gagal: " . $e->getMessage());
            }
        }

        return $this->success(
            ['success' => $success, 'failed' => $failed, 'total' => count($schedules)],
            "Re-sync selesai: {$success} berhasil, {$failed} gagal."
        );
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/schedule/google/sync-logs
    // ──────────────────────────────────────────────────────────────
    public function syncLogs()
    {
        $this->requireRole(['admin', 'tu']);
        return $this->success(['items' => (new ScheduleSyncLogModel())->getRecent(100)]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/schedule/google/calendars
    // Ambil daftar semua kalender milik akun Google yang terhubung
    // ──────────────────────────────────────────────────────────────
    public function listCalendars()
    {
        $this->requireRole(['admin', 'tu']);

        try {
            $list = (new GoogleCalendarService())->listCalendars();
            return $this->success(['items' => $list]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/schedule/google/pull
    // Import event dari Google Calendar ke database MKA.
    // Event yang google_event_id-nya sudah ada akan dilewati (skip).
    // Query params:
    //   dari=YYYY-MM-DD (default: awal bulan ini)
    //   sampai=YYYY-MM-DD (default: 6 bulan ke depan)
    //   calendar_ids[]=id1&calendar_ids[]=id2 (kosong = SEMUA kalender)
    // ──────────────────────────────────────────────────────────────
    public function pullFromGoogle()
    {
        $this->requireRole(['admin', 'tu']);

        $dari   = $this->request->getGet('dari')   ?? date('Y-m-01');
        $sampai = $this->request->getGet('sampai') ?? date('Y-m-d', strtotime('+6 months'));

        // Validasi format tanggal
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   $dari   = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) $sampai = date('Y-m-d', strtotime('+6 months'));

        // Filter kalender — bisa kosong (semua) atau array ID spesifik
        $calendarIds = $this->request->getGet('calendar_ids') ?? [];
        if (!is_array($calendarIds)) $calendarIds = [$calendarIds];
        $calendarIds = array_filter(array_map('trim', $calendarIds));

        // Timezone WIB
        $timeMin = $dari   . 'T00:00:00+07:00';
        $timeMax = $sampai . 'T23:59:59+07:00';

        try {
            $gcal   = new GoogleCalendarService();
            $events = empty($calendarIds)
                ? $gcal->listAllEvents($timeMin, $timeMax)         // semua kalender
                : $gcal->listAllEvents($timeMin, $timeMax, $calendarIds); // filter
        } catch (\Throwable $e) {
            return $this->fail('Gagal mengambil data dari Google Calendar: ' . $e->getMessage(), 422);
        }

        if (empty($events)) {
            return $this->success(['imported' => 0, 'skipped' => 0, 'total' => 0],
                'Tidak ada event dari Google Calendar pada rentang tanggal tersebut.');
        }

        $schModel = new ScheduleModel();
        $db       = \Config\Database::connect();

        // Ambil semua google_event_id yang sudah ada di DB (untuk cek duplikat)
        $existingIds = $db->table('schedules')
                          ->select('google_event_id')
                          ->where('google_event_id IS NOT NULL')
                          ->where('deleted_at', null)
                          ->get()->getResultArray();
        $existingIds = array_column($existingIds, 'google_event_id');
        $existingSet = array_flip($existingIds);

        // Ambil category_id default (kode 'lainnya' atau id=1)
        $defCat = $db->table('schedule_categories')->where('kode', 'lainnya')->get()->getRowArray();
        $defCatId = $defCat ? (int)$defCat['id'] : 1;

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($events as $ev) {
            $googleEventId = $ev['id'] ?? null;
            if (!$googleEventId) { $skipped++; continue; }

            // Skip jika sudah ada
            if (isset($existingSet[$googleEventId])) { $skipped++; continue; }

            // Skip event yang dibatalkan
            if (($ev['status'] ?? '') === 'cancelled') { $skipped++; continue; }

            try {
                // Tentukan tanggal — all-day atau datetime
                $allDay     = isset($ev['start']['date']);
                $tglMulai   = $allDay ? $ev['start']['date']
                                      : substr($ev['start']['dateTime'] ?? date('Y-m-d'), 0, 10);
                $tglSelesai = $allDay ? $ev['end']['date']   ?? $tglMulai
                                      : substr($ev['end']['dateTime']   ?? $tglMulai, 0, 10);

                // All-day end di Google adalah EXCLUSIVE, kurangi 1 hari
                if ($allDay && $tglSelesai > $tglMulai) {
                    $tglSelesai = date('Y-m-d', strtotime($tglSelesai . ' -1 day'));
                }

                $jamMulai   = null;
                $jamSelesai = null;
                if (!$allDay) {
                    $jamMulai   = substr($ev['start']['dateTime'] ?? '', 11, 8) ?: null;
                    $jamSelesai = substr($ev['end']['dateTime']   ?? '', 11, 8) ?: null;
                }

                $judul = $ev['summary'] ?? '(Tanpa Judul)';
                $kode  = $schModel->generateKode();

                $schModel->insert([
                    'kode'               => $kode,
                    'judul'              => $judul,
                    'deskripsi'          => $ev['description']  ?? null,
                    'lokasi'             => $ev['location']     ?? null,
                    'category_id'        => $defCatId,
                    'tgl_mulai'          => $tglMulai,
                    'jam_mulai'          => $jamMulai,
                    'tgl_selesai'        => $tglSelesai,
                    'jam_selesai'        => $jamSelesai,
                    'all_day'            => $allDay ? 1 : 0,
                    'sync_google'        => 1,
                    'google_event_id'    => $googleEventId,
                    'google_calendar_id' => $ev['_calendar_id'] ?? null,
                    'google_sync_status' => 'synced',
                    'google_synced_at'   => date('Y-m-d H:i:s'),
                    'ref_type'           => 'google_import',
                    'created_by'         => $this->userId(),
                ]);

                // Tandai event ini sudah ada untuk sisa loop
                $existingSet[$googleEventId] = true;
                $imported++;

            } catch (\Throwable $e) {
                $errors[] = ($ev['summary'] ?? $googleEventId) . ': ' . $e->getMessage();
                $skipped++;
            }
        }

        (new ScheduleSyncLogModel())->log(
            null, 'pull', $imported > 0 ? 'success' : 'skipped',
            "Pull selesai: {$imported} diimport, {$skipped} dilewati dari " . count($events) . " event.",
            $this->userId()
        );

        $this->auditLog("Pull Google Calendar: {$imported} imported, {$skipped} skipped", 'schedules');

        $msg = "Import selesai: {$imported} event berhasil diimport, {$skipped} dilewati.";
        return $this->success([
            'imported' => $imported,
            'skipped'  => $skipped,
            'total'    => count($events),
            'errors'   => $errors,
        ], $msg);
    }
}
