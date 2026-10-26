<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambahkan kolom google_auth_state ke google_calendar_settings
 * untuk menyimpan OAuth2 state parameter (CSRF protection).
 */
class AddOauthStateToGoogleSettings extends Migration
{
    public function up(): void
    {
        $this->db->query("
            ALTER TABLE `google_calendar_settings`
            ADD COLUMN `oauth_state` VARCHAR(255) NULL COMMENT 'OAuth2 state untuk CSRF' AFTER `refresh_token`
        ");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE `google_calendar_settings` DROP COLUMN IF EXISTS `oauth_state`");
    }
}
