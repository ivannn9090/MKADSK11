<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel rencana_penarikan_dana (RPD)
 * Menyimpan rencana penarikan per unit kerja per triwulan per tahun anggaran.
 * Kolom tw1..tw4 berisi rencana nominal masing-masing triwulan.
 */
class CreateRencanaPenarikanDanaTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unit_kerja_id'  => ['type' => 'INT', 'unsigned' => true],
            'tahun_anggaran' => ['type' => 'SMALLINT', 'unsigned' => true],
            'pagu_anggaran'  => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'tw1'            => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'comment' => 'Jan-Mar'],
            'tw2'            => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'comment' => 'Apr-Jun'],
            'tw3'            => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'comment' => 'Jul-Sep'],
            'tw4'            => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'comment' => 'Okt-Des'],
            'keterangan'     => ['type' => 'TEXT', 'null' => true],
            'created_by'     => ['type' => 'INT', 'unsigned' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['unit_kerja_id', 'tahun_anggaran']);
        $this->forge->addKey('tahun_anggaran');
        $this->forge->addForeignKey('unit_kerja_id', 'unit_kerja', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('rencana_penarikan_dana');
    }

    public function down(): void
    {
        $this->forge->dropTable('rencana_penarikan_dana', true);
    }
}
