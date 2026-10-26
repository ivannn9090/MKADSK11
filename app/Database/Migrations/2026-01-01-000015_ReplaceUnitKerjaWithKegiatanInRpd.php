<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ganti unit_kerja_id → kegiatan_id di tabel rencana_penarikan_dana.
 * Setiap kegiatan memiliki rencana penarikan dana (RPD) per triwulan.
 */
class ReplaceUnitKerjaWithKegiatanInRpd extends Migration
{
    public function up(): void
    {
        // Kosongkan data lama (unit_kerja based) karena skema berubah
        $this->db->query("DELETE FROM rencana_penarikan_dana");

        // Hapus FK & unique key lama, tambah kegiatan_id
        $this->db->query("ALTER TABLE rencana_penarikan_dana DROP FOREIGN KEY rencana_penarikan_dana_unit_kerja_id_foreign");
        $this->db->query("ALTER TABLE rencana_penarikan_dana DROP INDEX unit_kerja_id");

        $this->forge->modifyColumn('rencana_penarikan_dana', [
            'unit_kerja_id' => [
                'name'     => 'kegiatan_id',
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
        ]);

        $this->db->query("ALTER TABLE rencana_penarikan_dana ADD UNIQUE KEY uq_kegiatan_tahun (kegiatan_id, tahun_anggaran)");
        $this->db->query("ALTER TABLE rencana_penarikan_dana ADD CONSTRAINT fk_rpd_kegiatan FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE");
    }

    public function down(): void
    {
        $this->db->query("DELETE FROM rencana_penarikan_dana");
        $this->db->query("ALTER TABLE rencana_penarikan_dana DROP FOREIGN KEY fk_rpd_kegiatan");
        $this->db->query("ALTER TABLE rencana_penarikan_dana DROP INDEX uq_kegiatan_tahun");

        $this->forge->modifyColumn('rencana_penarikan_dana', [
            'kegiatan_id' => [
                'name'     => 'unit_kerja_id',
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
        ]);

        $this->db->query("ALTER TABLE rencana_penarikan_dana ADD UNIQUE KEY unit_kerja_id (unit_kerja_id, tahun_anggaran)");
        $this->db->query("ALTER TABLE rencana_penarikan_dana ADD CONSTRAINT rencana_penarikan_dana_unit_kerja_id_foreign FOREIGN KEY (unit_kerja_id) REFERENCES unit_kerja(id) ON DELETE RESTRICT");
    }
}
