<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKegiatanTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nama_kegiatan'   => ['type' => 'VARCHAR', 'constraint' => 200],
            'deskripsi'       => ['type' => 'TEXT', 'null' => true],
            'tanggal_mulai'   => ['type' => 'DATE'],
            'tanggal_selesai' => ['type' => 'DATE'],
            'pagu_anggaran'   => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'status'          => ['type' => 'ENUM', 'constraint' => ['draft', 'berjalan', 'selesai', 'terlambat'], 'default' => 'draft'],
            'team_id'         => ['type' => 'INT', 'unsigned' => true],
            'created_by'      => ['type' => 'INT', 'unsigned' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addKey('team_id');
        $this->forge->addForeignKey('team_id', 'teams', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('kegiatan');
    }

    public function down(): void
    {
        $this->forge->dropTable('kegiatan', true);
    }
}
