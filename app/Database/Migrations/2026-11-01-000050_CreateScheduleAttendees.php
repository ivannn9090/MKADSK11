<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScheduleAttendees extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `schedule_attendees` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `schedule_id` INT UNSIGNED NOT NULL,
                `user_id`     INT UNSIGNED NULL  COMMENT 'NULL jika peserta eksternal',
                `nama`        VARCHAR(200) NULL  COMMENT 'Nama peserta eksternal',
                `email`       VARCHAR(200) NULL,
                `status_rsvp` ENUM('undangan','hadir','tidak_hadir','tentatif') NOT NULL DEFAULT 'undangan',
                `created_at`  DATETIME     NULL,
                PRIMARY KEY (`id`),
                KEY `idx_attendees_schedule` (`schedule_id`),
                KEY `idx_attendees_user`     (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `schedule_attendees`");
    }
}
