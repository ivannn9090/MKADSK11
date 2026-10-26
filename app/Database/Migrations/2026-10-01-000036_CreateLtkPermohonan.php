<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Tabel utama permohonan telaah.
 * Menyimpan seluruh status workflow, data SLA, dan referensi PIC.
 */
class CreateLtkPermohonan extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                       => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'kode'                     => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false, 'unique' => true],
            'klien_user_id'            => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'unit_kerja'               => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'judul'                    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => false],
            'deskripsi'                => ['type' => 'TEXT', 'null' => true],
            'form_template_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'status'                   => [
                'type'       => 'ENUM',
                'constraint' => [
                    'draft',
                    'menunggu_srikandi',
                    'menunggu_verifikasi_tu',
                    'terverifikasi',
                    'masuk_antrian',
                    'ditugaskan_pic',
                    'diterima_pic',
                    'asesmen_awal',
                    'tidak_perlu_tindak_lanjut',
                    'penyusunan_policy_brief',
                    'penyusunan_nota_dinas_tanggapan',
                    'perlu_tindak_lanjut',
                    'nota_dinas_permintaan_penyesuaian',
                    'kajian_lanjutan',
                    'penyusunan_policy_paper',
                    'nota_dinas_hasil_telaah_akhir',
                    'selesai',
                    'ditutup',
                ],
                'null'       => false,
                'default'    => 'draft',
            ],
            'skenario'                 => ['type' => 'ENUM', 'constraint' => ['A','B'], 'null' => true],
            'jenis_antrian'            => ['type' => 'ENUM', 'constraint' => ['regular','prioritas'], 'null' => true, 'default' => 'regular'],
            'nomor_nota_dinas_masuk'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tgl_nota_dinas_masuk'     => ['type' => 'DATE', 'null' => true],
            'pic_user_id'              => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'tgl_penugasan'            => ['type' => 'DATETIME', 'null' => true],
            'tgl_terima_pic'           => ['type' => 'DATETIME', 'null' => true],
            'sla_hari_kerja'           => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true],
            'tgl_deadline'             => ['type' => 'DATE', 'null' => true],
            'tgl_selesai'              => ['type' => 'DATETIME', 'null' => true],
            'is_sla_breach'            => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'catatan_tu'               => ['type' => 'TEXT', 'null' => true],
            'created_by'               => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'updated_by'               => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'               => ['type' => 'DATETIME', 'null' => false, 'default' => false],
            'updated_at'               => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'               => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('klien_user_id');
        $this->forge->addKey('pic_user_id');
        $this->forge->addKey('status');
        $this->forge->addKey('is_sla_breach');
        $this->forge->createTable('ltk_permohonan', true);

        $this->db->query('ALTER TABLE ltk_permohonan MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ltk_permohonan ADD CONSTRAINT fk_lp_klien FOREIGN KEY (klien_user_id) REFERENCES users(id)');
        $this->db->query('ALTER TABLE ltk_permohonan ADD CONSTRAINT fk_lp_template FOREIGN KEY (form_template_id) REFERENCES ltk_form_template(id)');
        $this->db->query('ALTER TABLE ltk_permohonan ADD CONSTRAINT fk_lp_pic FOREIGN KEY (pic_user_id) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE ltk_permohonan DROP FOREIGN KEY fk_lp_klien');
        $this->db->query('ALTER TABLE ltk_permohonan DROP FOREIGN KEY fk_lp_template');
        $this->db->query('ALTER TABLE ltk_permohonan DROP FOREIGN KEY fk_lp_pic');
        $this->forge->dropTable('ltk_permohonan', true);
    }
}
