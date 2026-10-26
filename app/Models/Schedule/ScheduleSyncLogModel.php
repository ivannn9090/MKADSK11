<?php

namespace App\Models\Schedule;

use CodeIgniter\Model;

class ScheduleSyncLogModel extends Model
{
    protected $table         = 'schedule_sync_logs';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'schedule_id', 'aksi', 'status', 'google_event_id',
        'pesan', 'request_body', 'response_body', 'dilakukan_oleh', 'created_at',
    ];

    public function log(
        ?int   $scheduleId,
        string $aksi,
        string $status,
        string $pesan,
        ?int   $userId      = null,
        ?string $eventId    = null,
        ?string $reqBody    = null,
        ?string $resBody    = null
    ): void {
        $this->insert([
            'schedule_id'     => $scheduleId,
            'aksi'            => $aksi,
            'status'          => $status,
            'google_event_id' => $eventId,
            'pesan'           => $pesan,
            'request_body'    => $reqBody,
            'response_body'   => $resBody,
            'dilakukan_oleh'  => $userId,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    public function getRecent(int $limit = 50): array
    {
        return $this->db->table('schedule_sync_logs sl')
            ->select('sl.*, u.nama AS user_nama, s.kode AS schedule_kode, s.judul AS schedule_judul')
            ->join('users u', 'u.id = sl.dilakukan_oleh', 'left')
            ->join('schedules s', 's.id = sl.schedule_id', 'left')
            ->orderBy('sl.created_at', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }
}
