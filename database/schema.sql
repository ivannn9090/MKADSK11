-- ============================================================
-- Monitoring Kegiatan dan Anggaran
-- Database Schema - MySQL
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS notifikasi;
DROP TABLE IF EXISTS dokumen;
DROP TABLE IF EXISTS realisasi_anggaran;
DROP TABLE IF EXISTS task_attachments;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS kegiatan;
DROP TABLE IF EXISTS team_members;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Tabel Users
-- ------------------------------------------------------------
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(150)  NOT NULL,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    jabatan     VARCHAR(100),
    role        ENUM('direktur','ketua_tim','anggota_tim') NOT NULL DEFAULT 'anggota_tim',
    status      ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel Teams
-- ------------------------------------------------------------
CREATE TABLE teams (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_tim     VARCHAR(150) NOT NULL,
    ketua_tim_id INT UNSIGNED NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ketua_tim_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel TeamMembers
-- ------------------------------------------------------------
CREATE TABLE team_members (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_user (team_id, user_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel Kegiatan
-- ------------------------------------------------------------
CREATE TABLE kegiatan (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_kegiatan   VARCHAR(200) NOT NULL,
    deskripsi       TEXT,
    tanggal_mulai   DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    pagu_anggaran   DECIMAL(15,2) NOT NULL DEFAULT 0,
    status          ENUM('draft','berjalan','selesai','terlambat') NOT NULL DEFAULT 'draft',
    team_id         INT UNSIGNED NOT NULL,
    created_by      INT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_team (team_id),
    FOREIGN KEY (team_id)    REFERENCES teams(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel Tasks
-- ------------------------------------------------------------
CREATE TABLE tasks (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kegiatan_id    INT UNSIGNED NOT NULL,
    nama_task      VARCHAR(255) NOT NULL,
    pic_id         INT UNSIGNED,
    status         ENUM('belum_dimulai','sedang_berjalan','selesai') NOT NULL DEFAULT 'belum_dimulai',
    target_date    DATE,
    completed_date DATE,
    catatan        TEXT,
    urutan         SMALLINT UNSIGNED DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kegiatan (kegiatan_id),
    INDEX idx_pic (pic_id),
    FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE,
    FOREIGN KEY (pic_id)      REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel Task Attachments (Bukti Pekerjaan)
-- ------------------------------------------------------------
CREATE TABLE task_attachments (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id      INT UNSIGNED NOT NULL,
    nama_file    VARCHAR(255) NOT NULL,
    file_url     VARCHAR(500) NOT NULL,
    uploaded_by  INT UNSIGNED NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id)     REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel Realisasi Anggaran
-- ------------------------------------------------------------
CREATE TABLE realisasi_anggaran (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kegiatan_id     INT UNSIGNED NOT NULL,
    tanggal         DATE NOT NULL,
    uraian          VARCHAR(255) NOT NULL,
    nominal         DECIMAL(15,2) NOT NULL DEFAULT 0,
    bukti_transaksi VARCHAR(500),
    created_by      INT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kegiatan (kegiatan_id),
    FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel Dokumen
-- ------------------------------------------------------------
CREATE TABLE dokumen (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kegiatan_id  INT UNSIGNED NOT NULL,
    nama_file    VARCHAR(255) NOT NULL,
    jenis_file   VARCHAR(50),
    file_url     VARCHAR(500) NOT NULL,
    uploaded_by  INT UNSIGNED NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kegiatan_id) REFERENCES kegiatan(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel Notifikasi
-- ------------------------------------------------------------
CREATE TABLE notifikasi (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    judul      VARCHAR(200) NOT NULL,
    pesan      TEXT,
    tipe       ENUM('task_baru','task_update','deadline','terlambat','anggaran','selesai') NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel Audit Log
-- ------------------------------------------------------------
CREATE TABLE audit_log (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED,
    aktivitas      VARCHAR(255) NOT NULL,
    tabel_terkait  VARCHAR(100),
    record_id      INT UNSIGNED,
    data_sebelum   JSON,
    data_sesudah   JSON,
    ip_address     VARCHAR(45),
    waktu          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_waktu (waktu),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SEED DATA
-- ============================================================

-- Users (password: "password123" hashed with bcrypt placeholder)
INSERT INTO users (id, nama, email, password, jabatan, role, status) VALUES
(1, 'Dr. Budi Santoso', 'direktur@instansi.go.id', '$2y$10$examplehashforpassword123xxxxx1', 'Direktur Utama', 'direktur', 'aktif'),
(2, 'Siti Rahayu, S.E.', 'ketua.tim@instansi.go.id', '$2y$10$examplehashforpassword123xxxxx2', 'Kepala Seksi', 'ketua_tim', 'aktif'),
(3, 'Andi Prasetyo', 'anggota@instansi.go.id', '$2y$10$examplehashforpassword123xxxxx3', 'Staf Pelaksana', 'anggota_tim', 'aktif'),
(4, 'Dewi Kusuma', 'anggota2@instansi.go.id', '$2y$10$examplehashforpassword123xxxxx4', 'Staf Analis', 'anggota_tim', 'aktif');

-- Teams
INSERT INTO teams (id, nama_tim, ketua_tim_id) VALUES
(1, 'Tim Konsultasi Publik', 2),
(2, 'Tim Pengembangan Regulasi', 2);

-- Team Members
INSERT INTO team_members (team_id, user_id) VALUES
(1, 2), (1, 3), (1, 4),
(2, 2), (2, 3);

-- Kegiatan
INSERT INTO kegiatan (id, nama_kegiatan, deskripsi, tanggal_mulai, tanggal_selesai, pagu_anggaran, status, team_id, created_by) VALUES
(1, 'Konsultasi Publik Rancangan Kebijakan', 'Kegiatan konsultasi publik untuk mendapatkan masukan dari pemangku kepentingan terkait rancangan kebijakan strategis.', '2026-09-01', '2026-09-30', 50000000.00, 'berjalan', 1, 1),
(2, 'Workshop Pengembangan SDM', 'Workshop peningkatan kapasitas dan kompetensi sumber daya manusia di lingkungan instansi.', '2026-10-01', '2026-10-31', 35000000.00, 'draft', 2, 1);

-- Tasks for Kegiatan 1
INSERT INTO tasks (id, kegiatan_id, nama_task, pic_id, status, target_date, urutan) VALUES
(1,  1, 'Penentuan Pakar yang Diundang',      2, 'selesai',        '2026-09-05', 1),
(2,  1, 'Penentuan Stakeholder yang Diundang', 3, 'selesai',        '2026-09-06', 2),
(3,  1, 'Penentuan Tanggal',                  2, 'selesai',        '2026-09-07', 3),
(4,  1, 'Booking Tempat',                     3, 'selesai',        '2026-09-08', 4),
(5,  1, 'Penunjukan Penanggung Jawab',         2, 'selesai',        '2026-09-09', 5),
(6,  1, 'Penyusunan TOR',                     4, 'selesai',        '2026-09-12', 6),
(7,  1, 'Penyusunan Undangan',                3, 'sedang_berjalan','2026-09-14', 7),
(8,  1, 'Pelaksanaan Kegiatan',               2, 'belum_dimulai',  '2026-09-20', 8),
(9,  1, 'Penyusunan Laporan',                 4, 'belum_dimulai',  '2026-09-30', 9);

-- Tasks for Kegiatan 2
INSERT INTO tasks (id, kegiatan_id, nama_task, pic_id, status, target_date, urutan) VALUES
(10, 2, 'Persiapan Materi Workshop',    2, 'belum_dimulai', '2026-10-05', 1),
(11, 2, 'Undangan Peserta',             3, 'belum_dimulai', '2026-10-07', 2),
(12, 2, 'Pelaksanaan Workshop',         2, 'belum_dimulai', '2026-10-15', 3),
(13, 2, 'Evaluasi dan Pelaporan',       4, 'belum_dimulai', '2026-10-25', 4);

-- Realisasi Anggaran for Kegiatan 1
INSERT INTO realisasi_anggaran (kegiatan_id, tanggal, uraian, nominal, created_by) VALUES
(1, '2026-09-10', 'Sewa Tempat Pelaksanaan', 10000000.00, 2),
(1, '2026-09-10', 'Konsumsi Peserta',         5000000.00, 2),
(1, '2026-09-11', 'Honorarium Narasumber',    8000000.00, 2);
