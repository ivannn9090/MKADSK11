<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGoogleCalendarSettings extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `google_calendar_settings` (
                `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `nama`            VARCHAR(200) NOT NULL DEFAULT 'Default',
                `client_id`       TEXT         NULL,
                `client_secret`   TEXT         NULL,
                `project_id`      VARCHAR(255) NULL,
                `calendar_id`     VARCHAR(255) NULL COMMENT 'primary | email@group.calendar.google.com',
                `redirect_url`    VARCHAR(500) NULL,
                `access_token`    TEXT         NULL COMMENT 'JSON encoded token object',
                `refresh_token`   TEXT         NULL,
                `token_expires`   DATETIME     NULL,
                `is_connected`    TINYINT(1)   NOT NULL DEFAULT 0,
                `connected_email` VARCHAR(200) NULL,
                `is_default`      TINYINT(1)   NOT NULL DEFAULT 0,

                -- Reminder defaults (menit)
                `reminder_h7`     INT          NOT NULL DEFAULT 10080 COMMENT 'H-7 = 7*24*60',
                `reminder_h3`     INT          NOT NULL DEFAULT 4320  COMMENT 'H-3 = 3*24*60',
                `reminder_h1`     INT          NOT NULL DEFAULT 1440  COMMENT 'H-1 = 1*24*60',
                `reminder_aktif`  TINYINT(1)   NOT NULL DEFAULT 1,

                -- Scope per unit / pic
                `scope_type`      ENUM('global','unit_kerja','pic') NOT NULL DEFAULT 'global',
                `scope_value`     VARCHAR(200) NULL,

                `created_by`      INT UNSIGNED NULL,
                `updated_by`      INT UNSIGNED NULL,
                `created_at`      DATETIME     NULL,
                `updated_at`      DATETIME     NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Baris konfigurasi default
        $this->db->query("
            INSERT INTO `google_calendar_settings` (`nama`,`is_default`,`created_at`)
            VALUES ('Default Calendar','1', NOW())
        ");
    }

    public function down(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `google_calendar_settings`");
    }
}
