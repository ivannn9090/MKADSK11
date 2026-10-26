<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel rincian_anggaran — komponen biaya penyusun pagu suatu kegiatan.
 *
 * Setiap baris = satu item/komponen biaya, misalnya:
 *   - "Rapat 25 orang x 4 kali x Rp 81.000"  => qty=25, volume=4, satuan="OH", harga=81000
 *   - "Narasumber 2 orang x 1 hari x Rp 1.500.000"
 *   - "Pembelian buku 50 eksemplar x Rp 150.000"
 *
 * Formula subtotal: qty * volume * harga_satuan
 * Pagu kegiatan = SUM(subtotal) semua rincian (auto-sync).
 */
class CreateRincianAnggaranTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'kegiatan_id'  => ['type' => 'INT', 'unsigned' => true],
            'urutan'       => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            // Komponen / nama item
            'komponen'     => ['type' => 'VARCHAR', 'constraint' => 300],
            // Uraian bebas (opsional) — catatan tambahan
            'uraian'       => ['type' => 'TEXT', 'null' => true],
            // Qty: jumlah orang / unit / eksemplar / dll
            'qty'          => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 1],
            'satuan_qty'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],   // "Orang", "Unit", "Eks", dll
            // Volume: berapa kali / hari / kegiatan / dll (boleh null = tidak dipakai)
            'volume'       => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 1],
            'satuan_vol'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],   // "Kali", "Hari", "Kegiatan", dll
            // Harga per satuan
            'harga_satuan' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            // subtotal = qty * volume * harga_satuan (disimpan agar tidak selalu dihitung)
            'subtotal'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'created_by'   => ['type' => 'INT', 'unsigned' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('kegiatan_id');
        $this->forge->addForeignKey('kegiatan_id', 'kegiatan', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('rincian_anggaran');
    }

    public function down(): void
    {
        $this->forge->dropTable('rincian_anggaran', true);
    }
}
