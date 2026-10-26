<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Jawaban setiap field form untuk satu permohonan.
 * nilai_text menyimpan teks / nilai; nilai_file_path untuk tipe file/pdf.
 */
class CreateLtkJawabanField extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'permohonan_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'form_field_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'nilai_text'      => ['type' => 'TEXT', 'null' => true],
            'nilai_file_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => false, 'default' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('permohonan_id');
        $this->forge->createTable('ltk_jawaban_field', true);

        $this->db->query('ALTER TABLE ltk_jawaban_field MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ltk_jawaban_field ADD CONSTRAINT fk_ljf_permohonan FOREIGN KEY (permohonan_id) REFERENCES ltk_permohonan(id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE ltk_jawaban_field ADD CONSTRAINT fk_ljf_field FOREIGN KEY (form_field_id) REFERENCES ltk_form_field(id)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE ltk_jawaban_field DROP FOREIGN KEY fk_ljf_permohonan');
        $this->db->query('ALTER TABLE ltk_jawaban_field DROP FOREIGN KEY fk_ljf_field');
        $this->forge->dropTable('ltk_jawaban_field', true);
    }
}
