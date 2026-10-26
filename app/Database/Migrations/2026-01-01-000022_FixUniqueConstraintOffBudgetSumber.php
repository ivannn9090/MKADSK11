<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Perbaiki UNIQUE constraint tabel off_budget_sumber.
 *
 * Constraint lama: UNIQUE(off_budget_id, sumber_id)
 * → tidak bisa menambah kegiatan sumber yang sama dengan komponen berbeda.
 *
 * Constraint baru: UNIQUE(off_budget_id, sumber_id, rincian_anggaran_id)
 * → satu off-budget boleh mengambil dari kegiatan sumber yang sama
 *    selama komponen (rincian_anggaran_id) berbeda.
 *
 * Catatan MySQL: baris dengan rincian_anggaran_id = NULL masing-masing
 * dianggap unik oleh MySQL (NULL != NULL dalam UNIQUE KEY), sehingga
 * duplikasi NULL ditangani di layer aplikasi melalui getExisting().
 */
class FixUniqueConstraintOffBudgetSumber extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE off_budget_sumber DROP INDEX uq_ob_sumber');
        $this->db->query('ALTER TABLE off_budget_sumber ADD UNIQUE KEY uq_ob_sumber_komponen (off_budget_id, sumber_id, rincian_anggaran_id)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE off_budget_sumber DROP INDEX uq_ob_sumber_komponen');
        $this->db->query('ALTER TABLE off_budget_sumber ADD UNIQUE KEY uq_ob_sumber (off_budget_id, sumber_id)');
    }
}
