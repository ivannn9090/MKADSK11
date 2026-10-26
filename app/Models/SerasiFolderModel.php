<?php
namespace App\Models;
use CodeIgniter\Model;

/**
 * SerasiFolderModel v2 — SharePoint REST API
 * Menyimpan struktur folder SharePoint (hasil sinkronisasi).
 */
class SerasiFolderModel extends Model
{
    protected $table      = 'serasi_folders';
    protected $primaryKey = 'folder_id';
    protected $returnType = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'sharepoint_folder_id','folder_name','full_path',
        'parent_folder_id','item_count','synced_at',
    ];

    /** Bangun pohon hierarki dari flat list */
    public function getTree(): array
    {
        $all = $this->orderBy('full_path')->findAll();
        return $this->buildTree($all, null);
    }

    private function buildTree(array $all, ?int $parentId): array
    {
        $nodes = [];
        foreach ($all as $row) {
            if ($row['parent_folder_id'] == $parentId) {
                $row['children'] = $this->buildTree($all, (int)$row['folder_id']);
                $nodes[] = $row;
            }
        }
        return $nodes;
    }

    /** Upsert folder dari payload SharePoint REST API */
    public function upsertFromSharePoint(array $spFolder, ?int $parentDbId): int
    {
        $existing = $this->where('sharepoint_folder_id', $spFolder['UniqueId'] ?? '')->first();
        $data = [
            'sharepoint_folder_id' => $spFolder['UniqueId']          ?? null,
            'folder_name'          => $spFolder['Name']              ?? '',
            'full_path'            => $spFolder['ServerRelativeUrl'] ?? '',
            'parent_folder_id'     => $parentDbId,
            'item_count'           => $spFolder['ItemCount']         ?? 0,
            'synced_at'            => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            $this->update($existing['folder_id'], $data);
            return (int)$existing['folder_id'];
        }
        $this->insert($data);
        return $this->getInsertID();
    }
}
