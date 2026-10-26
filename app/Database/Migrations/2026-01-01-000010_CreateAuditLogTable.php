<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'aktivitas'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'tabel_terkait' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'record_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'data_sebelum'  => ['type' => 'JSON', 'null' => true],
            'data_sesudah'  => ['type' => 'JSON', 'null' => true],
            'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'waktu'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('waktu');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('audit_log');
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_log', true);
    }
}
