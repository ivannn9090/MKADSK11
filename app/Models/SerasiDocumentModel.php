<?php
namespace App\Models;
use CodeIgniter\Model;

/**
 * SerasiDocumentModel v2 — SharePoint REST API
 * File asli tetap di SharePoint. DB menyimpan metadata saja.
 */
class SerasiDocumentModel extends Model
{
    protected $table      = 'serasi_documents';
    protected $primaryKey = 'document_id';
    protected $returnType = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'sharepoint_file_id','file_name','file_extension','file_size',
        'folder_id','file_url','sharepoint_web_url',
        'created_date','modified_date','status','created_by','updated_by',
    ];

    // ──────────────────────────────────────────────────────────────
    // Query utama: dokumen + metadata + tags
    // ──────────────────────────────────────────────────────────────
    public function getAllWithMeta(array $filters = []): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('serasi_documents d')
            ->select('d.*, m.jenis_dokumen, m.tahun, m.program, m.sub_kegiatan, m.tim_penyusun, m.kata_kunci,
                      f.folder_name, f.full_path,
                      GROUP_CONCAT(st.tag_name ORDER BY st.tag_name SEPARATOR ",") AS tag_list')
            ->join('serasi_metadata m',      'm.document_id = d.document_id','left')
            ->join('serasi_folders f',       'f.folder_id   = d.folder_id',  'left')
            ->join('serasi_document_tags dt','dt.document_id = d.document_id','left')
            ->join('serasi_tags st',         'st.tag_id      = dt.tag_id',    'left')
            ->where('d.status','aktif')
            ->groupBy('d.document_id');

        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $builder->groupStart()
                ->like('d.file_name',      $q)
                ->orLike('m.kata_kunci',   $q)
                ->orLike('m.tim_penyusun', $q)
                ->orLike('m.program',      $q)
                ->orLike('f.full_path',    $q)
                ->orLike('m.jenis_dokumen',$q)
                ->groupEnd();
        }
        if (!empty($filters['tahun']))       $builder->where('m.tahun',         $filters['tahun']);
        if (!empty($filters['jenis']))       $builder->where('m.jenis_dokumen', $filters['jenis']);
        if (!empty($filters['program']))     $builder->like('m.program',        $filters['program']);
        if (!empty($filters['sub_kegiatan']))$builder->like('m.sub_kegiatan',   $filters['sub_kegiatan']);
        if (!empty($filters['tim']))         $builder->like('m.tim_penyusun',   $filters['tim']);
        if (!empty($filters['folder_id']))   $builder->where('d.folder_id',     $filters['folder_id']);
        if (!empty($filters['tag'])) {
            $builder->join('serasi_document_tags dt2','dt2.document_id = d.document_id','inner')
                    ->join('serasi_tags st2','st2.tag_id = dt2.tag_id','inner')
                    ->where('st2.tag_name', $filters['tag']);
        }

        $rows = $builder->get()->getResultArray();
        return array_map(function($r) {
            $r['tags'] = $r['tag_list'] ? explode(',', $r['tag_list']) : [];
            unset($r['tag_list']);
            return $r;
        }, $rows);
    }

    public function getOneWithMeta(int $id): ?array
    {
        $list = $this->getAllWithMeta();
        foreach ($list as $item) {
            if ((int)$item['document_id'] === $id) return $item;
        }
        return null;
    }

    public function getStats(): array
    {
        $db = \Config\Database::connect();
        $rows = $db->table('serasi_documents d')
            ->select('m.jenis_dokumen, COUNT(*) as total')
            ->join('serasi_metadata m','m.document_id = d.document_id','left')
            ->where('d.status','aktif')
            ->groupBy('m.jenis_dokumen')
            ->get()->getResultArray();
        $map = [];
        foreach ($rows as $r) $map[$r['jenis_dokumen']] = (int)$r['total'];
        return [
            'total'        => array_sum($map),
            'policy_brief' => $map['Policy Brief']   ?? 0,
            'policy_paper' => $map['Policy Paper']   ?? 0,
            'telaah'       => $map['Telaah']          ?? 0,
            'kajian'       => $map['Kajian Analisis'] ?? 0,
            'pok_kak'      => ($map['POK']??0)+($map['KAK']??0),
            'notula'       => $map['Notula']          ?? 0,
            'daftar_hadir' => $map['Daftar Hadir']    ?? 0,
            'laporan'      => $map['Laporan']          ?? 0,
        ];
    }

    public function syncTags(int $docId, array $tagIds): void
    {
        $db = \Config\Database::connect();
        $db->table('serasi_document_tags')->where('document_id',$docId)->delete();
        foreach (array_unique($tagIds) as $tid) {
            $db->table('serasi_document_tags')->insert([
                'document_id'=>$docId,'tag_id'=>(int)$tid,'created_at'=>date('Y-m-d H:i:s')
            ]);
        }
    }

    /** Upsert dokumen dari payload SharePoint REST API */
    public function upsertFromSharePoint(array $spFile, int $folderId, int $userId): int
    {
        $existing = $this->where('sharepoint_file_id', $spFile['UniqueId'] ?? '')->first();
        $ext = pathinfo($spFile['Name'] ?? '', PATHINFO_EXTENSION);
        $data = [
            'sharepoint_file_id' => $spFile['UniqueId']          ?? null,
            'file_name'          => $spFile['Name']              ?? '',
            'file_extension'     => strtolower($ext),
            'file_size'          => $spFile['Length']            ?? 0,
            'folder_id'          => $folderId,
            'file_url'           => $spFile['ServerRelativeUrl'] ?? null,
            'sharepoint_web_url' => isset($spFile['ServerRelativeUrl'])
                ? (env('SHAREPOINT_SITE_URL','') . $spFile['ServerRelativeUrl'])
                : null,
            'created_date'       => isset($spFile['TimeCreated'])
                ? date('Y-m-d H:i:s', strtotime($spFile['TimeCreated'])) : null,
            'modified_date'      => isset($spFile['TimeLastModified'])
                ? date('Y-m-d H:i:s', strtotime($spFile['TimeLastModified'])) : null,
            'status'             => 'aktif',
            'created_by'         => $userId,
            'updated_by'         => $userId,
        ];
        if ($existing) {
            $this->update($existing['document_id'], $data);
            return (int)$existing['document_id'];
        }
        $this->insert($data);
        return $this->getInsertID();
    }
}
