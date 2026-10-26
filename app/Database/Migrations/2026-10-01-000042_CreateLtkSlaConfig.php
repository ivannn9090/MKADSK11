<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Konfigurasi SLA per skenario (A = 6 HK, B = 28 HK).
 * Seed data dimasukkan langsung saat migrasi.
 */
class CreateLtkSlaConfig extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'skenario'    => ['type' => 'ENUM', 'constraint' => ['A','B'], 'null' => false],
            'hari_kerja'  => ['type' => 'TINYINT', 'unsigned' => true, 'null' => false],
            'keterangan'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'updated_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('skenario');
        $this->forge->createTable('ltk_sla_config', true);

        // Seed default SLA
        $this->db->table('ltk_sla_config')->insertBatch([
            ['skenario' => 'A', 'hari_kerja' => 6,  'keterangan' => 'Tidak memerlukan tindak lanjut'],
            ['skenario' => 'B', 'hari_kerja' => 28, 'keterangan' => 'Memerlukan tindak lanjut'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('ltk_sla_config', true);
    }
}
