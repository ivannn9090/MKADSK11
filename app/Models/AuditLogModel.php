<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table         = 'audit_log';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['user_id', 'aktivitas', 'tabel_terkait', 'record_id', 'data_sebelum', 'data_sesudah', 'ip_address', 'waktu'];
    protected $useTimestamps = false;

    public function log(int $userId, string $aktivitas, string $tabel = '', ?int $recordId = null, mixed $before = null, mixed $after = null): void
    {
        $this->insert([
            'user_id'       => $userId,
            'aktivitas'     => $aktivitas,
            'tabel_terkait' => $tabel,
            'record_id'     => $recordId,
            'data_sebelum'  => $before ? json_encode($before) : null,
            'data_sesudah'  => $after  ? json_encode($after)  : null,
            'ip_address'    => service('request')->getIPAddress(),
            'waktu'         => date('Y-m-d H:i:s'),
        ]);
    }
}
