<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Tambah kolom cover_path ke biolink_profiles
 * Cover digunakan sebagai gambar banner di halaman publik biolink.
 */
class AddCoverToBiolinkProfiles extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('biolink_profiles', [
            'cover_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 512,
                'null'       => true,
                'default'    => null,
                'after'      => 'foto_path',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('biolink_profiles', 'cover_path');
    }
}
