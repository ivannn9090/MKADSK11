<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * SERASI — Tabel serasi_tags
 * Menyimpan daftar tag yang digunakan untuk menandai arsip.
 */
class CreateSerasiTagsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'warna'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'comment' => 'Hex color untuk tampilan badge'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nama');
        $this->forge->createTable('serasi_tags');
    }

    public function down(): void
    {
        $this->forge->dropTable('serasi_tags', true);
    }
}
