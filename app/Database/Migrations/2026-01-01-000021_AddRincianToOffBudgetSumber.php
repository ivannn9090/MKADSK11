<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah kolom rincian_anggaran_id ke off_budget_sumber.
 *
 * Dengan kolom ini, setiap alokasi off-budget dikaitkan ke
 * komponen biaya (rincian_anggaran) tertentu dari kegiatan sumber,
 * sehingga sisa anggaran per komponen bisa dihitung dengan tepat.
 *
 * Kolom nullable agar data lama tetap valid.
 */
class AddRincianToOffBudgetSumber extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('off_budget_sumber', [
            'rincian_anggaran_id' => [
                'type'    => 'INT',
                'unsigned' => true,
                'null'    => true,
                'default' => null,
                'after'   => 'sumber_id',
            ],
        ]);

        $this->db->query('
            ALTER TABLE off_budget_sumber
            ADD CONSTRAINT fk_obs_rincian
            FOREIGN KEY (rincian_anggaran_id) REFERENCES rincian_anggaran(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE off_budget_sumber DROP FOREIGN KEY fk_obs_rincian');
        $this->forge->dropColumn('off_budget_sumber', 'rincian_anggaran_id');
    }
}
