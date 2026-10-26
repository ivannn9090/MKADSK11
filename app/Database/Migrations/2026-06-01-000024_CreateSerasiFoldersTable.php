<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * SERASI — Tabel serasi_folders
 * Menyimpan struktur folder OneDrive (hasil sinkronisasi via Microsoft Graph API).
 */
class CreateSerasiFoldersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'onedrive_id'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'comment' => 'Microsoft Graph driveItem ID'],
            'nama'           => ['type' => 'VARCHAR', 'constraint' => 300],
            'path'           => ['type' => 'VARCHAR', 'constraint' => 1000, 'comment' => 'Path lengkap dari root OneDrive'],
            'parent_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'jumlah_dokumen' => ['type' => 'INT', 'default' => 0],
            'ukuran_total'   => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0, 'comment' => 'Ukuran dalam bytes'],
            'synced_at'      => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('parent_id');
        $this->forge->addKey('onedrive_id');
        $this->forge->createTable('serasi_folders');
    }

    public function down(): void
    {
        $this->forge->dropTable('serasi_folders', true);
    }
}
