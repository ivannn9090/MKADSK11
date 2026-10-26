<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScheduleReminders extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `schedule_reminders` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `schedule_id` INT UNSIGNED NOT NULL,
                `menit`       INT          NOT NULL COMMENT 'Menit sebelum event — H-7=10080, H-3=4320, H-1=1440',
                `metode`      ENUM('email','popup','notification') NOT NULL DEFAULT 'popup',
                PRIMARY KEY (`id`),
                KEY `idx_reminders_schedule` (`schedule_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `schedule_reminders`");
    }
}
