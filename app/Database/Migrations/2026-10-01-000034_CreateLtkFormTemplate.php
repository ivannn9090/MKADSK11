<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Template formulir pengajuan telaah.
 * TU dapat membuat banyak template; satu template aktif digunakan per pengajuan.
 */
class CreateLtkFormTemplate extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nama'        => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => false],
            'deskripsi'   => ['type' => 'TEXT', 'null' => true],
            'is_aktif'    => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            'created_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'created_at'  => ['type' => 'DATETIME', 'null' => false, 'default' => false],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('ltk_form_template', true);

        $this->db->query('ALTER TABLE ltk_form_template MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        $this->forge->dropTable('ltk_form_template', true);
    }
}
