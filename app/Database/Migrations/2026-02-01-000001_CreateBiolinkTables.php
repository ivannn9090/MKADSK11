<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Biolink / Multi-link Builder
 *
 * Membuat dua tabel:
 *  - biolink_profiles : data header (avatar, nama, bio, sosmed)
 *  - biolink_items    : daftar tombol / tautan yang ditampilkan
 *
 * Setiap item dapat dihubungkan ke short_links (URL shortener yang sudah ada)
 * sehingga klik tercatat secara otomatis via sistem redirect yang telah berjalan.
 */
class CreateBiolinkTables extends Migration
{
    public function up(): void
    {
        // ── biolink_profiles ────────────────────────────────────────────────
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // Hubungan ke tabel users (owner profil)
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
            // Slug unik untuk URL publik: /biolink/<slug>
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'nama' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'bio' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            // Path relatif dari writable/uploads/biolink/
            'foto_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 512,
                'null'       => true,
            ],
            // Warna aksen tombol (hex, misal #1a6fb5)
            'warna_aksen' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => '#1a6fb5',
            ],
            // JSON array sosmed: [{"platform":"instagram","url":"..."}]
            'sosmed_json' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'is_active' => [
                'type'    => 'TINYINT',
                'null'    => false,
                'default' => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('biolink_profiles');

        // ── biolink_items ────────────────────────────────────────────────────
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'profile_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
            // Opsional: tautkan ke short_links.id agar tracking otomatis aktif
            'short_link_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'judul' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            // URL tujuan langsung (dipakai jika short_link_id NULL)
            'url_tujuan' => [
                'type'       => 'VARCHAR',
                'constraint' => 2048,
                'null'       => false,
            ],
            // Ikon: nama emoji atau nama icon set (misal "globe", "mail")
            'ikon' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
            'urutan' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
                'null'     => false,
                'default'  => 0,
            ],
            'is_active' => [
                'type'    => 'TINYINT',
                'null'    => false,
                'default' => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('profile_id');
        $this->forge->addKey('short_link_id');
        $this->forge->addForeignKey('profile_id', 'biolink_profiles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('short_link_id', 'short_links', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('biolink_items');
    }

    public function down(): void
    {
        $this->forge->dropTable('biolink_items', true);
        $this->forge->dropTable('biolink_profiles', true);
    }
}
