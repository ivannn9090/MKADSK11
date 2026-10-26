<?php

namespace App\Controllers\Api\Schedule;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Schedule\GoogleCalendarService;
use App\Models\Schedule\ScheduleAttendeeModel;
use App\Models\Schedule\ScheduleCategoryModel;
use App\Models\Schedule\ScheduleModel;
use App\Models\Schedule\ScheduleReminderModel;

/**
 * ScheduleController — CRUD agenda + sinkronisasi Google Calendar.
 */
class ScheduleController extends BaseApiController
{
    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/schedule/events
    // Query params: start, end, category_id, pic_user_id, q
    // FullCalendar mengirim start/end dalam format ISO 8601
    // ──────────────────────────────────────────────────────────────
    public function events()
    {
        $this->requirePermission('schedule.view');

        $filter = [
            'start'        => $this->request->getGet('start'),
            'end'          => $this->request->getGet('end'),
            'category_id'  => $this->request->getGet('category_id'),
            'pic_user_id'  => $this->request->getGet('pic_user_id'),
            'q'            => $this->request->getGet('q'),
        ];

        // Klien hanya lihat event yang dia jadi peserta atau PIC-nya
        if ($this->userRole() === 'klien') {
            $filter['attendee_user_id'] = $this->userId();
        }

        $events = (new ScheduleModel())->getEvents(array_filter($filter));
        return $this->success($events);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/schedule
    // Daftar agenda (tabel, bukan kalender) + filter + pagination
    // ──────────────────────────────────────────────────────────────
    public function index()
    {
        $this->requirePermission('schedule.view');

        $filter = [
            'start'       => $this->request->getGet('start'),
            'end'         => $this->request->getGet('end'),
            'category_id' => $this->request->getGet('category_id'),
            'q'           => $this->request->getGet('q'),
        ];

        if ($this->userRole() === 'klien') {
            $filter['attendee_user_id'] = $this->userId();
        }

        $items = (new ScheduleModel())->getEvents(array_filter($filter));
        return $this->success(['items' => $items, 'total' => count($items)]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/schedule/:id
    // ──────────────────────────────────────────────────────────────
    public function show(int $id)
    {
        $this->requirePermission('schedule.view');

        $schedule = (new ScheduleModel())->getDetail($id);
        if (!$schedule) return $this->fail('Agenda tidak ditemukan.', 404);

        return $this->success($schedule);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/schedule
    // ──────────────────────────────────────────────────────────────
    public function store()
    {
        $this->requirePermission('schedule.manage');

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'judul'       => 'required|max_length[500]',
            'category_id' => 'required|integer',
            'tgl_mulai'   => 'required|valid_date',
            'tgl_selesai' => 'required|valid_date',
        ];

        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $model  = new ScheduleModel();
        $kode   = $model->generateKode();

        $model->insert([
            'kode'        => $kode,
            'judul'       => $data['judul'],
            'deskripsi'   => $data['deskripsi'] ?? null,
            'category_id' => (int)$data['category_id'],
            'lokasi'      => $data['lokasi'] ?? null,
            'tgl_mulai'   => $data['tgl_mulai'],
            'jam_mulai'   => $data['jam_mulai'] ?? null,
            'tgl_selesai' => $data['tgl_selesai'],
            'jam_selesai' => $data['jam_selesai'] ?? null,
            'all_day'     => (int)($data['all_day'] ?? 0),
            'warna'       => $data['warna'] ?? null,
            'pic_user_id' => isset($data['pic_user_id']) ? (int)$data['pic_user_id'] : null,
            'sync_google' => (int)($data['sync_google'] ?? 1),
            'created_by'  => $this->userId(),
        ]);
        $newId = (int)$model->insertID();

        // Simpan attendees
        if (!empty($data['attendees']) && is_array($data['attendees'])) {
            $this->_saveAttendees($newId, $data['attendees']);
        }

        // Simpan reminders
        $this->_saveReminders($newId, $data['reminders'] ?? null);

        // Sinkronisasi Google Calendar
        $googleEventId = null;
        $syncError     = null;
        if (!empty($data['sync_google'])) {
            try {
                $schedule            = $model->getDetail($newId);
                $gcal                = new GoogleCalendarService();
                $googleEventId       = $gcal->createEvent($schedule);

                $model->update($newId, [
                    'google_event_id'    => $googleEventId,
                    'google_sync_status' => 'synced',
                    'google_synced_at'   => date('Y-m-d H:i:s'),
                    'google_sync_error'  => null,
                ]);
                log_message('info', "[Schedule] Event dibuat di Google Calendar: {$googleEventId} untuk agenda #{$newId}");
            } catch (\Throwable $e) {
                $syncError = $e->getMessage();
                $model->update($newId, [
                    'google_sync_status' => 'failed',
                    'google_sync_error'  => $this->_sanitizeSyncError($e->getMessage()),
                ]);
                log_message('warning', '[Schedule] Sync create gagal untuk agenda #' . $newId . ': ' . $e->getMessage());
            }
        }

        $this->auditLog("Buat agenda: {$kode}", 'schedules', $newId);

        $res = ['id' => $newId, 'kode' => $kode];
        if ($syncError) $res['sync_warning'] = 'Google Calendar: ' . $syncError;
        if ($googleEventId) $res['google_event_id'] = $googleEventId;

        return $this->success($res, 'Agenda berhasil disimpan.', 201);
    }

    // ──────────────────────────────────────────────────────────────
    // PUT /api/v1/schedule/:id
    // ──────────────────────────────────────────────────────────────
    public function update(int $id)
    {
        $this->requirePermission('schedule.manage');

        $model    = new ScheduleModel();
        $schedule = $model->find($id);
        if (!$schedule) return $this->fail('Agenda tidak ditemukan.', 404);

        $data = $this->request->getJSON(true) ?? [];

        $model->update($id, array_filter([
            'judul'       => $data['judul']       ?? null,
            'deskripsi'   => $data['deskripsi']   ?? null,
            'category_id' => isset($data['category_id']) ? (int)$data['category_id'] : null,
            'lokasi'      => $data['lokasi']       ?? null,
            'tgl_mulai'   => $data['tgl_mulai']    ?? null,
            'jam_mulai'   => $data['jam_mulai']    ?? null,
            'tgl_selesai' => $data['tgl_selesai']  ?? null,
            'jam_selesai' => $data['jam_selesai']  ?? null,
            'all_day'     => isset($data['all_day']) ? (int)$data['all_day'] : null,
            'warna'       => $data['warna']        ?? null,
            'pic_user_id' => isset($data['pic_user_id']) ? (int)$data['pic_user_id'] : null,
            'sync_google' => isset($data['sync_google']) ? (int)$data['sync_google'] : null,
            'updated_by'  => $this->userId(),
        ], fn($v) => $v !== null));

        if (!empty($data['attendees'])) {
            $this->db->table('schedule_attendees')->where('schedule_id', $id)->delete();
            $this->_saveAttendees($id, $data['attendees']);
        }

        if (isset($data['reminders'])) {
            $this->db->table('schedule_reminders')->where('schedule_id', $id)->delete();
            $this->_saveReminders($id, $data['reminders']);
        }

        // Sync update ke Google Calendar
        $syncError = null;
        if (!empty($data['sync_google'])) {
            try {
                $updated = $model->getDetail($id);
                (new GoogleCalendarService())->updateEvent($updated);

                $model->update($id, [
                    'google_sync_status' => 'synced',
                    'google_synced_at'   => date('Y-m-d H:i:s'),
                    'google_sync_error'  => null,
                ]);
                log_message('info', "[Schedule] Event diperbarui di Google Calendar untuk agenda #{$id}");
            } catch (\Throwable $e) {
                $syncError = $e->getMessage();
                $model->update($id, [
                    'google_sync_status' => 'failed',
                    'google_sync_error'  => $this->_sanitizeSyncError($e->getMessage()),
                ]);
                log_message('warning', '[Schedule] Sync update gagal untuk agenda #' . $id . ': ' . $e->getMessage());
            }
        }

        $this->auditLog("Update agenda #{$id}", 'schedules', $id);

        $res = ['id' => $id];
        if ($syncError) $res['sync_warning'] = 'Google Calendar: ' . $syncError;

        return $this->success($res, 'Agenda berhasil diperbarui.');
    }

    // ──────────────────────────────────────────────────────────────
    // DELETE /api/v1/schedule/:id
    // ──────────────────────────────────────────────────────────────
    public function delete(int $id)
    {
        $this->requirePermission('schedule.manage');

        $model    = new ScheduleModel();
        $schedule = $model->find($id);
        if (!$schedule) return $this->fail('Agenda tidak ditemukan.', 404);

        // Hapus dari Google Calendar jika ada
        if (!empty($schedule['google_event_id'])) {
            try {
                (new GoogleCalendarService())->deleteEvent($schedule['google_event_id']);
            } catch (\Throwable $e) {
                log_message('warning', '[Schedule] Delete Google event gagal: ' . $e->getMessage());
            }
        }

        $model->delete($id);
        $this->auditLog("Hapus agenda #{$id}", 'schedules', $id);

        return $this->success(null, 'Agenda berhasil dihapus.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/schedule/categories
    // Query: all=1 → semua termasuk non-aktif (admin only)
    // ──────────────────────────────────────────────────────────────
    public function categories()
    {
        $all   = $this->request->getGet('all');
        $model = new ScheduleCategoryModel();
        if ($all && in_array($this->userRole(), ['admin', 'tu'])) {
            $items = $model->orderBy('id')->findAll();
        } else {
            $items = $model->getAktif();
        }
        return $this->success(['items' => $items]);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/schedule/categories
    // ──────────────────────────────────────────────────────────────
    public function storeCategory()
    {
        $this->requirePermission('schedule.manage');

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'nama' => 'required|max_length[100]',
            'kode' => 'required|max_length[50]|regex_match[/^[a-z0-9_]+$/]',
            'warna'=> 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
        ];
        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        // Cek kode unik
        $model = new ScheduleCategoryModel();
        if ($model->where('kode', $data['kode'])->countAllResults()) {
            return $this->fail("Kode '{$data['kode']}' sudah digunakan.", 422);
        }

        $newId = $model->insert([
            'nama'     => $data['nama'],
            'kode'     => $data['kode'],
            'warna'    => $data['warna'] ?? '#6366f1',
            'is_aktif' => 1,
        ], true);

        $this->auditLog("Buat kategori schedule: {$data['kode']}", 'schedule_categories', (int)$newId);
        return $this->success(['id' => $newId], 'Kategori berhasil dibuat.', 201);
    }

    // ──────────────────────────────────────────────────────────────
    // PUT /api/v1/schedule/categories/:id
    // ──────────────────────────────────────────────────────────────
    public function updateCategory(int $id)
    {
        $this->requirePermission('schedule.manage');

        $model = new ScheduleCategoryModel();
        $cat   = $model->find($id);
        if (!$cat) return $this->fail('Kategori tidak ditemukan.', 404);

        $data = $this->request->getJSON(true) ?? [];
        $update = array_filter([
            'nama'     => $data['nama']     ?? null,
            'warna'    => $data['warna']    ?? null,
            'is_aktif' => isset($data['is_aktif']) ? (int)$data['is_aktif'] : null,
        ], fn($v) => $v !== null);

        // Kode bisa diupdate hanya jika bukan kategori sistem
        if (!empty($data['kode']) && $data['kode'] !== $cat['kode']) {
            if ($model->where('kode', $data['kode'])->where('id !=', $id)->countAllResults()) {
                return $this->fail("Kode '{$data['kode']}' sudah digunakan.", 422);
            }
            $update['kode'] = $data['kode'];
        }

        $model->update($id, $update);
        $this->auditLog("Update kategori schedule #{$id}", 'schedule_categories', $id);
        return $this->success(null, 'Kategori berhasil diperbarui.');
    }

    // ──────────────────────────────────────────────────────────────
    // DELETE /api/v1/schedule/categories/:id
    // Soft delete: set is_aktif = 0 (tidak benar-benar hapus)
    // ──────────────────────────────────────────────────────────────
    public function deleteCategory(int $id)
    {
        $this->requirePermission('schedule.manage');

        $model = new ScheduleCategoryModel();
        $cat   = $model->find($id);
        if (!$cat) return $this->fail('Kategori tidak ditemukan.', 404);

        // Cegah hapus jika masih ada agenda yang pakai
        $used = $this->db->table('schedules')
                         ->where('category_id', $id)
                         ->where('deleted_at', null)
                         ->countAllResults();
        if ($used > 0) {
            return $this->fail("Kategori masih digunakan oleh {$used} agenda. Nonaktifkan saja.", 422);
        }

        // Nonaktifkan (soft delete)
        $model->update($id, ['is_aktif' => 0]);
        $this->auditLog("Nonaktifkan kategori schedule #{$id}", 'schedule_categories', $id);
        return $this->success(null, 'Kategori berhasil dinonaktifkan.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/schedule/widget-stats
    // ──────────────────────────────────────────────────────────────
    public function widgetStats()
    {
        $this->requirePermission('schedule.view');
        $picFilter = $this->userRole() === 'klien' ? $this->userId() : null;
        return $this->success((new ScheduleModel())->getWidgetStats($picFilter));
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/schedule/resync/:id — Re-sync manual 1 agenda
    // ──────────────────────────────────────────────────────────────
    public function resync(int $id)
    {
        $this->requirePermission('schedule.manage');

        $model    = new ScheduleModel();
        $schedule = $model->getDetail($id);
        if (!$schedule) return $this->fail('Agenda tidak ditemukan.', 404);

        try {
            $gcal = new GoogleCalendarService();
            if (empty($schedule['google_event_id'])) {
                $eventId = $gcal->createEvent($schedule);
                $model->update($id, [
                    'google_event_id'    => $eventId,
                    'google_sync_status' => 'synced',
                    'google_synced_at'   => date('Y-m-d H:i:s'),
                    'google_sync_error'  => null,
                    'sync_google'        => 1,
                ]);
                log_message('info', "[Schedule] Re-sync CREATE berhasil untuk agenda #{$id}, event: {$eventId}");
            } else {
                $gcal->updateEvent($schedule);
                $model->update($id, [
                    'google_sync_status' => 'synced',
                    'google_synced_at'   => date('Y-m-d H:i:s'),
                    'google_sync_error'  => null,
                ]);
                log_message('info', "[Schedule] Re-sync UPDATE berhasil untuk agenda #{$id}");
            }
        } catch (\Throwable $e) {
            $model->update($id, [
                'google_sync_status' => 'failed',
                'google_sync_error'  => $this->_sanitizeSyncError($e->getMessage()),
            ]);
            log_message('warning', '[Schedule] Re-sync gagal untuk agenda #' . $id . ': ' . $e->getMessage());
            return $this->fail('Re-sync gagal: ' . $e->getMessage(), 422);
        }

        return $this->success(null, 'Re-sync berhasil.');
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Sanitasi pesan error agar tidak menyertakan credential/token sensitif.
     * Dipanggil sebelum menyimpan pesan error ke database.
     */
    private function _sanitizeSyncError(string $message): string
    {
        // Hilangkan kemungkinan token/secret yang terselip di pesan
        $sanitized = preg_replace('/Bearer\s+\S+/i', 'Bearer [REDACTED]', $message);
        $sanitized = preg_replace('/client_secret["\s:=]+\S+/i', 'client_secret=[REDACTED]', $sanitized);
        $sanitized = preg_replace('/refresh_token["\s:=]+\S+/i', 'refresh_token=[REDACTED]', $sanitized);
        // Batasi panjang agar tidak overflow kolom TEXT
        return mb_substr($sanitized, 0, 1000);
    }

    private function _saveAttendees(int $scheduleId, array $attendees): void
    {
        $rows = [];
        foreach ($attendees as $a) {
            if (empty($a['user_id']) && empty($a['email'])) continue;
            $rows[] = [
                'schedule_id' => $scheduleId,
                'user_id'     => isset($a['user_id']) ? (int)$a['user_id'] : null,
                'nama'        => $a['nama'] ?? null,
                'email'       => $a['email'] ?? null,
                'status_rsvp' => $a['status_rsvp'] ?? 'undangan',
                'created_at'  => date('Y-m-d H:i:s'),
            ];
        }
        if ($rows) $this->db->table('schedule_attendees')->insertBatch($rows);
    }

    private function _saveReminders(int $scheduleId, ?array $reminders): void
    {
        // Jika tidak ada custom reminders, pakai default dari settings
        if (empty($reminders)) {
            $cfg = (new \App\Models\Schedule\GoogleCalendarSettingsModel())->getDefault();
            if (!$cfg || !$cfg['reminder_aktif']) return;

            $reminders = [
                ['menit' => (int)$cfg['reminder_h7'], 'metode' => 'popup'],
                ['menit' => (int)$cfg['reminder_h3'], 'metode' => 'popup'],
                ['menit' => (int)$cfg['reminder_h1'], 'metode' => 'popup'],
            ];
        }

        $rows = [];
        foreach ($reminders as $r) {
            $rows[] = [
                'schedule_id' => $scheduleId,
                'menit'       => (int)($r['menit'] ?? 1440),
                'metode'      => $r['metode'] ?? 'popup',
            ];
        }
        if ($rows) $this->db->table('schedule_reminders')->insertBatch($rows);
    }
}
