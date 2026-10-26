<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDokumenTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'kegiatan_id' => ['type' => 'INT', 'unsigned' => true],
            'nama_file'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'jenis_file'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'file_url'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'uploaded_by' => ['type' => 'INT', 'unsigned' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('kegiatan_id', 'kegiatan', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('dokumen');
    }

    public function down(): void
    {
        $this->forge->dropTable('dokumen', true);
    }
}
