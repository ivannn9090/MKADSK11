<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ubah kolom RPD dari per-triwulan (tw1..tw4) menjadi per-bulan (bln_01..bln_12).
 * Setiap baris RPD menyimpan rencana penarikan dana per bulan dalam satu tahun anggaran.
 */
class RpdGantiTwKeBulanan extends Migration
{
    private array $bulanKolom = [
        'bln_01','bln_02','bln_03','bln_04','bln_05','bln_06',
        'bln_07','bln_08','bln_09','bln_10','bln_11','bln_12',
    ];

    public function up(): void
    {
        // Hapus kolom tw1..tw4
        $this->forge->dropColumn('rencana_penarikan_dana', ['tw1','tw2','tw3','tw4']);

        // Tambah 12 kolom bln_01..bln_12
        $fields = [];
        foreach ($this->bulanKolom as $kol) {
            $fields[$kol] = ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'null' => false, 'after' => 'pagu_anggaran'];
        }
        $this->forge->addColumn('rencana_penarikan_dana', $fields);
    }

    public function down(): void
    {
        $this->forge->dropColumn('rencana_penarikan_dana', $this->bulanKolom);

        $this->forge->addColumn('rencana_penarikan_dana', [
            'tw1' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'pagu_anggaran'],
            'tw2' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'tw1'],
            'tw3' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'tw2'],
            'tw4' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'tw3'],
        ]);
    }
}
