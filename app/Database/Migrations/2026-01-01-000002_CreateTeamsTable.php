<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTeamsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nama_tim'     => ['type' => 'VARCHAR', 'constraint' => 150],
            'ketua_tim_id' => ['type' => 'INT', 'unsigned' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('ketua_tim_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('teams');
    }

    public function down(): void
    {
        $this->forge->dropTable('teams', true);
    }
}
