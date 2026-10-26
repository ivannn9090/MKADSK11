<?php

namespace App\Controllers\Api;

use App\Models\RoleModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RoleController — CRUD role dinamis + manajemen permissions.
 * Semua endpoint hanya bisa diakses oleh admin.
 */
class RoleController extends BaseApiController
{
    private RoleModel $model;

    public function __construct()
    {
        $this->model = new RoleModel();
    }

    /** GET /api/v1/roles — list semua role + permissions */
    public function index(): ResponseInterface
    {
        // Semua pengguna terautentikasi boleh melihat daftar role (untuk dropdown)
        $roles = $this->model->getAllWithPermissions();
        return $this->success($roles);
    }

    /** GET /api/v1/roles/permissions — daftar semua permission yang tersedia */
    public function permissions(): ResponseInterface
    {
        $perms = array_map(
            fn($slug, $label) => ['slug' => $slug, 'label' => $label],
            array_keys(RoleModel::allPermissions()),
            array_values(RoleModel::allPermissions())
        );
        return $this->success($perms);
    }

    /** POST /api/v1/roles — buat role baru */
    public function create(): ResponseInterface
    {
        $this->requireRole(['admin']);

        $data = $this->request->getJSON(true) ?? [];

        if (empty($data['slug']))  return $this->fail("Field 'slug' wajib diisi.", 422);
        if (empty($data['label'])) return $this->fail("Field 'label' wajib diisi.", 422);

        // Slug: lowercase, hanya huruf/angka/underscore
        $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($data['slug'])));
        if (empty($slug)) return $this->fail('Slug tidak valid.', 422);

        // Cek duplikat
        if ($this->model->where('slug', $slug)->first()) {
            return $this->fail("Role dengan slug '{$slug}' sudah ada.", 422);
        }

        $roleId = $this->model->insert([
            'slug'      => $slug,
            'label'     => trim($data['label']),
            'warna'     => $data['warna'] ?? '#6b7280',
            'is_system' => 0,
        ]);

        $permissions = $data['permissions'] ?? [];
        if (!empty($permissions)) {
            $this->model->syncPermissions($slug, $permissions);
        }

        $this->auditLog("Buat role: {$slug}", 'roles', $this->model->insertID());

        return $this->success(['id' => $this->model->insertID()], 'Role berhasil dibuat.', 201);
    }

    /** PUT /api/v1/roles/:id — update label, warna, permissions */
    public function update(int $id): ResponseInterface
    {
        $this->requireRole(['admin']);

        $role = $this->model->find($id);
        if (!$role) return $this->fail('Role tidak ditemukan.', 404);

        $data   = $this->request->getJSON(true) ?? [];
        $update = [];

        if (isset($data['label'])) $update['label'] = trim($data['label']);
        if (isset($data['warna'])) $update['warna'] = $data['warna'];

        if (!empty($update)) {
            $this->model->update($id, $update);
        }

        if (isset($data['permissions'])) {
            $this->model->syncPermissions($role['slug'], $data['permissions']);
        }

        $this->auditLog("Update role: {$role['slug']}", 'roles', $id);

        return $this->success(null, 'Role berhasil diperbarui.');
    }

    /** DELETE /api/v1/roles/:id — hapus role (tidak bisa hapus system role) */
    public function delete(int $id): ResponseInterface
    {
        $this->requireRole(['admin']);

        $role = $this->model->find($id);
        if (!$role) return $this->fail('Role tidak ditemukan.', 404);

        if ($role['is_system']) {
            return $this->fail('Role sistem tidak dapat dihapus.', 403);
        }

        // Cek apakah ada user yang memakai role ini
        $count = $this->db->table('users')->where('role', $role['slug'])->countAllResults();
        if ($count > 0) {
            return $this->fail(
                "Role ini digunakan oleh {$count} pengguna. Ubah role mereka terlebih dahulu.",
                422
            );
        }

        $this->db->table('role_permissions')->where('role_slug', $role['slug'])->delete();
        $this->model->delete($id);
        $this->auditLog("Hapus role: {$role['slug']}", 'roles', $id);

        return $this->success(null, 'Role berhasil dihapus.');
    }
}
