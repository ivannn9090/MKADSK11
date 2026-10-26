<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 000060 — Multi-role support
 * - Buat tabel user_roles (user_id ↔ role_slug, many-to-many)
 * - Isi user_roles dari kolom users.role yang ada (migrasi data)
 * - Kolom users.role TIDAK dihapus agar backward-compat dengan JWT lama
 *   (diisi role utama / role pertama user)
 */
class CreateUserRolesTable extends Migration
{
    public function up(): void
    {
        // ── Buat tabel user_roles ─────────────────────────────
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'role_slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'role_slug']);
        $this->forge->addKey('user_id');
        $this->forge->createTable('user_roles', true);

        // ── Migrasi data: salin users.role → user_roles ───────
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $users = $db->table('users')
                    ->select('id, role')
                    ->where('role IS NOT NULL')
                    ->where('role !=', '')
                    ->get()
                    ->getResultArray();

        foreach ($users as $u) {
            $db->table('user_roles')->ignore(true)->insert([
                'user_id'    => $u['id'],
                'role_slug'  => $u['role'],
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('user_roles', true);
    }
}
