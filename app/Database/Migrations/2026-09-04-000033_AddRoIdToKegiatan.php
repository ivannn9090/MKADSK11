<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah kolom ro_id ke tabel kegiatan.
 *
 * ro_id bersifat opsional (nullable) — kegiatan tanpa RO tetap valid (standalone).
 * Saat RO dihapus, ro_id di-set NULL via ON DELETE SET NULL.
 */
class AddRoIdToKegiatan extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('kegiatan', [
            'ro_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
                'after'    => 'tipe',
            ],
        ]);

        $this->db->query('ALTER TABLE kegiatan ADD CONSTRAINT fk_kegiatan_ro FOREIGN KEY (ro_id) REFERENCES ro(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE kegiatan DROP FOREIGN KEY fk_kegiatan_ro');
        $this->forge->dropColumn('kegiatan', 'ro_id');
    }
}
