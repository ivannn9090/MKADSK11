<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Buat tabel ro (Rumpun Output) dan tambah ro_id ke tabel kegiatan.
 *
 * RO adalah pengelompokan kegiatan on-budget satu level di atas kegiatan.
 * Bersifat opsional — kegiatan yang tidak memiliki ro_id dianggap standalone.
 */
class CreateRoTable extends Migration
{
    public function up(): void
    {
        // ── Tabel ro ──────────────────────────────────────────────────
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nama_ro' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => false,
            ],
            'kode_ro' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => null,
            ],
            'deskripsi' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'tahun' => [
                'type' => 'YEAR',
                'null' => true,
            ],
            'team_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('ro');
    }

    public function down(): void
    {
        $this->forge->dropTable('ro', true);
    }
}
