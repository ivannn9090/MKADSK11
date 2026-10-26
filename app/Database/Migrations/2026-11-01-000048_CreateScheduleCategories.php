<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScheduleCategories extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `schedule_categories` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `nama`       VARCHAR(100) NOT NULL,
                `kode`       VARCHAR(50)  NOT NULL COMMENT 'internal|rapat|on_budget|off_budget|telaah|libur|lainnya',
                `warna`      VARCHAR(20)  NOT NULL DEFAULT '#3b82f6' COMMENT 'hex color untuk FullCalendar',
                `is_aktif`   TINYINT(1)   NOT NULL DEFAULT 1,
                `created_at` DATETIME     NULL,
                `updated_at` DATETIME     NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_schedule_categories_kode` (`kode`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed kategori default
        $this->db->query("
            INSERT INTO `schedule_categories` (`nama`, `kode`, `warna`, `is_aktif`, `created_at`) VALUES
            ('Kegiatan Internal',  'internal',   '#3b82f6', 1, NOW()),
            ('Rapat',             'rapat',      '#8b5cf6', 1, NOW()),
            ('Kegiatan On Budget','on_budget',  '#10b981', 1, NOW()),
            ('Kegiatan Off Budget','off_budget', '#f59e0b', 1, NOW()),
            ('Telaah Konsep',     'telaah',     '#ef4444', 1, NOW()),
            ('Hari Libur',        'libur',      '#9ca3af', 1, NOW()),
            ('Lainnya',           'lainnya',    '#6366f1', 1, NOW())
        ");
    }

    public function down(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `schedule_categories`");
    }
}
