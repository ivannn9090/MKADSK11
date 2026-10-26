<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nama', 'email', 'password', 'jabatan', 'posisi_tim', 'role', 'status', 'must_change_password'];

    protected $useTimestamps = true;
    protected $hidden        = ['password'];

    // Validasi role dilakukan secara dinamis via getValidRoles()
    // agar role kustom dari tabel `roles` bisa diterima
    protected $validationRules = [
        'nama'  => 'required|max_length[150]',
        'email' => 'required|valid_email|max_length[150]',
    ];

    /**
     * Ambil semua slug role valid dari tabel roles + role sistem tetap.
     */
    public static function getValidRoles(): array
    {
        $db    = \Config\Database::connect();
        $slugs = $db->table('roles')->select('slug')->get()->getResultArray();
        $from_table = array_column($slugs, 'slug');

        // Gabung dengan role sistem yang mungkin belum ada di tabel roles
        $system = ['admin', 'klien', 'pic_telaah'];
        return array_unique(array_merge($from_table, $system));
    }
}
