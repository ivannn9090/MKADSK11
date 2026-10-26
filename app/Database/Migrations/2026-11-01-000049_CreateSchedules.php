<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSchedules extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `schedules` (
                `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `kode`                VARCHAR(30)  NOT NULL COMMENT 'SCH-2026-0001',
                `judul`               VARCHAR(500) NOT NULL,
                `deskripsi`           TEXT         NULL,
                `category_id`         INT UNSIGNED NOT NULL,
                `lokasi`              VARCHAR(300) NULL,
                `tgl_mulai`           DATE         NOT NULL,
                `jam_mulai`           TIME         NULL,
                `tgl_selesai`         DATE         NOT NULL,
                `jam_selesai`         TIME         NULL,
                `all_day`             TINYINT(1)   NOT NULL DEFAULT 0,
                `warna`               VARCHAR(20)  NULL COMMENT 'Override warna dari kategori',

                -- PIC & Peserta
                `pic_user_id`         INT UNSIGNED NULL,
                `created_by`          INT UNSIGNED NOT NULL,
                `updated_by`          INT UNSIGNED NULL,

                -- Google Calendar
                `sync_google`         TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Sinkronkan ke Google Calendar',
                `google_event_id`     VARCHAR(255) NULL COMMENT 'ID event di Google Calendar',
                `google_calendar_id`  VARCHAR(255) NULL COMMENT 'Calendar ID tujuan sinkronisasi',
                `google_sync_status`  ENUM('synced','pending','failed','not_synced') NOT NULL DEFAULT 'not_synced',
                `google_synced_at`    DATETIME     NULL,

                -- Referensi modul lain
                `ref_type`            VARCHAR(50)  NULL COMMENT 'ltk_permohonan|kegiatan|null',
                `ref_id`              INT UNSIGNED NULL COMMENT 'ID record yang direferensikan',

                `is_aktif`            TINYINT(1)   NOT NULL DEFAULT 1,
                `created_at`          DATETIME     NULL,
                `updated_at`          DATETIME     NULL,
                `deleted_at`          DATETIME     NULL,

                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_schedules_kode` (`kode`),
                KEY `idx_schedules_tgl` (`tgl_mulai`, `tgl_selesai`),
                KEY `idx_schedules_pic` (`pic_user_id`),
                KEY `idx_schedules_category` (`category_id`),
                KEY `idx_schedules_google_event` (`google_event_id`),
                KEY `idx_schedules_ref` (`ref_type`, `ref_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `schedules`");
    }
}
