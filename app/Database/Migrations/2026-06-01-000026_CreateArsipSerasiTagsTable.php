<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * SERASI — Tabel arsip_serasi_tags (pivot)
 * Relasi many-to-many antara arsip_serasi dan serasi_tags.
 */
class CreateArsipSerasiTagsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'arsip_id'  => ['type' => 'INT', 'unsigned' => true],
            'tag_id'    => ['type' => 'INT', 'unsigned' => true],
            'created_at'=> ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['arsip_id', 'tag_id']);
        $this->forge->addForeignKey('arsip_id', 'arsip_serasi',  'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tag_id',   'serasi_tags',   'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('arsip_serasi_tags');
    }

    public function down(): void
    {
        $this->forge->dropTable('arsip_serasi_tags', true);
    }
}
