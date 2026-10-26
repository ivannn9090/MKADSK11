<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Master hari libur nasional untuk kalkulasi hari kerja SLA.
 */
class CreateLtkHariLibur extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tanggal'     => ['type' => 'DATE', 'null' => false],
            'keterangan'  => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('tanggal');
        $this->forge->createTable('ltk_hari_libur', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ltk_hari_libur', true);
    }
}
