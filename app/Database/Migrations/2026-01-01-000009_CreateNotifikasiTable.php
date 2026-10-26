<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotifikasiTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'judul'      => ['type' => 'VARCHAR', 'constraint' => 200],
            'pesan'      => ['type' => 'TEXT', 'null' => true],
            'tipe'       => ['type' => 'ENUM', 'constraint' => ['task_baru', 'task_update', 'deadline', 'terlambat', 'anggaran', 'selesai'], 'default' => 'task_baru'],
            'is_read'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'is_read']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('notifikasi');
    }

    public function down(): void
    {
        $this->forge->dropTable('notifikasi', true);
    }
}
