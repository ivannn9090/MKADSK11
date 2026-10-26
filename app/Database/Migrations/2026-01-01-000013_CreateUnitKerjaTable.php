<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel unit_kerja: master unit kerja (KD1, KD2, KP, SDMKS, dst.)
 * Digunakan sebagai dimensi baris pada tabel realisasi & RPD.
 */
class CreateUnitKerjaTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'kode'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'urutan'     => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->createTable('unit_kerja');
    }

    public function down(): void
    {
        $this->forge->dropTable('unit_kerja', true);
    }
}
