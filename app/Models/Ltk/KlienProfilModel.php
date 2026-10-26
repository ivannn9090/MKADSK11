<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class KlienProfilModel extends Model
{
    protected $table         = 'ltk_klien_profil';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = ['user_id', 'unit_kerja', 'jabatan', 'no_telepon', 'is_verified', 'verified_by', 'verified_at'];

    public function getByUserId(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first();
    }

    public function getDaftarBelumVerifikasi(): array
    {
        return $this->select('ltk_klien_profil.*, u.nama, u.email')
                    ->join('users AS u', 'u.id = ltk_klien_profil.user_id')
                    ->where('is_verified', 0)
                    ->orderBy('ltk_klien_profil.created_at', 'DESC')
                    ->findAll();
    }

    public function verifikasi(int $userId, int $verifiedBy): void
    {
        $this->where('user_id', $userId)->set([
            'is_verified' => 1,
            'verified_by' => $verifiedBy,
            'verified_at' => date('Y-m-d H:i:s'),
        ])->update();
    }
}
