<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTasksTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'kegiatan_id'    => ['type' => 'INT', 'unsigned' => true],
            'nama_task'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'pic_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['belum_dimulai', 'sedang_berjalan', 'selesai'], 'default' => 'belum_dimulai'],
            'target_date'    => ['type' => 'DATE', 'null' => true],
            'completed_date' => ['type' => 'DATE', 'null' => true],
            'catatan'        => ['type' => 'TEXT', 'null' => true],
            'urutan'         => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('kegiatan_id');
        $this->forge->addForeignKey('kegiatan_id', 'kegiatan', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('pic_id', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('tasks');
    }

    public function down(): void
    {
        $this->forge->dropTable('tasks', true);
    }
}
