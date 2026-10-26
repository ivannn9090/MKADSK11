<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ubah kolom `role` di tabel users dari ENUM → VARCHAR(60)
 * agar dapat menampung role dinamis yang dibuat admin.
 */
class ChangeRoleColumnToVarchar extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('users', [
            'role' => [
                'name'       => 'role',
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => false,
                'default'    => 'anggota_tim',
            ],
        ]);
    }

    public function down(): void
    {
        // Kembalikan ke ENUM (hanya role sistem)
        $this->forge->modifyColumn('users', [
            'role' => [
                'name'       => 'role',
                'type'       => 'ENUM',
                'constraint' => ['direktur','ketua_tim','anggota_tim','perencana_anggaran','admin','tu','klien','pic_telaah'],
                'null'       => false,
                'default'    => 'anggota_tim',
            ],
        ]);
    }
}
