<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * PosisiTimController — CRUD master posisi tim + sync user↔posisi
 *
 * GET    /api/v1/posisi-tim          → index()   daftar semua posisi
 * POST   /api/v1/posisi-tim          → store()   tambah posisi baru
 * PUT    /api/v1/posisi-tim/:id      → update()  ubah nama posisi
 * DELETE /api/v1/posisi-tim/:id      → delete()  hapus posisi (non-sistem)
 * PUT    /api/v1/users/:id/posisi    → syncUserPosisi()  set posisi user
 */
class PosisiTimController extends BaseApiController
{
    // ──────────────────────────────────────────────────────
    // GET /api/v1/posisi-tim
    // ──────────────────────────────────────────────────────
    public function index(): ResponseInterface
    {
        $items = $this->db->table('posisi_tim')
                          ->orderBy('urutan')
                          ->orderBy('nama')
                          ->get()->getResultArray();

        return $this->success($items);
    }

    // ──────────────────────────────────────────────────────
    // POST /api/v1/posisi-tim
    // ──────────────────────────────────────────────────────
    public function store(): ResponseInterface
    {
        $this->requirePermission('users.manage');

        $data = $this->request->getJSON(true) ?? [];
        $nama = trim($data['nama'] ?? '');

        if (!$nama) {
            return $this->fail('Nama posisi wajib diisi.', 422);
        }
        if (mb_strlen($nama) > 100) {
            return $this->fail('Nama posisi maksimal 100 karakter.', 422);
        }

        // Cek duplikat
        $exists = $this->db->table('posisi_tim')
                           ->where('nama', $nama)
                           ->countAllResults();
        if ($exists) {
            return $this->fail("Posisi '{$nama}' sudah ada.", 422);
        }

        $now    = date('Y-m-d H:i:s');
        $urutan = (int) $this->db->table('posisi_tim')->selectMax('urutan')->get()->getRowArray()['urutan'] + 1;

        $this->db->table('posisi_tim')->insert([
            'nama'       => $nama,
            'is_system'  => 0,
            'urutan'     => $urutan,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $newId = $this->db->insertID();

        $this->auditLog("Tambah posisi tim: {$nama}", 'posisi_tim', $newId);

        return $this->success(['id' => $newId, 'nama' => $nama], 'Posisi tim berhasil ditambahkan.', 201);
    }

    // ──────────────────────────────────────────────────────
    // PUT /api/v1/posisi-tim/:id
    // ──────────────────────────────────────────────────────
    public function update(int $id): ResponseInterface
    {
        $this->requirePermission('users.manage');

        $posisi = $this->db->table('posisi_tim')->where('id', $id)->get()->getRowArray();
        if (!$posisi) return $this->fail('Posisi tidak ditemukan.', 404);

        $data = $this->request->getJSON(true) ?? [];
        $nama = trim($data['nama'] ?? '');

        if (!$nama) return $this->fail('Nama posisi wajib diisi.', 422);

        // Cek duplikat (kecuali diri sendiri)
        $exists = $this->db->table('posisi_tim')
                           ->where('nama', $nama)
                           ->where('id !=', $id)
                           ->countAllResults();
        if ($exists) return $this->fail("Posisi '{$nama}' sudah ada.", 422);

        $this->db->table('posisi_tim')->where('id', $id)->update([
            'nama'       => $nama,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditLog("Ubah posisi tim #{$id}: {$posisi['nama']} → {$nama}", 'posisi_tim', $id);

        return $this->success(null, 'Posisi tim berhasil diperbarui.');
    }

    // ──────────────────────────────────────────────────────
    // DELETE /api/v1/posisi-tim/:id
    // ──────────────────────────────────────────────────────
    public function delete(int $id): ResponseInterface
    {
        $this->requirePermission('users.manage');

        $posisi = $this->db->table('posisi_tim')->where('id', $id)->get()->getRowArray();
        if (!$posisi) return $this->fail('Posisi tidak ditemukan.', 404);

        if ((int)$posisi['is_system']) {
            return $this->fail('Posisi sistem tidak dapat dihapus.', 403);
        }

        // Hapus referensi di user_posisi_tim dulu
        $this->db->table('user_posisi_tim')->where('posisi_tim_id', $id)->delete();
        $this->db->table('posisi_tim')->where('id', $id)->delete();

        $this->auditLog("Hapus posisi tim: {$posisi['nama']}", 'posisi_tim', $id);

        return $this->success(null, "Posisi '{$posisi['nama']}' berhasil dihapus.");
    }

    // ──────────────────────────────────────────────────────
    // PUT /api/v1/users/:id/posisi
    // Body: { posisi_ids: [1, 3, 5] }
    // ──────────────────────────────────────────────────────
    public function syncUserPosisi(int $userId): ResponseInterface
    {
        $this->requirePermission('users.manage');

        // Pastikan user ada
        $user = $this->db->table('users')->where('id', $userId)->get()->getRowArray();
        if (!$user) return $this->fail('User tidak ditemukan.', 404);

        $data     = $this->request->getJSON(true) ?? [];
        $posisiIds = array_map('intval', $data['posisi_ids'] ?? []);

        // Validasi semua id ada
        if (!empty($posisiIds)) {
            $validCount = $this->db->table('posisi_tim')
                                   ->whereIn('id', $posisiIds)
                                   ->countAllResults();
            if ($validCount !== count($posisiIds)) {
                return $this->fail('Beberapa posisi tidak ditemukan.', 422);
            }
        }

        // Sync
        $now = date('Y-m-d H:i:s');
        $this->db->table('user_posisi_tim')->where('user_id', $userId)->delete();

        foreach (array_unique($posisiIds) as $pid) {
            $this->db->table('user_posisi_tim')->ignore(true)->insert([
                'user_id'       => $userId,
                'posisi_tim_id' => $pid,
                'created_at'    => $now,
            ]);
        }

        // Update users.posisi_tim (kolom lama) dengan nama posisi pertama untuk backward compat
        $firstName = null;
        if (!empty($posisiIds)) {
            $first = $this->db->table('posisi_tim')->where('id', $posisiIds[0])->get()->getRowArray();
            $firstName = $first['nama'] ?? null;
        }
        $this->db->table('users')->where('id', $userId)->update([
            'posisi_tim' => $firstName,
            'updated_at' => $now,
        ]);

        $this->auditLog("Sync posisi tim user #{$userId}", 'user_posisi_tim', $userId);

        return $this->success(null, 'Posisi tim berhasil diperbarui.');
    }
}
