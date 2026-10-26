<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Tambah kolom bg_path ke biolink_profiles
 * Background digunakan sebagai gambar latar di belakang card pada halaman publik.
 */
class AddBgToBiolinkProfiles extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('biolink_profiles', [
            'bg_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 512,
                'null'       => true,
                'default'    => null,
                'after'      => 'cover_path',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('biolink_profiles', 'bg_path');
    }
}
