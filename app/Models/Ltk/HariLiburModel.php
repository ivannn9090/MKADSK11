<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class HariLiburModel extends Model
{
    protected $table         = 'ltk_hari_libur';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['tanggal', 'keterangan'];

    /** Kembalikan array tanggal libur dalam format 'Y-m-d'. */
    public function getTanggalLibur(int $tahun = 0): array
    {
        $q = $this->select('tanggal');
        if ($tahun > 0) {
            $q->like('tanggal', (string)$tahun, 'after');
        }
        $rows = $q->findAll();
        return array_column($rows, 'tanggal');
    }
}
