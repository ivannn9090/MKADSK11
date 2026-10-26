<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class LtkNotifikasiModel extends Model
{
    protected $table         = 'ltk_notifikasi';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['user_id', 'permohonan_id', 'tipe', 'judul', 'pesan', 'is_read'];

    /** Kirim notifikasi ke satu user. */
    public function kirim(int $userId, string $tipe, string $judul, string $pesan, ?int $permohonanId = null): void
    {
        $this->insert([
            'user_id'       => $userId,
            'permohonan_id' => $permohonanId,
            'tipe'          => $tipe,
            'judul'         => $judul,
            'pesan'         => $pesan,
            'is_read'       => 0,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /** Kirim notifikasi ke banyak user sekaligus. */
    public function kirimBulk(array $userIds, string $tipe, string $judul, string $pesan, ?int $permohonanId = null): void
    {
        $rows = [];
        $now  = date('Y-m-d H:i:s');
        foreach (array_unique($userIds) as $uid) {
            $rows[] = [
                'user_id'       => (int)$uid,
                'permohonan_id' => $permohonanId,
                'tipe'          => $tipe,
                'judul'         => $judul,
                'pesan'         => $pesan,
                'is_read'       => 0,
                'created_at'    => $now,
            ];
        }
        if (!empty($rows)) {
            $this->insertBatch($rows);
        }
    }

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

    public function markAllRead(int $userId): void
    {
        $this->where('user_id', $userId)->where('is_read', 0)->set(['is_read' => 1])->update();
    }
}
