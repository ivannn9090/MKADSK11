<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTuRole extends Migration
{
    public function up(): void
    {
        $this->db->query("
            ALTER TABLE users
            MODIFY COLUMN role ENUM(
                'direktur','ketua_tim','anggota_tim','perencana_anggaran','admin','tu'
            ) NOT NULL DEFAULT 'anggota_tim'
        ");
    }

    public function down(): void
    {
        $this->db->query("
            ALTER TABLE users
            MODIFY COLUMN role ENUM(
                'direktur','ketua_tim','anggota_tim','perencana_anggaran','admin'
            ) NOT NULL DEFAULT 'anggota_tim'
        ");
    }
}
