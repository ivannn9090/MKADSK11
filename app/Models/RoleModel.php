<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table         = 'roles';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['slug', 'label', 'warna', 'is_system'];
    protected $useTimestamps = true;

    /** Ambil semua role beserta daftar permissions-nya */
    public function getAllWithPermissions(): array
    {
        $roles = $this->orderBy('is_system', 'DESC')->orderBy('label')->findAll();
        $perms = $this->db->table('role_permissions')
                          ->select('role_slug, permission')
                          ->get()->getResultArray();

        $permMap = [];
        foreach ($perms as $p) {
            $permMap[$p['role_slug']][] = $p['permission'];
        }

        foreach ($roles as &$r) {
            $r['permissions'] = $permMap[$r['slug']] ?? [];
        }
        unset($r);

        return $roles;
    }

    /** Ambil daftar semua permission (slug => label) */
    public static function allPermissions(): array
    {
        return \App\Database\Seeds\RolesSeeder::ALL_PERMISSIONS;
    }

    /** Sync permissions untuk satu role */
    public function syncPermissions(string $slug, array $permissions): void
    {
        $db = \Config\Database::connect();
        $db->table('role_permissions')->where('role_slug', $slug)->delete();

        foreach ($permissions as $perm) {
            if (array_key_exists($perm, self::allPermissions())) {
                $db->table('role_permissions')->insert([
                    'role_slug'  => $slug,
                    'permission' => $perm,
                ]);
            }
        }
    }
}
