<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Penyesuaian desain:
 *
 * 1. ltk_dokumen_output:
 *    - Hapus kolom upload_path (klien tidak upload file)
 *    - Tambah link_cloud  : URL OneDrive/Google Drive dari Klien
 *    - Tambah keterangan_link : label/keterangan link (opsional)
 *
 * 2. ltk_jawaban_field:
 *    - Tambah nilai_link : URL link cloud yang diinput klien
 *
 * 3. ltk_form_field:
 *    - Ganti tipe ENUM: hapus 'file' dan 'pdf', tambah 'link_cloud'
 *      (tipe baru = text | textarea | number | date | dropdown |
 *                   radio | checkbox | link_cloud)
 */
class LtkAdjustLinkCloudAndNdTemplate extends Migration
{
    public function up(): void
    {
        // ── 1. ltk_dokumen_output ──────────────────────────────────
        $this->forge->dropColumn('ltk_dokumen_output', 'upload_path');

        $this->forge->addColumn('ltk_dokumen_output', [
            'link_cloud' => [
                'type'       => 'VARCHAR',
                'constraint' => 2000,
                'null'       => true,
                'after'      => 'file_pdf_path',
            ],
            'keterangan_link' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'link_cloud',
            ],
        ]);

        // ── 2. ltk_jawaban_field ───────────────────────────────────
        // Hapus kolom nilai_file_path (tidak dipakai), tambah nilai_link
        $this->forge->dropColumn('ltk_jawaban_field', 'nilai_file_path');

        $this->forge->addColumn('ltk_jawaban_field', [
            'nilai_link' => [
                'type'       => 'VARCHAR',
                'constraint' => 2000,
                'null'       => true,
                'after'      => 'nilai_text',
            ],
        ]);

        // ── 3. ltk_form_field — ubah ENUM tipe ────────────────────
        // MySQL ALTER COLUMN untuk mengubah definisi ENUM
        $this->db->query("
            ALTER TABLE ltk_form_field
            MODIFY COLUMN tipe
            ENUM('text','textarea','number','date','dropdown','radio','checkbox','link_cloud')
            NOT NULL
        ");
    }

    public function down(): void
    {
        // Kembalikan ltk_dokumen_output
        $this->forge->dropColumn('ltk_dokumen_output', 'link_cloud');
        $this->forge->dropColumn('ltk_dokumen_output', 'keterangan_link');
        $this->forge->addColumn('ltk_dokumen_output', [
            'upload_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'after'      => 'upload_manual',
            ],
        ]);

        // Kembalikan ltk_jawaban_field
        $this->forge->dropColumn('ltk_jawaban_field', 'nilai_link');
        $this->forge->addColumn('ltk_jawaban_field', [
            'nilai_file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'after'      => 'nilai_text',
            ],
        ]);

        // Kembalikan ltk_form_field ENUM ke semula
        $this->db->query("
            ALTER TABLE ltk_form_field
            MODIFY COLUMN tipe
            ENUM('text','textarea','number','date','dropdown','radio','checkbox','file','pdf')
            NOT NULL
        ");
    }
}
