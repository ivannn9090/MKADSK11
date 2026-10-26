<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ganti relasi satu-sumber (sumber_kegiatan_id) menjadi banyak-sumber
 * menggunakan tabel pivot off_budget_sumber.
 *
 * Tabel off_budget_sumber:
 *   - off_budget_id   : FK → kegiatan.id (kegiatan off-budget)
 *   - sumber_id       : FK → kegiatan.id (kegiatan on-budget yang jadi sumber)
 *   - alokasi         : nominal anggaran yang dialokasikan dari sumber ini
 *   - keterangan      : catatan perencana
 *   - created_by      : user yang mengatur (perencana_anggaran / direktur)
 *
 * UNIQUE KEY (off_budget_id, sumber_id) — satu off-budget tidak boleh
 * menggunakan sumber yang sama dua kali.
 */
class CreateOffBudgetSumberTable extends Migration
{
    public function up(): void
    {
        // 1. Hapus FK dan kolom lama sumber_kegiatan_id dari kegiatan
        $this->db->query('ALTER TABLE kegiatan DROP FOREIGN KEY fk_kegiatan_sumber');
        $this->forge->dropColumn('kegiatan', 'sumber_kegiatan_id');

        // 2. Buat tabel pivot
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'off_budget_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'sumber_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'alokasi'        => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'null' => false],
            'keterangan'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['off_budget_id', 'sumber_id']);
        $this->forge->addKey('off_budget_id');
        $this->forge->addKey('sumber_id');
        $this->forge->addForeignKey('off_budget_id', 'kegiatan', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sumber_id',     'kegiatan', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('created_by',    'users',    'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('off_budget_sumber');
    }

    public function down(): void
    {
        $this->forge->dropTable('off_budget_sumber', true);

        $this->forge->addColumn('kegiatan', [
            'sumber_kegiatan_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
                'after'    => 'tipe',
            ],
        ]);
        $this->db->query('ALTER TABLE kegiatan ADD CONSTRAINT fk_kegiatan_sumber FOREIGN KEY (sumber_kegiatan_id) REFERENCES kegiatan(id) ON DELETE SET NULL');
    }
}
