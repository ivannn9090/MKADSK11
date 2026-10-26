<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * SERASI — Tabel arsip_serasi
 * Metadata arsip yang terindeks dari OneDrive.
 * File asli tetap tersimpan di OneDrive; tabel ini hanya menyimpan metadata.
 */
class CreateArsipSerasiTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'onedrive_file_id'=> ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'comment' => 'Microsoft Graph driveItem ID'],
            'nama_file'       => ['type' => 'VARCHAR', 'constraint' => 500],
            'path_folder'     => ['type' => 'VARCHAR', 'constraint' => 1000, 'comment' => 'Path folder di OneDrive'],
            'folder_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'comment' => 'FK → serasi_folders.id'],
            'jenis_dokumen'   => [
                'type'       => 'ENUM',
                'constraint' => [
                    'Policy Brief','Policy Paper','Policy Memo','Telaah','Kajian Analisis',
                    'Draf Kajian','Rancangan Akhir Kajian','Notula','Daftar Hadir',
                    'POK','KAK','Laporan','SK Tim','Lainnya',
                ],
                'default'    => 'Lainnya',
            ],
            'tahun'           => ['type' => 'YEAR', 'null' => true],
            'tim_penyusun'    => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'kata_kunci'      => ['type' => 'TEXT', 'null' => true],
            'link_onedrive'   => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'tanggal_dibuat'  => ['type' => 'DATETIME', 'null' => true, 'comment' => 'Tanggal file dibuat di OneDrive'],
            'tanggal_diubah'  => ['type' => 'DATETIME', 'null' => true, 'comment' => 'Tanggal file terakhir diubah di OneDrive'],
            'status'          => ['type' => 'ENUM', 'constraint' => ['aktif','arsip','dihapus'], 'default' => 'aktif'],
            'created_by'      => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'comment' => 'FK → users.id'],
            'updated_by'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('folder_id');
        $this->forge->addKey('jenis_dokumen');
        $this->forge->addKey('tahun');
        $this->forge->addKey('onedrive_file_id');
        $this->forge->createTable('arsip_serasi');
    }

    public function down(): void
    {
        $this->forge->dropTable('arsip_serasi', true);
    }
}
