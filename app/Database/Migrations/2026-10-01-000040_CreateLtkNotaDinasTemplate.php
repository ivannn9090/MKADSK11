<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Template dokumen Nota Dinas yang dapat dikelola Admin.
 * konten_template menyimpan path file .docx template (bukan blob).
 * variabel berisi array JSON daftar placeholder, mis. ["{{JUDUL}}", "{{TANGGAL}}"].
 */
class CreateLtkNotaDinasTemplate extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tipe'             => [
                'type'       => 'ENUM',
                'constraint' => [
                    'nota_dinas_pengajuan',
                    'nota_dinas_tanggapan',
                    'nota_dinas_permintaan_penyesuaian',
                    'nota_dinas_hasil_telaah_akhir',
                ],
                'null' => false,
            ],
            'nama'             => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => false],
            'template_path'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'variabel'         => ['type' => 'JSON', 'null' => true],
            'versi'            => ['type' => 'TINYINT', 'unsigned' => true, 'null' => false, 'default' => 1],
            'is_aktif'         => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            'created_by'       => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'created_at'       => ['type' => 'DATETIME', 'null' => false, 'default' => false],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tipe');
        $this->forge->createTable('ltk_nota_dinas_template', true);

        $this->db->query('ALTER TABLE ltk_nota_dinas_template MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        $this->forge->dropTable('ltk_nota_dinas_template', true);
    }
}
