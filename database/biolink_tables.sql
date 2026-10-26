-- ============================================================
-- Biolink / Multi-link Builder — DDL Manual
-- Jalankan di MySQL/MariaDB setelah tabel `users` dan
-- `short_links` sudah ada.
-- ============================================================

-- ── 1. biolink_profiles ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `biolink_profiles` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED     NOT NULL,
  -- Slug unik untuk URL publik: /biolink/<slug>
  `slug`         VARCHAR(80)      NOT NULL,
  `nama`         VARCHAR(255)     NOT NULL,
  `bio`          TEXT             DEFAULT NULL,
  -- Path relatif di writable/uploads/biolink/
  `foto_path`    VARCHAR(512)     DEFAULT NULL,
  -- Warna tombol dalam format hex, misal #1a6fb5
  `warna_aksen`  VARCHAR(20)      NOT NULL DEFAULT '#1a6fb5',
  -- JSON array: [{"platform":"instagram","url":"https://..."}]
  `sosmed_json`  JSON             DEFAULT NULL,
  `is_active`    TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`   DATETIME         DEFAULT NULL,
  `updated_at`   DATETIME         DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_biolink_slug`    (`slug`),
  KEY        `idx_biolink_user`   (`user_id`),
  CONSTRAINT `fk_biolink_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 2. biolink_items ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `biolink_items` (
  `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `profile_id`     INT UNSIGNED     NOT NULL,
  -- Opsional: tautkan ke short_links.id agar tracking otomatis aktif.
  -- Jika NULL, tombol mengarah langsung ke url_tujuan tanpa tracking.
  `short_link_id`  INT UNSIGNED     DEFAULT NULL,
  `judul`          VARCHAR(255)     NOT NULL,
  -- URL tujuan (jika short_link_id NULL) ATAU short URL (jika tracking aktif)
  `url_tujuan`     VARCHAR(2048)    NOT NULL,
  -- Nama emoji atau kode ikon, misal '🌐' atau 'globe'
  `ikon`           VARCHAR(80)      DEFAULT NULL,
  -- Urutan tampil: angka terkecil tampil paling atas
  `urutan`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`      TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`     DATETIME         DEFAULT NULL,
  `updated_at`     DATETIME         DEFAULT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_blitem_profile`    (`profile_id`),
  KEY `idx_blitem_shortlink`  (`short_link_id`),
  CONSTRAINT `fk_blitem_profile`
    FOREIGN KEY (`profile_id`) REFERENCES `biolink_profiles` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_blitem_shortlink`
    FOREIGN KEY (`short_link_id`) REFERENCES `short_links` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
