<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class UserController extends BaseApiController
{
    private UserModel $model;

    public function __construct()
    {
        $this->model = new UserModel();
    }

    public function index(): ResponseInterface
    {
        $filterRole = $this->request->getGet('role');
        $query      = $this->model->select('id, nama, email, jabatan, posisi_tim, role, status')
                                  ->where('role !=', 'admin')
                                  ->where('role !=', 'klien')
                                  ->orderBy('nama');

        if ($filterRole) $query->where('role', $filterRole);

        $users = $query->findAll();

        if (!empty($users)) {
            $userIds = array_column($users, 'id');

            // Sertakan semua role dari user_roles
            $urRows   = $this->db->table('user_roles')
                                 ->whereIn('user_id', $userIds)
                                 ->get()->getResultArray();
            $rolesMap = [];
            foreach ($urRows as $r) {
                $rolesMap[$r['user_id']][] = $r['role_slug'];
            }

            // Sertakan semua posisi dari user_posisi_tim
            $upRows    = $this->db->table('user_posisi_tim up')
                                  ->select('up.user_id, pt.id as posisi_id, pt.nama as posisi_nama')
                                  ->join('posisi_tim pt', 'pt.id = up.posisi_tim_id')
                                  ->whereIn('up.user_id', $userIds)
                                  ->orderBy('pt.urutan')
                                  ->get()->getResultArray();
            $posisiMap = [];
            foreach ($upRows as $r) {
                $posisiMap[$r['user_id']][] = [
                    'id'   => (int)$r['posisi_id'],
                    'nama' => $r['posisi_nama'],
                ];
            }

            foreach ($users as &$u) {
                $u['roles']  = $rolesMap[$u['id']] ?? [$u['role']];
                $u['posisi'] = $posisiMap[$u['id']] ?? (
                    $u['posisi_tim'] ? [['id' => 0, 'nama' => $u['posisi_tim']]] : []
                );
            }
            unset($u);
        }

        return $this->success($users);
    }

    /**
     * GET /api/v1/users/klien
     * Daftar akun Klien beserta profil (unit_kerja, jabatan, is_verified).
     */
    public function klienIndex(): ResponseInterface
    {
        $this->requireRole(['admin', 'tu', 'direktur']);

        $rows = $this->db->query(
            "SELECT u.id, u.nama, u.email, u.jabatan AS jabatan_user, u.status,
                    k.unit_kerja, k.jabatan, k.no_telepon, k.is_verified,
                    k.verified_at,
                    vby.nama AS verified_by_nama
             FROM users u
             LEFT JOIN ltk_klien_profil k  ON k.user_id   = u.id
             LEFT JOIN users vby           ON vby.id      = k.verified_by
             WHERE u.role = 'klien'
             ORDER BY k.is_verified ASC, u.nama ASC"
        )->getResultArray();

        return $this->success($rows);
    }

    /**
     * PUT /api/v1/users/klien/:id/status
     * Admin/TU ubah status akun klien (aktif / nonaktif).
     */
    public function klienStatus(int $id): ResponseInterface
    {
        $this->requireRole(['admin', 'tu']);

        $user = $this->model->find($id);
        if (!$user || $user['role'] !== 'klien') {
            return $this->fail('Akun klien tidak ditemukan.', 404);
        }

        $status = $this->request->getJSON(true)['status'] ?? '';
        if (!in_array($status, ['aktif', 'nonaktif'], true)) {
            return $this->fail('Status tidak valid. Pilih: aktif atau nonaktif.', 422);
        }

        $this->model->update($id, ['status' => $status]);
        $this->auditLog("Ubah status klien #{$id} → {$status}", 'users', $id);

        return $this->success(null, "Status akun klien diubah menjadi {$status}.");
    }

    public function show(int $id): ResponseInterface
    {
        $user = $this->model->select('id, nama, email, jabatan, posisi_tim, role, status')->find($id);
        if (! $user) return $this->fail('User tidak ditemukan.', 404);

        // Profil admin tidak bisa dilihat melalui endpoint publik ini
        if ($user['role'] === 'admin') return $this->fail('User tidak ditemukan.', 404);

        return $this->success($user);
    }

    public function create(): ResponseInterface
    {
        $this->requireRole(['direktur', 'tu']);

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        // Normalisasi: dukung `roles` (array) maupun `role` (string lama)
        $roles = $this->_normalizeRoles($data);

        // Tidak bisa membuat akun admin/klien
        foreach ($roles as $r) {
            if (in_array($r, ['admin', 'klien'], true)) {
                return $this->fail('Role tersebut tidak dapat dibuat melalui menu ini.', 403);
            }
        }

        $rules = [
            'nama'     => 'required|max_length[150]',
            'email'    => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        if (empty($roles)) {
            return $this->fail('Minimal satu role wajib dipilih.', 422);
        }

        // Validasi semua role dari tabel roles
        $validRoles = \App\Models\UserModel::getValidRoles();
        foreach ($roles as $r) {
            if (! in_array($r, $validRoles, true)) {
                return $this->fail("Role '{$r}' tidak valid.", 422);
            }
        }

        // role utama = role pertama (untuk JWT backward-compat)
        $primaryRole = $roles[0];

        $insertData = [
            'nama'     => $data['nama'],
            'email'    => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'jabatan'  => $data['jabatan'] ?? null,
            'role'     => $primaryRole,
        ];

        $this->model->insert($insertData);
        $newId = $this->model->insertID();

        // Simpan semua role ke user_roles
        $this->_syncUserRoles($newId, $roles);

        $this->auditLog("Membuat user: {$data['nama']}", 'users', $newId, null, ['nama' => $data['nama'], 'roles' => $roles]);

        return $this->success(['id' => $newId], 'User berhasil dibuat.', 201);
    }

    public function update(int $id): ResponseInterface
    {
        $this->requireRole(['direktur', 'tu']);

        $user = $this->model->find($id);
        if (! $user) return $this->fail('User tidak ditemukan.', 404);

        // Tidak bisa edit akun admin
        if ($user['role'] === 'admin') {
            return $this->fail('User tidak ditemukan.', 404);
        }

        $data       = $this->request->getJSON(true) ?? [];
        $validRoles = \App\Models\UserModel::getValidRoles();

        // Cek apakah ada update roles
        $hasRolesUpdate = isset($data['roles']) || isset($data['role']);
        $roles          = $hasRolesUpdate ? $this->_normalizeRoles($data) : null;

        if ($roles !== null) {
            foreach ($roles as $r) {
                if (in_array($r, ['admin', 'klien'], true)) {
                    return $this->fail('Role tersebut tidak dapat diatur melalui menu ini.', 403);
                }
                if (! in_array($r, $validRoles, true)) {
                    return $this->fail("Role '{$r}' tidak valid.", 422);
                }
            }
            if (empty($roles)) {
                return $this->fail('Minimal satu role wajib dipilih.', 422);
            }
        }

        // TU hanya boleh mengubah field 'role'/'roles'
        if ($this->userRole() === 'tu') {
            if (! $hasRolesUpdate) {
                return $this->fail('TU hanya dapat mengubah role pengguna.', 403);
            }
            $updateData = ['role' => $roles[0]];
        } else {
            $allowed    = ['nama', 'email', 'jabatan', 'posisi_tim', 'status'];
            $updateData = array_intersect_key($data, array_flip($allowed));

            if ($roles !== null) {
                $updateData['role'] = $roles[0]; // role utama
            }

            if (! empty($data['password'])) {
                $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
            }
        }

        if (!empty($updateData)) {
            $this->model->update($id, $updateData);
        }

        // Sync user_roles jika ada perubahan role
        if ($roles !== null) {
            $this->_syncUserRoles($id, $roles);
        }

        $this->auditLog("Mengubah user: {$user['nama']}", 'users', $id, $user, $updateData ?? []);

        return $this->success(null, 'User berhasil diperbarui.');
    }

    /**
     * DELETE /api/v1/users/:id
     * Hanya direktur/admin yang boleh menghapus.
     * Tidak bisa menghapus: diri sendiri, akun admin, akun aktif dengan data terkait.
     */
    public function delete(int $id): ResponseInterface
    {
        $this->requireRole(['direktur', 'admin']);

        // Tidak bisa hapus diri sendiri
        if ($this->userId() === $id) {
            return $this->fail('Tidak dapat menghapus akun sendiri.', 403);
        }

        $user = $this->model->find($id);
        if (!$user) return $this->fail('User tidak ditemukan.', 404);

        // Tidak bisa hapus akun admin
        if ($user['role'] === 'admin') {
            return $this->fail('Akun admin tidak dapat dihapus.', 403);
        }

        // Tidak bisa hapus klien via endpoint ini (pakai endpoint klien)
        if ($user['role'] === 'klien') {
            return $this->fail('Gunakan endpoint khusus untuk menghapus akun klien.', 403);
        }

        $nama = $user['nama'];
        $this->model->delete($id);
        $this->auditLog("Menghapus user: {$nama}", 'users', $id, $user);

        return $this->success(null, "Akun {$nama} berhasil dihapus.");
    }

    /**
     * DELETE /api/v1/users/klien/:id
     * Hapus akun klien beserta profil LTK-nya.
     * Hanya admin/tu yang boleh.
     */
    public function klienDelete(int $id): ResponseInterface
    {
        $this->requireRole(['admin', 'tu']);

        $user = $this->model->find($id);
        if (!$user || $user['role'] !== 'klien') {
            return $this->fail('Akun klien tidak ditemukan.', 404);
        }

        // Cek apakah klien masih punya permohonan aktif
        $aktif = $this->db->table('ltk_permohonan')
                          ->where('klien_user_id', $id)
                          ->whereNotIn('status', ['selesai', 'ditutup'])
                          ->where('deleted_at', null)
                          ->countAllResults();
        if ($aktif > 0) {
            return $this->fail(
                "Akun klien tidak dapat dihapus karena masih memiliki {$aktif} permohonan aktif.",
                422
            );
        }

        $nama = $user['nama'];
        // ltk_klien_profil terhapus otomatis via ON DELETE CASCADE
        $this->model->delete($id);
        $this->auditLog("Menghapus akun klien: {$nama}", 'users', $id, $user);

        return $this->success(null, "Akun klien {$nama} berhasil dihapus.");
    }

    // ─────────────────────────────────────────────────────────
    // Helper: normalisasi input roles (dukung `roles` array & `role` string)
    // ─────────────────────────────────────────────────────────
    private function _normalizeRoles(array $data): array
    {
        if (!empty($data['roles']) && is_array($data['roles'])) {
            return array_values(array_filter(array_unique($data['roles'])));
        }
        if (!empty($data['role']) && is_string($data['role'])) {
            return [$data['role']];
        }
        return [];
    }

    // ─────────────────────────────────────────────────────────
    // Helper: sync tabel user_roles (hapus lama, insert baru)
    // ─────────────────────────────────────────────────────────
    private function _syncUserRoles(int $userId, array $roles): void
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $db->table('user_roles')->where('user_id', $userId)->delete();

        foreach (array_unique($roles) as $slug) {
            $db->table('user_roles')->ignore(true)->insert([
                'user_id'    => $userId,
                'role_slug'  => $slug,
                'created_at' => $now,
            ]);
        }
    }
}
