<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ============================================================
// API Routes — v1  (defined FIRST so (:any) web catch-all does not intercept them)
// ============================================================
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api'], static function ($routes) {

    // Auth (no JWT required) — rate limited: 10 req/60 detik per IP per endpoint
    $routes->post('auth/login',      'AuthController::login',      ['filter' => 'rateLimit10']);
    $routes->post('auth/register',   'AuthController::register',   ['filter' => 'rateLimit5']);
    $routes->post('auth/send-otp',   'AuthController::sendOtp',    ['filter' => 'rateLimit5']);
    $routes->post('auth/verify-otp', 'AuthController::verifyOtp',  ['filter' => 'rateLimit10']);

    // LTK — Registrasi Klien (publik, tidak butuh JWT) — rate limited
    $routes->post('ltk/klien/register', 'Ltk\KlienController::register', ['filter' => 'rateLimit5']);

    // Protected routes — JWT required
    $routes->group('', ['filter' => 'jwtAuth'], static function ($routes) {

        // Auth
        $routes->get('auth/me',                  'AuthController::me');
        $routes->post('auth/logout',             'AuthController::logout');
        $routes->put('auth/profile',             'AuthController::updateProfile');
        $routes->post('auth/change-password',    'AuthController::changePassword');

        // Dashboard
        $routes->get('dashboard', 'DashboardController::index');

        // Kegiatan
        $routes->get('kegiatan',                   'KegiatanController::index');
        $routes->get('kegiatan/on-budget',         'KegiatanController::onBudgetList');
        $routes->get('kegiatan/(:num)',            'KegiatanController::show/$1');
        $routes->post('kegiatan',                  'KegiatanController::create');
        $routes->put('kegiatan/(:num)',                          'KegiatanController::update/$1');
        $routes->patch('kegiatan/(:num)/convert-to-on-budget',  'KegiatanController::convertToOnBudget/$1');
        $routes->delete('kegiatan/(:num)',                       'KegiatanController::delete/$1');

        // Off Budget
        $routes->get('off-budget',                              'OffBudgetController::index');
        $routes->get('off-budget/sumber-ringkasan/(:num)',      'OffBudgetController::sumberRingkasan/$1');
        $routes->get('off-budget/(:num)/rincian',               'OffBudgetController::rincianDariSumber/$1');
        $routes->get('off-budget/(:num)/sumber',                'OffBudgetController::sumberIndex/$1');
        $routes->post('off-budget/(:num)/sumber',               'OffBudgetController::sumberCreate/$1');
        $routes->put('off-budget/(:num)/sumber/(:num)',         'OffBudgetController::sumberUpdate/$1/$2');
        $routes->delete('off-budget/(:num)/sumber/(:num)',      'OffBudgetController::sumberDelete/$1/$2');
        $routes->get('off-budget/(:num)',                       'OffBudgetController::show/$1');

        // Tasks (nested under kegiatan)
        $routes->get('kegiatan/(:num)/tasks',            'TaskController::index/$1');
        $routes->get('kegiatan/(:num)/tasks/(:num)',     'TaskController::show/$1/$2');
        $routes->post('kegiatan/(:num)/tasks',           'TaskController::create/$1');
        $routes->put('kegiatan/(:num)/tasks/(:num)',     'TaskController::update/$1/$2');
        $routes->delete('kegiatan/(:num)/tasks/(:num)', 'TaskController::delete/$1/$2');

        // Anggaran (nested under kegiatan)
        $routes->get('kegiatan/(:num)/anggaran',            'AnggaranController::index/$1');
        $routes->post('kegiatan/(:num)/anggaran',           'AnggaranController::create/$1');
        $routes->put('kegiatan/(:num)/anggaran/(:num)',     'AnggaranController::update/$1/$2');
        $routes->delete('kegiatan/(:num)/anggaran/(:num)', 'AnggaranController::delete/$1/$2');

        // Rincian Anggaran — komponen biaya per kegiatan
        $routes->get('kegiatan/(:num)/rincian',            'RincianAnggaranController::index/$1');
        $routes->get('kegiatan/(:num)/rincian/sisa',       'RincianAnggaranController::sisa/$1');
        $routes->post('kegiatan/(:num)/rincian',           'RincianAnggaranController::create/$1');
        $routes->put('kegiatan/(:num)/rincian/(:num)',     'RincianAnggaranController::update/$1/$2');
        $routes->delete('kegiatan/(:num)/rincian/(:num)', 'RincianAnggaranController::delete/$1/$2');

        // Dokumen (nested under kegiatan)
        $routes->get('kegiatan/(:num)/dokumen',                   'DokumenController::index/$1');
        $routes->post('kegiatan/(:num)/dokumen',                  'DokumenController::upload/$1');
        $routes->delete('kegiatan/(:num)/dokumen/(:num)',         'DokumenController::delete/$1/$2');
        $routes->post('kegiatan/(:num)/tasks/(:num)/attachments',            'DokumenController::uploadTaskAttachment/$1/$2');
        $routes->delete('kegiatan/(:num)/tasks/(:num)/attachments/(:num)',   'DokumenController::deleteTaskAttachment/$1/$2/$3');

        // Roles — manajemen role dinamis (admin only untuk CUD)
        $routes->get('roles',                        'RoleController::index');
        $routes->get('roles/permissions',            'RoleController::permissions');
        $routes->post('roles',                       'RoleController::create');
        $routes->put('roles/(:num)',                 'RoleController::update/$1');
        $routes->delete('roles/(:num)',              'RoleController::delete/$1');

        // Posisi Tim — master posisi dinamis
        $routes->get('posisi-tim',                   'PosisiTimController::index');
        $routes->post('posisi-tim',                  'PosisiTimController::store');
        $routes->put('posisi-tim/(:num)',             'PosisiTimController::update/$1');
        $routes->delete('posisi-tim/(:num)',          'PosisiTimController::delete/$1');

        // Users — Pengguna Internal DSK
        $routes->get('users',                        'UserController::index');
        $routes->post('users',                       'UserController::create');
        // Users — Klien  (harus di atas users/(:num) agar tidak tertangkap oleh pattern numerik)
        $routes->get('users/klien',                  'UserController::klienIndex');
        $routes->put('users/klien/(:num)/status',    'UserController::klienStatus/$1');
        $routes->delete('users/klien/(:num)',        'UserController::klienDelete/$1');
        // Users — by ID (setelah route literal)
        $routes->get('users/(:num)',                 'UserController::show/$1');
        $routes->put('users/(:num)',                 'UserController::update/$1');
        $routes->put('users/(:num)/posisi',          'PosisiTimController::syncUserPosisi/$1');
        $routes->delete('users/(:num)',              'UserController::delete/$1');

        // Teams
        $routes->get('teams',           'TeamController::index');
        $routes->get('teams/(:num)',    'TeamController::show/$1');
        $routes->post('teams',          'TeamController::create');
        $routes->put('teams/(:num)',    'TeamController::update/$1');
        $routes->delete('teams/(:num)', 'TeamController::delete/$1');

        // Notifikasi
        $routes->get('notifikasi',             'NotifikasiController::index');
        $routes->put('notifikasi/read-all',    'NotifikasiController::markAllRead');
        $routes->put('notifikasi/(:num)/read', 'NotifikasiController::markRead/$1');

        // Laporan
        $routes->get('laporan/progress', 'LaporanController::progress');
        $routes->get('laporan/anggaran', 'LaporanController::anggaran');

        // Audit Log
        $routes->get('audit-log', 'AuditLogController::index');

        // ── Admin — Pengaturan & Manajemen Pengguna ─────────────────
        $routes->get('admin/stats',            'AdminController::stats');
        $routes->get('admin/settings',         'AdminController::settingsIndex');
        $routes->post('admin/settings',        'AdminController::settingsUpdate');
        $routes->post('admin/test-email',      'AdminController::testEmail');
        $routes->get('admin/users',            'AdminController::usersIndex');
        $routes->post('admin/users',           'AdminController::usersCreate');
        $routes->put('admin/users/(:num)',     'AdminController::usersUpdate/$1');
        $routes->delete('admin/users/(:num)',  'AdminController::usersDelete/$1');

        // ── SERASI v2 — SharePoint REST API ────────────────────────
        // Dokumen
        $routes->get('serasi/stats',                    'SerasiController::stats');
        $routes->get('serasi/documents',                'SerasiController::documentsIndex');
        $routes->get('serasi/documents/(:num)',         'SerasiController::documentsShow/$1');
        $routes->post('serasi/documents',               'SerasiController::documentsCreate');
        $routes->put('serasi/documents/(:num)',         'SerasiController::documentsUpdate/$1');
        $routes->delete('serasi/documents/(:num)',      'SerasiController::documentsDelete/$1');
        // Tag
        $routes->get('serasi/tags',                     'SerasiController::tagsIndex');
        $routes->post('serasi/tags',                    'SerasiController::tagCreate');
        $routes->delete('serasi/tags/(:num)',           'SerasiController::tagDelete/$1');
        // Folder
        $routes->get('serasi/folders',                  'SerasiController::foldersIndex');
        // SharePoint REST API
        $routes->get('serasi/sharepoint/status',        'SerasiSharePointController::status');
        $routes->post('serasi/sharepoint/test',         'SerasiSharePointController::testConnection');
        $routes->get('serasi/sharepoint/lists',         'SerasiSharePointController::lists');
        $routes->get('serasi/sharepoint/browse',        'SerasiSharePointController::browse');
        $routes->get('serasi/sharepoint/files',         'SerasiSharePointController::files');
        $routes->get('serasi/sharepoint/file-meta',     'SerasiSharePointController::fileMeta');
        $routes->post('serasi/sharepoint/sync-folders', 'SerasiSharePointController::syncFolders');
        $routes->post('serasi/sharepoint/import-file',  'SerasiSharePointController::importFile');

        // RO (Rumpun Output — pengelompokan on-budget kegiatan)
        $routes->get('ro',           'RoController::index');
        $routes->get('ro/(:num)',    'RoController::show/$1');
        $routes->post('ro',          'RoController::create');
        $routes->put('ro/(:num)',    'RoController::update/$1');
        $routes->delete('ro/(:num)', 'RoController::delete/$1');

        // RPD (Rencana Penarikan Dana — berbasis Kegiatan)
        $routes->get('rpd/dashboard',         'RpdController::dashboardTables');
        $routes->get('rpd',                   'RpdController::index');
        $routes->post('rpd',                  'RpdController::save');
        $routes->delete('rpd/(:num)',         'RpdController::delete/$1');

        // ── Layanan Telaah Konsep (LTK) ────────────────────────────
        $routes->group('ltk', ['namespace' => 'App\Controllers\Api\Ltk'], static function ($routes) {

            // ── Klien ───────────────────────────────────────────────
            $routes->get('klien/pending',             'KlienController::pending');
            $routes->post('klien/(:num)/verifikasi',  'KlienController::verifikasi/$1');
            $routes->get('klien/profil',              'KlienController::profil');

            // ── Form Builder ────────────────────────────────────────
            $routes->get('form-template',                            'FormBuilderController::index');
            $routes->get('form-template/aktif',                      'FormBuilderController::aktif');
            $routes->get('form-template/(:num)',                     'FormBuilderController::show/$1');
            $routes->post('form-template',                           'FormBuilderController::create');
            $routes->put('form-template/(:num)',                     'FormBuilderController::update/$1');
            $routes->delete('form-template/(:num)',                  'FormBuilderController::delete/$1');
            $routes->get('form-template/(:num)/fields',              'FormBuilderController::fields/$1');
            $routes->post('form-template/(:num)/fields',             'FormBuilderController::addField/$1');
            $routes->put('form-template/(:num)/fields/reorder',      'FormBuilderController::reorderFields/$1');
            $routes->put('form-template/(:num)/fields/(:num)',       'FormBuilderController::updateField/$1/$2');
            $routes->delete('form-template/(:num)/fields/(:num)',    'FormBuilderController::deleteField/$1/$2');

            // ── Permohonan ──────────────────────────────────────────
            $routes->get('permohonan',                               'PermohonanController::index');
            $routes->get('permohonan/saya',                          'PermohonanController::saya');
            $routes->post('permohonan/tu-input',                     'PermohonanController::tuInput');      // TU input langsung dari SRIKANDI
            $routes->get('permohonan/(:num)',                        'PermohonanController::show/$1');
            $routes->post('permohonan',                              'PermohonanController::create');
            $routes->post('permohonan/(:num)/konfirmasi-srikandi',   'PermohonanController::konfirmasiSrikandi/$1');
            $routes->post('permohonan/(:num)/verifikasi',            'PermohonanController::verifikasi/$1');

            // ── Antrian ─────────────────────────────────────────────
            $routes->get('antrian',                  'AntrianController::index');
            $routes->put('antrian/(:num)/prioritas', 'AntrianController::setPrioritas/$1');
            $routes->post('antrian/(:num)/tugaskan', 'AntrianController::tugaskan/$1');

            // ── PIC ─────────────────────────────────────────────────
            $routes->get('pic/list',                    'PicController::listPic');
            $routes->get('pic/tugas',                   'PicController::tugasAktif');
            $routes->post('pic/tugas/(:num)/terima',    'PicController::terima/$1');
            $routes->post('pic/tugas/(:num)/mulai-asesmen', 'PicController::mulaiAsesmen/$1');

            // ── Asesmen ─────────────────────────────────────────────
            $routes->post('asesmen',             'AsesmenController::store');
            $routes->get('asesmen/(:num)',        'AsesmenController::show/$1');

            // ── Dokumen & Output ────────────────────────────────────
            $routes->get('dokumen/(:num)',                          'DokumenController::index/$1');
            $routes->post('dokumen/policy-brief',                  'DokumenController::submitPolicyBrief');
            $routes->post('dokumen/policy-paper',                  'DokumenController::submitPolicyPaper');
            $routes->post('dokumen/generate-nota-dinas',           'DokumenController::generateNotaDinas');
            $routes->post('dokumen/(:num)/selesai',                'DokumenController::selesai/$1');
            $routes->get('dokumen/download/(:num)/(:alpha)',       'DokumenController::download/$1/$2');

            // ── Timeline ────────────────────────────────────────────
            $routes->get('permohonan/(:num)/timeline', 'TimelineController::show/$1');

            // ── SLA ─────────────────────────────────────────────────
            $routes->get('sla/(:num)', 'SlaController::show/$1');

            // ── Dashboard ───────────────────────────────────────────
            $routes->get('dashboard/tu',    'DashboardController::tu');
            $routes->get('dashboard/pic',   'DashboardController::pic');
            $routes->get('dashboard/klien', 'DashboardController::klien');

            // ── Laporan ─────────────────────────────────────────────
            $routes->get('laporan/permohonan',           'LaporanController::permohonan');
            $routes->get('laporan/per-unit',             'LaporanController::perUnit');
            $routes->get('laporan/per-pic',              'LaporanController::perPic');
            $routes->get('laporan/sla',                  'LaporanController::sla');
            $routes->get('laporan/prioritas',            'LaporanController::prioritas');
            $routes->get('laporan/output-jenis',         'LaporanController::outputJenis');
            $routes->get('laporan/statistik-policy-brief', 'LaporanController::statistikPolicyBrief');
            $routes->get('laporan/statistik-policy-paper', 'LaporanController::statistikPolicyPaper');
            $routes->get('laporan/kinerja-pic',          'LaporanController::kinerjaPic');
            $routes->get('laporan/rata-waktu',           'LaporanController::rataWaktu');

            // ── Admin LTK ───────────────────────────────────────────
            $routes->get('admin/sla-config',             'AdminLtkController::slaConfigIndex');
            $routes->put('admin/sla-config',             'AdminLtkController::slaConfigUpdate');
            $routes->get('admin/hari-libur',             'AdminLtkController::hariLiburIndex');
            $routes->post('admin/hari-libur',            'AdminLtkController::hariLiburCreate');
            $routes->post('admin/hari-libur/batch',      'AdminLtkController::hariLiburBatch');
            $routes->delete('admin/hari-libur/(:num)',   'AdminLtkController::hariLiburDelete/$1');
            $routes->get('admin/nd-template-status',     'AdminLtkController::ndTemplateStatus');
            $routes->post('admin/nd-template/upload',    'AdminLtkController::ndTemplateUpload');

            // ── Notifikasi LTK ──────────────────────────────────────
            $routes->get('notifikasi',             'AdminLtkController::notifikasiIndex');
            $routes->put('notifikasi/read-all',    'AdminLtkController::notifikasiReadAll');
        });

        // ── URL Shortener ────────────────────────────────────────────
        $routes->get('short-links',                   'ShortLinkController::index');
        $routes->post('short-links',                  'ShortLinkController::create');
        $routes->get('short-links/(:num)',             'ShortLinkController::show/$1');
        $routes->delete('short-links/(:num)',          'ShortLinkController::delete/$1');
        $routes->get('short-links/(:num)/logs',        'ShortLinkController::logs/$1');

        // ── Biolink / Multi-link Builder ─────────────────────────────
        // Cards
        $routes->get('biolink/cards',                              'BiolinkController::listCards');
        $routes->post('biolink/cards',                             'BiolinkController::createCard');
        $routes->get('biolink/cards/(:num)',                       'BiolinkController::showCard/$1');
        $routes->post('biolink/cards/(:num)',                      'BiolinkController::updateCard/$1');
        $routes->delete('biolink/cards/(:num)',                    'BiolinkController::deleteCard/$1');
        $routes->patch('biolink/cards/(:num)/toggle',              'BiolinkController::toggleCard/$1');
        // Items (nested — literal 'reorder' sebelum (:num) agar tidak tertukar)
        $routes->get('biolink/cards/(:num)/items',                 'BiolinkController::getItems/$1');
        $routes->post('biolink/cards/(:num)/items',                'BiolinkController::createItem/$1');
        $routes->put('biolink/cards/(:num)/items/reorder',         'BiolinkController::reorderItems/$1');
        $routes->put('biolink/cards/(:num)/items/(:num)',          'BiolinkController::updateItem/$1/$2');
        $routes->patch('biolink/cards/(:num)/items/(:num)/toggle', 'BiolinkController::toggleItem/$1/$2');
        $routes->delete('biolink/cards/(:num)/items/(:num)',       'BiolinkController::deleteItem/$1/$2');

        // ── Schedule ────────────────────────────────────────────────
        $routes->group('schedule', ['namespace' => 'App\Controllers\Api\Schedule'], static function ($routes) {

            // Agenda CRUD
            $routes->get('',                    'ScheduleController::index');
            $routes->get('events',              'ScheduleController::events');
            $routes->get('widget-stats',        'ScheduleController::widgetStats');
            $routes->get('(:num)',              'ScheduleController::show/$1');
            $routes->post('',                   'ScheduleController::store');
            $routes->put('(:num)',              'ScheduleController::update/$1');
            $routes->delete('(:num)',           'ScheduleController::delete/$1');
            $routes->post('(:num)/resync',      'ScheduleController::resync/$1');

            // Kategori
            $routes->get('categories',          'ScheduleController::categories');
            $routes->post('categories',         'ScheduleController::storeCategory');
            $routes->put('categories/(:num)',    'ScheduleController::updateCategory/$1');
            $routes->delete('categories/(:num)','ScheduleController::deleteCategory/$1');

            // Google Calendar
            $routes->get('google/settings',     'GoogleCalendarController::settings');
            $routes->put('google/settings',     'GoogleCalendarController::updateSettings');
            $routes->get('google/auth-url',     'GoogleCalendarController::authUrl');
            $routes->post('google/callback',    'GoogleCalendarController::callback');
            $routes->post('google/disconnect',  'GoogleCalendarController::disconnect');
            $routes->post('google/test',        'GoogleCalendarController::test');
            $routes->post('google/resync-all',  'GoogleCalendarController::resyncAll');
            $routes->get('google/calendars',    'GoogleCalendarController::listCalendars');
            $routes->post('google/pull',        'GoogleCalendarController::pullFromGoogle');
            $routes->get('google/sync-logs',    'GoogleCalendarController::syncLogs');
        });
    });
});

// ============================================================
// Web Routes (SPA Shell) — AFTER API routes
// ============================================================
$routes->get('/', 'WebController::index');
$routes->get('login', 'WebController::login');
// OAuth2 Callback dari Google — landing page sederhana yang post ke API
$routes->get('google-oauth-callback', 'WebController::googleOauthCallback');
// Catch-all hanya untuk path yang BUKAN aset statis dan bukan api
$routes->get('app/(:any)', 'WebController::index');

// ============================================================
// Biolink — Halaman Publik (tidak butuh auth)
// ============================================================
// Serve foto profil dari writable/ (harus sebelum biolink/(:any))
$routes->get('biolink-foto/(:any)', 'BiolinkPublicController::foto/$1');
// Halaman profil publik Linktree-style
$routes->get('biolink/(:any)', 'BiolinkPublicController::show/$1');

// ============================================================
// URL Shortener — Public Redirect (HARUS setelah API & SPA routes)
// ============================================================
// Route ini menangkap /s/<slug> dan melakukan redirect + tracking
$routes->get('s/(:any)', 'RedirectController::redirect/$1');
