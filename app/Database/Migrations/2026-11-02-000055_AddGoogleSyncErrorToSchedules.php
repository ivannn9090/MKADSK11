<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambahkan kolom google_sync_error ke tabel schedules.
 * Kolom ini menyimpan pesan error terakhir saat sinkronisasi Google Calendar gagal.
 * Dikosongkan kembali saat sinkronisasi berhasil.
 */
class AddGoogleSyncErrorToSchedules extends Migration
{
    public function up(): void
    {
        $this->db->query("
            ALTER TABLE `schedules`
            ADD COLUMN `google_sync_error` TEXT NULL
                COMMENT 'Pesan error terakhir sinkronisasi Google Calendar'
                AFTER `google_synced_at`
        ");
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE `schedules` DROP COLUMN IF EXISTS `google_sync_error`");
    }
}
