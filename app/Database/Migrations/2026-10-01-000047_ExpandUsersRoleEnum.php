<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Perluas ENUM kolom `role` pada tabel `users` agar mencakup
 * role LTK: 'klien' dan 'pic_telaah'.
 *
 * Sebelumnya ENUM hanya: direktur, ketua_tim, anggota_tim,
 * perencana_anggaran, admin, tu
 *
 * Sesudahnya ditambah: klien, pic_telaah
 */
class ExpandUsersRoleEnum extends Migration
{
    public function up(): void
    {
        $this->db->query("
            ALTER TABLE users
            MODIFY COLUMN role
            ENUM(
                'direktur',
                'ketua_tim',
                'anggota_tim',
                'perencana_anggaran',
                'admin',
                'tu',
                'klien',
                'pic_telaah'
            ) NOT NULL DEFAULT 'anggota_tim'
        ");
    }

    public function down(): void
    {
        // Hapus row klien/pic_telaah lebih dulu agar rollback tidak error constraint
        $this->db->query("
            ALTER TABLE users
            MODIFY COLUMN role
            ENUM(
                'direktur',
                'ketua_tim',
                'anggota_tim',
                'perencana_anggaran',
                'admin',
                'tu'
            ) NOT NULL DEFAULT 'anggota_tim'
        ");
    }
}
