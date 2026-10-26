<<<<<<< HEAD
# MKADSK11
=======
# Monitoring Kegiatan dan Anggaran (MKA System)

Aplikasi Web Admin untuk memonitor progress kegiatan, pelaksanaan pekerjaan, dan realisasi anggaran secara realtime, dibangun dengan **CodeIgniter 4** + **MySQL**.

---

## Stack Teknologi

| Layer    | Teknologi                          |
|----------|------------------------------------|
| Backend  | PHP 8.2+, CodeIgniter 4.7          |
| Database | MySQL 8.0+                         |
| Frontend | HTML5, CSS3, Vanilla JavaScript SPA |
| Auth     | JWT (HS256, tanpa library eksternal)|

---

## Struktur Folder (CI4)

```
app/
├── Config/
│   ├── Filters.php      ← JWT filter didaftarkan di sini
│   └── Routes.php       ← Semua route API + web
├── Controllers/
│   ├── Api/
│   │   ├── BaseApiController.php
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── KegiatanController.php
│   │   ├── TaskController.php
│   │   ├── AnggaranController.php
│   │   ├── UserController.php
│   │   ├── TeamController.php
│   │   ├── NotifikasiController.php
│   │   ├── LaporanController.php
│   │   ├── AuditLogController.php
│   │   └── DokumenController.php
│   └── WebController.php
├── Database/
│   ├── Migrations/       ← 10 file migrasi
│   └── Seeds/            ← MainSeeder, UserSeeder, TeamSeeder, KegiatanSeeder
├── Filters/
│   └── JwtAuthFilter.php
├── Libraries/
│   └── JwtLibrary.php    ← JWT murni PHP
├── Models/               ← 9 model
└── Views/
    ├── app.php           ← SPA shell
    └── auth/login.php    ← Halaman login
public/
├── assets/
│   ├── css/app.css
│   └── js/app.js
└── uploads/
    ├── dokumen/
    └── attachments/
```

---

## Cara Setup

### 1. Copy file env

```bash
cp env .env
```

Edit `.env`:
```
CI_ENVIRONMENT = development
app.baseURL    = 'http://localhost/DSK-DASH/public/'

database.default.hostname = localhost
database.default.database = monitoring_kegiatan
database.default.username = root
database.default.password =
```

### 2. Buat database

```sql
CREATE DATABASE monitoring_kegiatan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Jalankan Migrasi dan Seeder

```bash
php spark migrate
php spark db:seed MainSeeder
```

### 4. Buka di browser

```
http://localhost/DSK-DASH/public/login
```

---

## Akun Demo

| Role       | Email                        | Password    |
|------------|------------------------------|-------------|
| Direktur   | direktur@instansi.go.id      | password123 |
| Ketua Tim  | ketua.tim@instansi.go.id     | password123 |
| Anggota    | anggota@instansi.go.id       | password123 |

---

## API Endpoints

Base URL: `http://localhost/DSK-DASH/public/api/v1/`

### Auth
| Method | Endpoint          | Deskripsi       |
|--------|-------------------|-----------------|
| POST   | auth/login        | Login           |
| GET    | auth/me           | Info user saat ini |
| POST   | auth/logout       | Logout          |

### Kegiatan
| Method | Endpoint              | Role Required       |
|--------|-----------------------|---------------------|
| GET    | kegiatan              | Semua               |
| GET    | kegiatan/{id}         | Semua               |
| POST   | kegiatan              | Direktur            |
| PUT    | kegiatan/{id}         | Direktur, Ketua Tim |
| DELETE | kegiatan/{id}         | Direktur            |

### Tasks (nested)
| Method | Endpoint                       | Role Required        |
|--------|--------------------------------|----------------------|
| GET    | kegiatan/{id}/tasks            | Semua                |
| POST   | kegiatan/{id}/tasks            | Direktur, Ketua Tim  |
| PUT    | kegiatan/{id}/tasks/{taskId}   | Semua (terbatas)     |
| DELETE | kegiatan/{id}/tasks/{taskId}   | Direktur, Ketua Tim  |

### Anggaran (nested)
| Method | Endpoint                          | Role Required       |
|--------|-----------------------------------|---------------------|
| GET    | kegiatan/{id}/anggaran            | Semua               |
| POST   | kegiatan/{id}/anggaran            | Direktur, Ketua Tim |
| PUT    | kegiatan/{id}/anggaran/{angId}    | Direktur, Ketua Tim |
| DELETE | kegiatan/{id}/anggaran/{angId}    | Direktur only       |

---

## Fitur

- ✅ Dashboard realtime (kegiatan, anggaran, grafik, ranking tim)
- ✅ Kanban card-based kegiatan dengan progress bar
- ✅ Checklist task interaktif
- ✅ Input realisasi anggaran
- ✅ Upload dokumen & lampiran
- ✅ Laporan progress + anggaran (export CSV)
- ✅ Manajemen pengguna & tim (Direktur)
- ✅ Audit trail lengkap
- ✅ Notifikasi in-app
- ✅ JWT Authentication
- ✅ Role-Based Access Control (Direktur / Ketua Tim / Anggota Tim)
- ✅ Responsive (Mobile + Desktop)
>>>>>>> 1dfcc2e (Upload file baru)
