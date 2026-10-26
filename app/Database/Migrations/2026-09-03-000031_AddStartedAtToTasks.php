<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStartedAtToTasks extends Migration
{
    public function up(): void
    {
        $this->db->query("
            ALTER TABLE tasks
            ADD COLUMN started_at DATETIME NULL DEFAULT NULL
            AFTER target_date
        ");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE tasks DROP COLUMN started_at");
    }
}
