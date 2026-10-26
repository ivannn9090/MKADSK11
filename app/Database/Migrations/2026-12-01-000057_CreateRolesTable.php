<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRolesTable extends Migration
{
    public function up(): void
    {
        // Tabel definisi role dinamis
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 60],
            'label'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'warna'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '#6b7280'],
            'is_system'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0,
                              'comment' => '1 = role bawaan sistem, tidak bisa dihapus'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('roles');

        // Tabel permission per role (checkbox)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'role_slug'  => ['type' => 'VARCHAR', 'constraint' => 60],
            'permission' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['role_slug', 'permission'], 'uq_role_perm');
        $this->forge->createTable('role_permissions');

        // Kolom posisi_tim pada users
        $this->forge->addColumn('users', [
            'posisi_tim' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
                'default'    => null,
                'after'      => 'jabatan',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('role_permissions', true);
        $this->forge->dropTable('roles', true);
        $this->forge->dropColumn('users', 'posisi_tim');
    }
}
