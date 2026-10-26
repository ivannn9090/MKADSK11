<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Dokumen output yang dihasilkan dalam proses telaah.
 * Mencakup Nota Dinas (generate otomatis) dan Policy Brief/Paper (upload manual).
 */
class CreateLtkDokumenOutput extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'permohonan_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'tipe_dokumen'      => [
                'type'       => 'ENUM',
                'constraint' => [
                    'nota_dinas_pengajuan',
                    'nota_dinas_tanggapan',
                    'nota_dinas_permintaan_penyesuaian',
                    'nota_dinas_hasil_telaah_akhir',
                    'policy_brief',
                    'policy_paper',
                ],
                'null' => false,
            ],
            'nomor_dokumen'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tgl_dokumen'       => ['type' => 'DATE', 'null' => true],
            'file_docx_path'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'file_pdf_path'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'upload_manual'     => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'upload_path'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'digenerate_oleh'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => false, 'default' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('permohonan_id');
        $this->forge->createTable('ltk_dokumen_output', true);

        $this->db->query('ALTER TABLE ltk_dokumen_output MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ltk_dokumen_output ADD CONSTRAINT fk_ldo_permohonan FOREIGN KEY (permohonan_id) REFERENCES ltk_permohonan(id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE ltk_dokumen_output ADD CONSTRAINT fk_ldo_user FOREIGN KEY (digenerate_oleh) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE ltk_dokumen_output DROP FOREIGN KEY fk_ldo_permohonan');
        $this->db->query('ALTER TABLE ltk_dokumen_output DROP FOREIGN KEY fk_ldo_user');
        $this->forge->dropTable('ltk_dokumen_output', true);
    }
}
