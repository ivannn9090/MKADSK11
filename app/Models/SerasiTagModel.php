<?php
namespace App\Models;
use CodeIgniter\Model;

/**
 * SerasiTagModel v2
 */
class SerasiTagModel extends Model
{
    protected $table      = 'serasi_tags';
    protected $primaryKey = 'tag_id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['tag_name','warna'];

    public function getAllWithCount(): array
    {
        $db = \Config\Database::connect();
        return $db->table('serasi_tags st')
            ->select('st.tag_id, st.tag_name, st.warna, COUNT(dt.document_id) AS jumlah_dokumen')
            ->join('serasi_document_tags dt','dt.tag_id = st.tag_id','left')
            ->groupBy('st.tag_id')
            ->orderBy('jumlah_dokumen','DESC')
            ->get()->getResultArray();
    }

    public function findOrCreate(string $name): int
    {
        $r = $this->where('tag_name', $name)->first();
        if ($r) return (int)$r['tag_id'];
        $this->insert(['tag_name' => $name]);
        return $this->getInsertID();
    }
}
