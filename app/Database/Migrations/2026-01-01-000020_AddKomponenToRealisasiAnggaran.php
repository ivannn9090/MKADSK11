<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah kolom rincian_anggaran_id dan volume_dipakai ke realisasi_anggaran.
 *
 * rincian_anggaran_id  → FK ke rincian_anggaran (komponen biaya yang dipakai)
 * volume_dipakai       → berapa volume (orang/unit) yang dikonsumsi pada realisasi ini
 *
 * Keduanya nullable agar realisasi lama (tanpa komponen) tetap valid.
 */
class AddKomponenToRealisasiAnggaran extends Migration
{
    public function up(): void
    {
        // Kolom sumber_ob_id & ref_realisasi_id sudah ada dari migration sebelumnya.
        // Kita hanya tambah dua kolom baru.
        $this->forge->addColumn('realisasi_anggaran', [
            'rincian_anggaran_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'created_by',
            ],
            'volume_dipakai' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => true,
                'default'    => null,
                'after'      => 'rincian_anggaran_id',
            ],
        ]);

        // FK — opsional: jika rincian dihapus, set null agar realisasi tidak ikut hilang
        $this->db->query('
            ALTER TABLE realisasi_anggaran
            ADD CONSTRAINT fk_realisasi_rincian
            FOREIGN KEY (rincian_anggaran_id) REFERENCES rincian_anggaran(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE realisasi_anggaran DROP FOREIGN KEY fk_realisasi_rincian');
        $this->forge->dropColumn('realisasi_anggaran', ['rincian_anggaran_id', 'volume_dipakai']);
    }
}
