<?php

namespace App\Controllers\Api;

use App\Models\RoModel;
use App\Models\KegiatanModel;
use CodeIgniter\HTTP\ResponseInterface;

class RoController extends BaseApiController
{
    private RoModel $model;

    public function __construct()
    {
        $this->model = new RoModel();
    }

    /** GET /api/v1/ro — daftar semua RO dengan rollup stats */
    public function index(): ResponseInterface
    {
        $rows = $this->model->getWithStats();
        return $this->success($rows);
    }

    /** GET /api/v1/ro/:id — detail RO + child kegiatan */
    public function show(int $id): ResponseInterface
    {
        $ro = $this->model->getDetail($id);
        if (! $ro) return $this->fail('RO tidak ditemukan.', 404);
        return $this->success($ro);
    }

    /** POST /api/v1/ro */
    public function create(): ResponseInterface
    {
        $this->requireRole(['direktur', 'perencana_anggaran', 'tu']);

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if (! $this->validate(['nama_ro' => 'required|max_length[200]'])) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $insertData = [
            'nama_ro'    => $data['nama_ro'],
            'kode_ro'    => $data['kode_ro']    ?? null,
            'deskripsi'  => $data['deskripsi']  ?? null,
            'tahun'      => $data['tahun']       ?? null,
            'team_id'    => $data['team_id']     ?? null,
            'created_by' => $this->userId(),
        ];

        $this->model->insert($insertData);
        $newId = $this->model->insertID();

        $this->auditLog("Membuat RO: {$data['nama_ro']}", 'ro', $newId, null, $insertData);

        return $this->success(['id' => $newId], 'RO berhasil dibuat.', 201);
    }

    /** PUT /api/v1/ro/:id */
    public function update(int $id): ResponseInterface
    {
        $this->requireRole(['direktur', 'perencana_anggaran', 'tu']);

        $ro = $this->model->find($id);
        if (! $ro) return $this->fail('RO tidak ditemukan.', 404);

        $data = $this->request->getJSON(true) ?? $this->request->getRawInput();

        $allowed    = ['nama_ro', 'kode_ro', 'deskripsi', 'tahun', 'team_id'];
        $updateData = array_intersect_key($data, array_flip($allowed));

        if (empty($updateData)) return $this->fail('Tidak ada data yang dapat diubah.', 422);

        $this->model->update($id, $updateData);
        $this->auditLog("Mengubah RO: {$ro['nama_ro']}", 'ro', $id, $ro, $updateData);

        return $this->success(null, 'RO berhasil diperbarui.');
    }

    /** DELETE /api/v1/ro/:id — set ro_id=NULL pada child kegiatan, lalu hapus RO */
    public function delete(int $id): ResponseInterface
    {
        $this->requireRole(['direktur']);

        $ro = $this->model->find($id);
        if (! $ro) return $this->fail('RO tidak ditemukan.', 404);

        // Lepas semua child kegiatan sebelum hapus RO
        $this->db->query("UPDATE kegiatan SET ro_id = NULL WHERE ro_id = ?", [$id]);

        $this->model->delete($id);
        $this->auditLog("Menghapus RO: {$ro['nama_ro']}", 'ro', $id, $ro, null);

        return $this->success(null, 'RO berhasil dihapus.');
    }
}
