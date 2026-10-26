<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah role 'perencana_anggaran' ke ENUM kolom role di tabel users.
 * CI4/MySQLi: ALTER TABLE dengan MODIFY COLUMN.
 */
class AddPerencanaAnggaranRole extends Migration
{
    public function up(): void
    {
        $this->db->query("
            ALTER TABLE users
            MODIFY COLUMN role ENUM('direktur','ketua_tim','anggota_tim','perencana_anggaran')
            NOT NULL DEFAULT 'anggota_tim'
        ");
    }

    public function down(): void
    {
        $this->db->query("
            ALTER TABLE users
            MODIFY COLUMN role ENUM('direktur','ketua_tim','anggota_tim')
            NOT NULL DEFAULT 'anggota_tim'
        ");
    }
}
