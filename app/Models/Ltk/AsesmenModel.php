<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class AsesmenModel extends Model
{
    protected $table         = 'ltk_asesmen';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['permohonan_id', 'pic_user_id', 'skenario', 'catatan_asesmen', 'tgl_asesmen'];

    public function getByPermohonan(int $permohonanId): ?array
    {
        return $this->select('ltk_asesmen.*, u.nama AS pic_nama')
                    ->join('users AS u', 'u.id = ltk_asesmen.pic_user_id', 'left')
                    ->where('permohonan_id', $permohonanId)
                    ->orderBy('tgl_asesmen', 'DESC')
                    ->first();
    }
}
