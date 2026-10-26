<?php
namespace App\Controllers\Api;

use App\Models\SerasiDocumentModel;
use App\Models\SerasiMetadataModel;
use App\Models\SerasiTagModel;
use App\Models\SerasiFolderModel;

/**
 * SerasiController v2 — SERASI SharePoint-based
 * CRUD metadata dokumen, tag, folder, dan statistik.
 */
class SerasiController extends BaseApiController
{
    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/serasi/stats
    // ──────────────────────────────────────────────────────────────
    public function stats()
    {
        return $this->success((new SerasiDocumentModel())->getStats());
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/serasi/documents
    //   ?q=&tahun=&jenis=&program=&sub_kegiatan=&tim=&tag=&folder_id=
    // ──────────────────────────────────────────────────────────────
    public function documentsIndex()
    {
        $f = [
            'q'            => trim($this->request->getGet('q')           ?? ''),
            'tahun'        => $this->request->getGet('tahun')            ?? '',
            'jenis'        => $this->request->getGet('jenis')            ?? '',
            'program'      => $this->request->getGet('program')          ?? '',
            'sub_kegiatan' => $this->request->getGet('sub_kegiatan')     ?? '',
            'tim'          => $this->request->getGet('tim')              ?? '',
            'tag'          => $this->request->getGet('tag')              ?? '',
            'folder_id'    => $this->request->getGet('folder_id')        ?? '',
        ];
        $items = (new SerasiDocumentModel())->getAllWithMeta(array_filter($f));
        return $this->success(['items' => $items, 'total' => count($items)]);
    }

    // GET /api/v1/serasi/documents/:id
    public function documentsShow(int $id)
    {
        $item = (new SerasiDocumentModel())->getOneWithMeta($id);
        if (!$item) return $this->fail('Dokumen tidak ditemukan.', 404);
        return $this->success($item);
    }

    // ──────────────────────────────────────────────────────────────
    // POST /api/v1/serasi/documents  — input manual
    // Role: editor, direktur
    // ──────────────────────────────────────────────────────────────
    public function documentsCreate()
    {
        $this->requireRole(['direktur','ketua_tim']);
        $data    = $this->request->getJSON(true);
        $docMdl  = new SerasiDocumentModel();
        $metaMdl = new SerasiMetadataModel();
        $tagMdl  = new SerasiTagModel();

        $tagNames = $data['tag_names'] ?? [];
        $meta     = $data['metadata']  ?? [];
        unset($data['tag_names'], $data['metadata'], $data['tags']);
        $data['created_by'] = $this->userId();
        $data['updated_by'] = $this->userId();
        $data['status']     = 'aktif';

        if (!$docMdl->insert($data)) {
            return $this->fail('Gagal menyimpan dokumen. ' . implode(', ', $docMdl->errors()));
        }
        $docId = $docMdl->getInsertID();

        if ($meta) $metaMdl->upsert($docId, $meta);

        $tagIds = [];
        foreach ($tagNames as $n) $tagIds[] = $tagMdl->findOrCreate(trim($n));
        if ($tagIds) $docMdl->syncTags($docId, $tagIds);

        $this->auditLog('Tambah Dokumen SERASI','serasi_documents',$docId,null,$data);
        return $this->success(['document_id'=>$docId],'Dokumen berhasil ditambahkan.',201);
    }

    // PUT /api/v1/serasi/documents/:id
    public function documentsUpdate(int $id)
    {
        $this->requireRole(['direktur','ketua_tim']);
        $docMdl  = new SerasiDocumentModel();
        $before  = $docMdl->find($id);
        if (!$before) return $this->fail('Dokumen tidak ditemukan.', 404);

        $data     = $this->request->getJSON(true);
        $tagNames = $data['tag_names'] ?? null;
        $meta     = $data['metadata']  ?? [];
        $tagMdl   = new SerasiTagModel();
        $metaMdl  = new SerasiMetadataModel();
        unset($data['tag_names'],$data['metadata'],$data['tags']);
        $data['updated_by'] = $this->userId();

        $docMdl->update($id, $data);
        if ($meta) $metaMdl->upsert($id, $meta);
        if ($tagNames !== null) {
            $tagIds = [];
            foreach ($tagNames as $n) $tagIds[] = $tagMdl->findOrCreate(trim($n));
            $docMdl->syncTags($id, $tagIds);
        }
        $this->auditLog('Update Dokumen SERASI','serasi_documents',$id,$before,$data);
        return $this->success(null,'Dokumen berhasil diperbarui.');
    }

    // DELETE /api/v1/serasi/documents/:id  (soft)
    public function documentsDelete(int $id)
    {
        $this->requireRole(['direktur']);
        $docMdl = new SerasiDocumentModel();
        $before = $docMdl->find($id);
        if (!$before) return $this->fail('Dokumen tidak ditemukan.',404);
        $docMdl->update($id,['status'=>'dihapus','updated_by'=>$this->userId()]);
        $this->auditLog('Hapus Dokumen SERASI','serasi_documents',$id,$before,null);
        return $this->success(null,'Dokumen dihapus.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET  /api/v1/serasi/tags
    // POST /api/v1/serasi/tags
    // DELETE /api/v1/serasi/tags/:id
    // ──────────────────────────────────────────────────────────────
    public function tagsIndex()
    {
        return $this->success(['items' => (new SerasiTagModel())->getAllWithCount()]);
    }

    public function tagCreate()
    {
        $this->requireRole(['direktur','ketua_tim']);
        $data = $this->request->getJSON(true);
        if (empty($data['tag_name'])) return $this->fail('Nama tag wajib diisi.');
        $tagMdl = new SerasiTagModel();
        $id = $tagMdl->findOrCreate(trim($data['tag_name']));
        if (!empty($data['warna'])) $tagMdl->update($id,['warna'=>$data['warna']]);
        return $this->success(['tag_id'=>$id],'Tag berhasil disimpan.',201);
    }

    public function tagDelete(int $id)
    {
        $this->requireRole(['direktur']);
        $tagMdl = new SerasiTagModel();
        if (!$tagMdl->find($id)) return $this->fail('Tag tidak ditemukan.',404);
        $tagMdl->delete($id);
        return $this->success(null,'Tag dihapus.');
    }

    // ──────────────────────────────────────────────────────────────
    // GET /api/v1/serasi/folders
    // ──────────────────────────────────────────────────────────────
    public function foldersIndex()
    {
        return $this->success(['items' => (new SerasiFolderModel())->getTree()]);
    }
}
