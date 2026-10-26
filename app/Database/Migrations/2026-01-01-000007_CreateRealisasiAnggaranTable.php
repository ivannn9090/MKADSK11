<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRealisasiAnggaranTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'kegiatan_id'     => ['type' => 'INT', 'unsigned' => true],
            'tanggal'         => ['type' => 'DATE'],
            'uraian'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'nominal'         => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'bukti_transaksi' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by'      => ['type' => 'INT', 'unsigned' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('kegiatan_id');
        $this->forge->addForeignKey('kegiatan_id', 'kegiatan', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('realisasi_anggaran');
    }

    public function down(): void
    {
        $this->forge->dropTable('realisasi_anggaran', true);
    }
}
