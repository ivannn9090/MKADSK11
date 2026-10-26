<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class DokumenOutputModel extends Model
{
    protected $table         = 'ltk_dokumen_output';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'permohonan_id', 'tipe_dokumen', 'nomor_dokumen', 'tgl_dokumen',
        'file_docx_path', 'file_pdf_path',
        'link_cloud', 'keterangan_link',
        'upload_manual', 'digenerate_oleh',
    ];

    public function getByPermohonan(int $permohonanId): array
    {
        return $this->where('permohonan_id', $permohonanId)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    public function getLatestByTipe(int $permohonanId, string $tipe): ?array
    {
        return $this->where('permohonan_id', $permohonanId)
                    ->where('tipe_dokumen', $tipe)
                    ->orderBy('created_at', 'DESC')
                    ->first();
    }
}
