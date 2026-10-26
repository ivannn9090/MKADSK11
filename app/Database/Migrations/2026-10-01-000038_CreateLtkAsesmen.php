<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Hasil asesmen awal PIC beserta pilihan skenario (A atau B).
 */
class CreateLtkAsesmen extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'permohonan_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'pic_user_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'skenario'        => ['type' => 'ENUM', 'constraint' => ['A','B'], 'null' => false],
            'catatan_asesmen' => ['type' => 'TEXT', 'null' => true],
            'tgl_asesmen'     => ['type' => 'DATETIME', 'null' => false, 'default' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('permohonan_id');
        $this->forge->createTable('ltk_asesmen', true);

        $this->db->query('ALTER TABLE ltk_asesmen MODIFY tgl_asesmen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ltk_asesmen ADD CONSTRAINT fk_la_permohonan FOREIGN KEY (permohonan_id) REFERENCES ltk_permohonan(id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE ltk_asesmen ADD CONSTRAINT fk_la_pic FOREIGN KEY (pic_user_id) REFERENCES users(id)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE ltk_asesmen DROP FOREIGN KEY fk_la_permohonan');
        $this->db->query('ALTER TABLE ltk_asesmen DROP FOREIGN KEY fk_la_pic');
        $this->forge->dropTable('ltk_asesmen', true);
    }
}
