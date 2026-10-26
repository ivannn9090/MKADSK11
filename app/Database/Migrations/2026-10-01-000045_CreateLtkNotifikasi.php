<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Notifikasi in-app khusus modul Layanan Telaah Konsep.
 * Terpisah dari tabel notifikasi utama agar tidak mencemari modul lain.
 */
class CreateLtkNotifikasi extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'permohonan_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'tipe'           => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'judul'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'pesan'          => ['type' => 'TEXT', 'null' => false],
            'is_read'        => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => false, 'default' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey(['user_id', 'is_read'], false, false, 'idx_lnotif_user_read');
        $this->forge->createTable('ltk_notifikasi', true);

        $this->db->query('ALTER TABLE ltk_notifikasi MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ltk_notifikasi ADD CONSTRAINT fk_ln_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE ltk_notifikasi ADD CONSTRAINT fk_ln_permohonan FOREIGN KEY (permohonan_id) REFERENCES ltk_permohonan(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE ltk_notifikasi DROP FOREIGN KEY fk_ln_user');
        $this->db->query('ALTER TABLE ltk_notifikasi DROP FOREIGN KEY fk_ln_permohonan');
        $this->forge->dropTable('ltk_notifikasi', true);
    }
}
