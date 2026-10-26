<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah dukungan Off Budget pada tabel kegiatan.
 *
 * - tipe          : ENUM('on_budget','off_budget') DEFAULT 'on_budget'
 * - sumber_kegiatan_id : FK ke kegiatan.id (NULL = kegiatan on-budget biasa)
 *
 * Kegiatan off-budget menggunakan anggaran dari kegiatan sumber.
 * Realisasi off-budget dihitung terhadap sisa anggaran kegiatan sumber.
 */
class AddOffBudgetToKegiatan extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('kegiatan', [
            'tipe' => [
                'type'       => 'ENUM',
                'constraint' => ['on_budget', 'off_budget'],
                'default'    => 'on_budget',
                'null'       => false,
                'after'      => 'status',
            ],
            'sumber_kegiatan_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
                'after'    => 'tipe',
            ],
        ]);

        // FK: sumber_kegiatan_id → kegiatan.id (self-reference)
        $this->db->query('ALTER TABLE kegiatan ADD CONSTRAINT fk_kegiatan_sumber FOREIGN KEY (sumber_kegiatan_id) REFERENCES kegiatan(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE kegiatan DROP FOREIGN KEY fk_kegiatan_sumber');
        $this->forge->dropColumn('kegiatan', ['tipe', 'sumber_kegiatan_id']);
    }
}
