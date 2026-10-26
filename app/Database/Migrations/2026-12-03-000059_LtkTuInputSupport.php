<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LtkTuInputSupport — Mendukung alur baru di mana TU langsung menginput
 * permohonan berdasarkan NODIN masuk dari SRIKANDI, tanpa registrasi klien.
 *
 * Perubahan:
 *  - klien_user_id  → nullable (permohonan TU tidak punya klien)
 *  - form_template_id → nullable (TU input tidak pakai form template)
 *  - +sumber_permohonan ENUM('klien','tu_input')  — siapa yang buat
 *  - +nama_pengusul   VARCHAR(200)  — nama pejabat/unit pengusul
 *  - +nodin_srikandi  VARCHAR(100)  — nomor NODIN di SRIKANDI
 */
class LtkTuInputSupport extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('ltk_permohonan', [
            'klien_user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'form_template_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
        ]);

        $this->forge->addColumn('ltk_permohonan', [
            'sumber_permohonan' => [
                'type'       => 'ENUM',
                'constraint' => ['klien', 'tu_input'],
                'null'       => false,
                'default'    => 'klien',
                'after'      => 'kode',
            ],
            'nama_pengusul' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => true,
                'default'    => null,
                'after'      => 'unit_kerja',
            ],
            'nodin_srikandi' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
                'after'      => 'nama_pengusul',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('ltk_permohonan', ['sumber_permohonan', 'nama_pengusul', 'nodin_srikandi']);

        $this->forge->modifyColumn('ltk_permohonan', [
            'klien_user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => false,
            ],
            'form_template_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => false,
            ],
        ]);
    }
}
