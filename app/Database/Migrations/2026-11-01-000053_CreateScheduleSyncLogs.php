<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScheduleSyncLogs extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `schedule_sync_logs` (
                `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `schedule_id`    INT UNSIGNED NULL,
                `aksi`           ENUM('create','update','delete','resync','connect','disconnect') NOT NULL,
                `status`         ENUM('success','failed','skipped') NOT NULL,
                `google_event_id`VARCHAR(255) NULL,
                `pesan`          TEXT         NULL,
                `request_body`   TEXT         NULL COMMENT 'JSON payload ke Google API',
                `response_body`  TEXT         NULL COMMENT 'JSON response dari Google API',
                `dilakukan_oleh` INT UNSIGNED NULL,
                `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_sync_logs_schedule` (`schedule_id`),
                KEY `idx_sync_logs_status`   (`status`),
                KEY `idx_sync_logs_created`  (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `schedule_sync_logs`");
    }
}
