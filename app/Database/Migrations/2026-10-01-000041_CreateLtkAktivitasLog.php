<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Log aktivitas/timeline setiap permohonan.
 * Append-only; tidak pernah di-update atau dihapus.
 */
class CreateLtkAktivitasLog extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'permohonan_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'status_dari'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status_ke'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'label_aktivitas'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'catatan'          => ['type' => 'TEXT', 'null' => true],
            'dilakukan_oleh'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => false, 'default' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('permohonan_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('ltk_aktivitas_log', true);

        $this->db->query('ALTER TABLE ltk_aktivitas_log MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ltk_aktivitas_log ADD CONSTRAINT fk_lal_permohonan FOREIGN KEY (permohonan_id) REFERENCES ltk_permohonan(id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE ltk_aktivitas_log ADD CONSTRAINT fk_lal_user FOREIGN KEY (dilakukan_oleh) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE ltk_aktivitas_log DROP FOREIGN KEY fk_lal_permohonan');
        $this->db->query('ALTER TABLE ltk_aktivitas_log DROP FOREIGN KEY fk_lal_user');
        $this->forge->dropTable('ltk_aktivitas_log', true);
    }
}
