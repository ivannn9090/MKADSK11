<?php

namespace App\Models;

use CodeIgniter\Model;

class NotifikasiModel extends Model
{
    protected $table         = 'notifikasi';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['user_id', 'judul', 'pesan', 'tipe', 'is_read'];
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';

    public function getByUser(int $userId, int $limit = 50): array
    {
        return $this->where('user_id', $userId)
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->findAll();
    }

    public function countUnread(int $userId): int
    {
        return $this->where('user_id', $userId)->where('is_read', 0)->countAllResults();
    }

    public function createForTeam(int $teamId, string $judul, string $pesan, string $tipe, ?int $excludeUserId = null): void
    {
        $members = $this->db->table('team_members')->where('team_id', $teamId)->get()->getResultArray();
        foreach ($members as $m) {
            if ($excludeUserId && (int)$m['user_id'] === $excludeUserId) continue;
            $this->insert([
                'user_id'    => $m['user_id'],
                'judul'      => $judul,
                'pesan'      => $pesan,
                'tipe'       => $tipe,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
