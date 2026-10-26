<?php
namespace App\Models;
use CodeIgniter\Model;

/**
 * SerasiMetadataModel v2
 * Metadata kurator — diisi manual oleh Editor/Admin.
 */
class SerasiMetadataModel extends Model
{
    protected $table      = 'serasi_metadata';
    protected $primaryKey = 'metadata_id';
    protected $returnType = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'document_id','jenis_dokumen','tahun','program',
        'sub_kegiatan','tim_penyusun','kata_kunci',
    ];

    public function upsert(int $docId, array $data): void
    {
        $existing = $this->where('document_id',$docId)->first();
        $data['document_id'] = $docId;
        if ($existing) {
            $this->update($existing['metadata_id'], $data);
        } else {
            $this->insert($data);
        }
    }
}
