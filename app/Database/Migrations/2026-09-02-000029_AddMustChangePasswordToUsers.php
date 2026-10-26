<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMustChangePasswordToUsers extends Migration
{
    public function up(): void
    {
        $this->db->query("
            ALTER TABLE users
            ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0
            AFTER status
        ");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE users DROP COLUMN must_change_password");
    }
}
