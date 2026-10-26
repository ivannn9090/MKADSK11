<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Profil tambahan untuk akun Klien.
 * Menyimpan unit kerja, jabatan, dan status verifikasi oleh TU.
 */
class CreateLtkKlienProfil extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'unit_kerja'   => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => false],
            'jabatan'      => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'no_telepon'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'is_verified'  => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'verified_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'verified_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => false, 'default' => false],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->createTable('ltk_klien_profil', true);

        $this->db->query('ALTER TABLE ltk_klien_profil MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ltk_klien_profil ADD CONSTRAINT fk_lkp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE ltk_klien_profil ADD CONSTRAINT fk_lkp_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE ltk_klien_profil DROP FOREIGN KEY fk_lkp_user');
        $this->db->query('ALTER TABLE ltk_klien_profil DROP FOREIGN KEY fk_lkp_verifier');
        $this->forge->dropTable('ltk_klien_profil', true);
    }
}
