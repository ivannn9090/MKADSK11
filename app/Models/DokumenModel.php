<?php

namespace App\Models;

use CodeIgniter\Model;

class DokumenModel extends Model
{
    protected $table         = 'dokumen';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['kegiatan_id', 'nama_file', 'jenis_file', 'file_url', 'uploaded_by'];
    protected $useTimestamps = false;

    public function getByKegiatan(int $kegiatanId): array
    {
        return $this->select('dokumen.*, users.nama AS uploaded_by_nama')
                    ->join('users', 'users.id = dokumen.uploaded_by', 'left')
                    ->where('kegiatan_id', $kegiatanId)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }
}
