<?php

namespace App\Models\Schedule;

use CodeIgniter\Model;

class ScheduleModel extends Model
{
    protected $table         = 'schedules';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $useSoftDeletes= true;
    protected $allowedFields = [
        'kode', 'judul', 'deskripsi', 'category_id', 'lokasi',
        'tgl_mulai', 'jam_mulai', 'tgl_selesai', 'jam_selesai', 'all_day', 'warna',
        'pic_user_id', 'created_by', 'updated_by',
        'sync_google', 'google_event_id', 'google_calendar_id',
        'google_sync_status', 'google_synced_at', 'google_sync_error',
        'ref_type', 'ref_id', 'is_aktif',
    ];

    // ──────────────────────────────────────────────────────────────
    // Generate kode unik: SCH-2026-0001
    // ──────────────────────────────────────────────────────────────
    public function generateKode(): string
    {
        $tahun = date('Y');
        $last  = $this->db->query(
            "SELECT kode FROM schedules WHERE kode LIKE ? ORDER BY id DESC LIMIT 1",
            ["SCH-{$tahun}-%"]
        )->getRow();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->kode);
            $seq   = (int)end($parts) + 1;
        }
        return sprintf('SCH-%s-%04d', $tahun, $seq);
    }

    // ──────────────────────────────────────────────────────────────
    // Ambil daftar event untuk FullCalendar (format array)
    // $start / $end = YYYY-MM-DD
    // ──────────────────────────────────────────────────────────────
    public function getEvents(array $filter = []): array
    {
        $q = $this->db->table('schedules s')
            ->select('s.*, sc.warna AS cat_warna, sc.kode AS cat_kode, sc.nama AS cat_nama, u.nama AS pic_nama')
            ->join('schedule_categories sc', 'sc.id = s.category_id', 'left')
            ->join('users u', 'u.id = s.pic_user_id', 'left')
            ->where('s.deleted_at', null)
            ->where('s.is_aktif', 1);

        if (!empty($filter['start'])) {
            $q->where('s.tgl_selesai >=', $filter['start']);
        }
        if (!empty($filter['end'])) {
            $q->where('s.tgl_mulai <=', $filter['end']);
        }
        if (!empty($filter['category_id'])) {
            $q->where('s.category_id', (int)$filter['category_id']);
        }
        if (!empty($filter['pic_user_id'])) {
            $q->where('s.pic_user_id', (int)$filter['pic_user_id']);
        }
        if (!empty($filter['q'])) {
            $q->like('s.judul', $filter['q']);
        }
        // Filter berdasarkan peserta
        if (!empty($filter['attendee_user_id'])) {
            $q->join('schedule_attendees sa', 'sa.schedule_id = s.id', 'left')
              ->where('sa.user_id', (int)$filter['attendee_user_id'])
              ->groupBy('s.id');
        }

        $rows = $q->orderBy('s.tgl_mulai', 'ASC')->get()->getResultArray();

        // Format untuk FullCalendar
        return array_map([$this, '_toFcEvent'], $rows);
    }

    // ──────────────────────────────────────────────────────────────
    // Format satu row menjadi FullCalendar Event Object
    // ──────────────────────────────────────────────────────────────
    private function _toFcEvent(array $row): array
    {
        $warna = $row['warna'] ?: $row['cat_warna'] ?: '#3b82f6';

        $start = $row['tgl_mulai'];
        $end   = $row['tgl_selesai'];

        if (!$row['all_day']) {
            if ($row['jam_mulai'])  $start .= 'T' . $row['jam_mulai'];
            if ($row['jam_selesai']) $end   .= 'T' . $row['jam_selesai'];
        }

        return [
            'id'              => (int)$row['id'],
            'title'           => $row['judul'],
            'start'           => $start,
            'end'             => $end,
            'allDay'          => (bool)$row['all_day'],
            'backgroundColor' => $warna,
            'borderColor'     => $warna,
            'textColor'       => '#ffffff',
            'extendedProps'   => [
                'kode'         => $row['kode'],
                'deskripsi'    => $row['deskripsi'],
                'lokasi'       => $row['lokasi'],
                'cat_kode'     => $row['cat_kode'],
                'cat_nama'     => $row['cat_nama'],
                'pic_nama'     => $row['pic_nama'],
                'sync_google'  => (bool)$row['sync_google'],
                'sync_status'  => $row['google_sync_status'],
                'google_event_id' => $row['google_event_id'],
                'ref_type'     => $row['ref_type'],
                'ref_id'       => $row['ref_id'],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Ambil satu agenda beserta attendees dan reminders
    // ──────────────────────────────────────────────────────────────
    public function getDetail(int $id): ?array
    {
        $row = $this->db->table('schedules s')
            ->select('s.*, sc.warna AS cat_warna, sc.kode AS cat_kode, sc.nama AS cat_nama, u.nama AS pic_nama, u.jabatan AS pic_jabatan')
            ->join('schedule_categories sc', 'sc.id = s.category_id', 'left')
            ->join('users u', 'u.id = s.pic_user_id', 'left')
            ->where('s.id', $id)
            ->where('s.deleted_at', null)
            ->get()->getRowArray();

        if (!$row) return null;

        $row['attendees'] = $this->db->table('schedule_attendees sa')
            ->select('sa.*, u.nama AS user_nama, u.jabatan AS user_jabatan')
            ->join('users u', 'u.id = sa.user_id', 'left')
            ->where('sa.schedule_id', $id)
            ->get()->getResultArray();

        $row['reminders'] = $this->db->table('schedule_reminders')
            ->where('schedule_id', $id)
            ->get()->getResultArray();

        return $row;
    }

    // ──────────────────────────────────────────────────────────────
    // Widget aggregat untuk dashboard
    // ──────────────────────────────────────────────────────────────
    public function getWidgetStats(?int $picUserId = null): array
    {
        $today    = date('Y-m-d');
        $weekEnd  = date('Y-m-d', strtotime('+7 days'));
        $monthEnd = date('Y-m-d', strtotime('last day of this month'));

        $base = $this->db->table('schedules')
            ->where('deleted_at', null)->where('is_aktif', 1);
        if ($picUserId) $base->where('pic_user_id', $picUserId);

        $cloneBase = fn() => clone $base;

        return [
            'hari_ini'    => (clone $base)->where('schedules.tgl_mulai', $today)->countAllResults(false),
            'minggu_ini'  => (clone $base)->where('schedules.tgl_mulai >=', $today)->where('schedules.tgl_mulai <=', $weekEnd)->countAllResults(false),
            'bulan_ini'   => (clone $base)->where('schedules.tgl_mulai >=', $today)->where('schedules.tgl_mulai <=', $monthEnd)->countAllResults(false),
            'deadline_telaah' => $this->db->table('schedules')
                ->where('schedules.deleted_at', null)
                ->where('schedules.is_aktif', 1)
                ->join('schedule_categories sc', 'sc.id = schedules.category_id')
                ->where('sc.kode', 'telaah')
                ->where('schedules.tgl_mulai >=', $today)
                ->countAllResults(false),
        ];
    }
}
