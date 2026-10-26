/* ============================================================
   core.js — Config, State, API helpers, Bootstrap, UI init,
             Navigation, Notifications
   ============================================================ */
'use strict';

// ---------------------------------------------------------------
// Config — URLs injected by PHP via layout/_head.php <script> block
// ---------------------------------------------------------------
const BASE_URL  = window.APP_API_URL  || '/api/v1/';
const LOGIN_URL = window.APP_LOGIN_URL || '/login';

// ---------------------------------------------------------------
// State
// ---------------------------------------------------------------
let currentUser        = null;
let currentPage        = 'dashboard';
let currentModalKegId  = null;   // ID kegiatan yang sedang terbuka di modal
let currentModalTipe   = null;   // 'on_budget' | 'off_budget'
let notifInterval      = null;
let _lastUnreadCount   = 0;   // Lacak jumlah unread sebelumnya untuk deteksi notifikasi baru
let _userPermissions   = [];  // Array permission user yang sedang login

/**
 * Cek apakah user punya permission tertentu.
 * Admin (role 'admin') selalu punya semua permission.
 */
function hasPermission(perm) {
  if (!currentUser) return false;
  if (currentUser.role === 'admin') return true;
  return _userPermissions.includes(perm);
}

/**
 * Subtitle di sidebar footer:
 * - Admin → label role ("Administrator")
 * - User lain → posisi tim tertinggi (urutan terkecil),
 *   fallback ke label role jika tidak punya posisi.
 */
function _sidebarSubtitle() {
  if (!currentUser) return '';
  if (currentUser.role === 'admin') return roleLabel(currentUser.role);

  // Ambil posisi dari currentUser.posisi (array [{id, nama, urutan}], sudah urut ASC dari server)
  const posisi = currentUser.posisi;
  if (Array.isArray(posisi) && posisi.length > 0) {
    return esc(posisi[0].nama);
  }

  // Fallback: posisi_tim string lama (dari localStorage sebelum auth/me selesai)
  if (currentUser.posisi_tim) return esc(currentUser.posisi_tim);

  // Fallback terakhir: role label
  return roleLabel(currentUser.role);
}

// ---------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  const token   = localStorage.getItem('mka_token');
  const userRaw = localStorage.getItem('mka_user');

  // Cek token — jika tidak ada atau expired, redirect ke login
  if (!token || !userRaw) {
    redirectToLogin();
    return;
  }

  // Decode exp dari JWT payload (client-side check)
  try {
    const parts   = token.split('.');
    const payload = JSON.parse(atob(parts[1].replace(/-/g,'+').replace(/_/g,'/')));
    if (!payload.exp || payload.exp <= Math.floor(Date.now() / 1000)) {
      localStorage.removeItem('mka_token');
      localStorage.removeItem('mka_user');
      redirectToLogin();
      return;
    }
  } catch (e) {
    localStorage.removeItem('mka_token');
    localStorage.removeItem('mka_user');
    redirectToLogin();
    return;
  }

  currentUser = JSON.parse(userRaw);

  // Muat permissions dari server (async, non-blocking untuk UI)
  apiGet('auth/me').then(res => {
    if (res?.data?.permissions) {
      const prev = JSON.stringify(_userPermissions);
      _userPermissions = res.data.permissions;
      // Merge data terbaru dari server
      if (res.data.role)   currentUser.role   = res.data.role;
      if (res.data.roles)  currentUser.roles  = res.data.roles;
      if (res.data.posisi) currentUser.posisi = res.data.posisi;
      // Cache permissions terbaru ke localStorage
      try { localStorage.setItem('mka_permissions', JSON.stringify(_userPermissions)); } catch(e) {}
      // Re-render sidebar dengan data terbaru (selalu, agar posisi tim ikut terupdate)
      if (JSON.stringify(_userPermissions) !== prev || res.data.posisi) {
        initUI();
      }
    }
  });

  // Jika permissions sudah ada di localStorage (dari sesi sebelumnya), pakai dulu
  try {
    const savedPerms = localStorage.getItem('mka_permissions');
    if (savedPerms) _userPermissions = JSON.parse(savedPerms);
  } catch(e) {}

  initUI();

  // Role LTK-only langsung ke halaman LTK, lainnya ke dashboard
  let defaultPage = 'dashboard';
  if (currentUser.role === 'klien') defaultPage = 'ltk_dashboard_klien';
  else if (currentUser.role === 'pic_telaah') defaultPage = 'ltk_dashboard_pic';
  navigateTo(defaultPage);

  loadNotifications();
  notifInterval = setInterval(loadNotifications, 30000);

  // Jika akun ditandai wajib ganti password, buka profile panel di tab password
  if (parseInt(currentUser.must_change_password) === 1) {
    // Delay kecil agar panel sudah diinisialisasi oleh profile.js
    setTimeout(() => openProfilePanelForcePassword(), 300);
  }
});

function redirectToLogin() {
  // Hanya redirect jika belum di halaman login — cegah loop
  if (!window.location.href.includes('/login')) {
    window.location.href = LOGIN_URL;
  }
}

// ---------------------------------------------------------------
// API Helper
// ---------------------------------------------------------------
async function api(path, options = {}) {
  const token = localStorage.getItem('mka_token');
  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    ...(options.headers || {}),
  };

  // For FormData (file upload), don't set Content-Type
  if (options.body instanceof FormData) {
    delete headers['Content-Type'];
  }

  const res = await fetch(BASE_URL + path, { ...options, headers });

  if (res.status === 401) {
    localStorage.removeItem('mka_token');
    localStorage.removeItem('mka_user');
    redirectToLogin();
    return null;
  }

  // Guard: jika server return non-JSON (error page HTML, dsb), jangan crash
  const ct = res.headers.get('content-type') || '';
  if (!ct.includes('application/json')) {
    const text = await res.text();
    console.error('[api] Non-JSON response for', path, res.status, text.slice(0, 200));
    return { status: false, message: `Server error (${res.status}). Lihat console untuk detail.` };
  }

  try {
    return await res.json();
  } catch (e) {
    console.error('[api] JSON parse error for', path, e);
    return { status: false, message: 'Response server tidak valid (bukan JSON).' };
  }
}

function apiGet(path)             { return api(path); }
function apiPost(path, data)      { return api(path, { method: 'POST',   body: JSON.stringify(data) }); }
function apiPut(path, data)       { return api(path, { method: 'PUT',    body: JSON.stringify(data) }); }
function apiDelete(path)          { return api(path, { method: 'DELETE' }); }
function apiPatch(path, data)     { return api(path, { method: 'PATCH',   body: JSON.stringify(data) }); }
function apiUpload(path, formData){ return api(path, { method: 'POST',   body: formData }); }

// ---------------------------------------------------------------
// UI Init
// ---------------------------------------------------------------
let _listenersAttached = false;  // guard agar listener tidak didaftarkan dua kali

function initUI() {
  _updateUIState();      // update user info + show/hide menu (boleh berulang)
  if (!_listenersAttached) {
    _listenersAttached = true;
    _initUIListeners(); // event listeners — sekali saja
  }
}

/** Bagian UI yang boleh diperbarui berulang (user info, visibilitas menu) */
function _updateUIState() {
  // User info in sidebar (clickable — profile.js handles the click)
  document.getElementById('userInfo').innerHTML = `
    <div style="display:flex;align-items:center;justify-content:space-between">
      <div class="user-name">${esc(currentUser.nama)}</div>
      <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12" style="opacity:.45;flex-shrink:0" title="Edit profil"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
    </div>
    <div class="user-role">${_sidebarSubtitle()}</div>
  `;

  // Show/hide nav items by permission
  const isAdmin     = currentUser.role === 'admin';
  const isTu        = currentUser.role === 'tu';

  // Menu Pengguna — tampil jika punya users.view atau users.manage
  if (hasPermission('users.view') || hasPermission('users.manage')) {
    document.querySelectorAll('.users-nav-only').forEach(el => el.style.display = 'flex');
  }
  // Menu Tim — tampil jika punya teams.view atau teams.manage
  if (hasPermission('teams.view') || hasPermission('teams.manage')) {
    document.querySelectorAll('.teams-nav-only').forEach(el => el.style.display = 'flex');
  }
  // Menu Audit Trail — tampil jika punya audit.view
  if (hasPermission('audit.view')) {
    document.querySelectorAll('.audit-nav-only').forEach(el => el.style.display = 'flex');
  }

  // Menu RPD — tampil jika punya rpd.view
  if (hasPermission('rpd.view')) {
    document.querySelectorAll('.rpd-only').forEach(el => el.style.display = 'flex');
  } else {
    document.querySelectorAll('.rpd-only').forEach(el => el.style.display = 'none');
  }
  // Admin: tampilkan menu Pengaturan
  if (isAdmin) {
    document.querySelectorAll('.admin-only').forEach(el => el.style.display = 'flex');
  }

  // ── Schedule — tampil jika punya schedule.view ───────────
  if (hasPermission('schedule.view')) {
    document.querySelectorAll('.schedule-only').forEach(el => {
      el.style.display = el.tagName === 'DIV' ? 'block' : 'flex'
    })
  }
  if (hasPermission('schedule.manage')) {
    document.querySelectorAll('.schedule-admin-only').forEach(el => el.style.display = 'flex')
  }

  // ── Layanan Telaah Konsep — visibilitas berdasarkan permission ──────────
  const hasAnyLtk = ['ltk.view','ltk.input','ltk.verifikasi','ltk.antrian',
                      'ltk.tugaskan','ltk.pic','ltk.laporan','ltk.admin']
                      .some(p => hasPermission(p));

  if (hasAnyLtk) {
    // Tampilkan section label
    document.querySelectorAll('.nav-section-label.ltk-only').forEach(el => el.style.display = 'block');

    // TU / input / antrian menus
    if (hasPermission('ltk.input') || hasPermission('ltk.antrian') || hasPermission('ltk.verifikasi') || isAdmin) {
      document.querySelectorAll('.ltk-tu-only').forEach(el => el.style.display = 'flex');
    }
    // Admin pengaturan
    if (hasPermission('ltk.admin') || isAdmin) {
      document.querySelectorAll('.ltk-admin-only').forEach(el => el.style.display = 'flex');
    }
    // PIC menu
    if (hasPermission('ltk.pic') || hasPermission('ltk.tugaskan') || isAdmin) {
      document.querySelectorAll('.ltk-pic-only').forEach(el => el.style.display = 'flex');
    }
    // Klien menu
    if (currentUser.role === 'klien') {
      document.querySelectorAll('.ltk-klien-only').forEach(el => el.style.display = 'flex');
    }
  }

  // Role KHUSUS LTK saja (tidak punya akses ke modul MKA utama):
  if (['klien', 'pic_telaah'].includes(currentUser.role)) {
    ['kegiatan','off_budget','laporan','users','teams','audit','rpd','serasi','settings'].forEach(p => {
      const el = document.querySelector(`.nav-item[data-page="${p}"]`);
      if (el) el.style.display = 'none';
    });
  }
  // direktur/ketua_tim/anggota_tim/perencana_anggaran: MKA utama TETAP tampil,
  // hanya tambahan menu LTK (ltk-pic-only) yang muncul — tidak ada yang disembunyikan.
}  // ── akhir _updateUIState() ──────────────────────────────────

/** Event listeners — dipasang sekali saja saat halaman pertama dimuat */
function _initUIListeners() {
  // Nav clicks
  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', e => {
      e.preventDefault();
      const page = item.dataset.page;
      navigateTo(page);
    });
  });

  // ── Nav group toggle (Kegiatan dropdown) ──────────────────
  document.querySelectorAll('.nav-group-header').forEach(header => {
    header.addEventListener('click', () => {
      const group = header.closest('.nav-group');
      const sidebar = document.getElementById('sidebar');
      // Pada collapsed mode, klik header langsung navigasi ke halaman pertama sub-item
      if (sidebar.classList.contains('collapsed')) {
        const firstSub = group.querySelector('.nav-sub-item');
        if (firstSub) navigateTo(firstSub.dataset.page);
        return;
      }
      group.classList.toggle('open');
    });
  });

  // ── Sidebar collapse / expand ──────────────────────────────
  const sidebar        = document.getElementById('sidebar');
  const collapseBtn    = document.getElementById('sidebarCollapseBtn');
  const showSidebarBtn = document.getElementById('showSidebarBtn');
  const SIDEBAR_FULL   = '240px';
  const SIDEBAR_MIN    = '56px';

  function _setSidebarCollapsed(collapsed) {
    if (collapsed) {
      sidebar.classList.add('collapsed');
      // Update CSS var → body flex layout merespons instan
      document.documentElement.style.setProperty('--sidebar-w', SIDEBAR_MIN);
    } else {
      sidebar.classList.remove('collapsed');
      document.documentElement.style.setProperty('--sidebar-w', SIDEBAR_FULL);
    }
    showSidebarBtn.style.display = collapsed ? 'flex' : 'none';
    localStorage.setItem('mka_sidebar_collapsed', collapsed ? '1' : '0');
  }

  // Restore saved state
  _setSidebarCollapsed(localStorage.getItem('mka_sidebar_collapsed') === '1');

  // Hide button (inside sidebar header)
  collapseBtn.addEventListener('click', () => {
    _setSidebarCollapsed(!sidebar.classList.contains('collapsed'));
  });

  // Show button (floating tab at left edge)
  showSidebarBtn.addEventListener('click', () => {
    _setSidebarCollapsed(false);
  });

  // Menu toggle (mobile hamburger in topbar)
  document.getElementById('menuToggle').addEventListener('click', () => {
    sidebar.classList.toggle('open');
  });

  // ── Back to top ────────────────────────────────────────────
  const backToTopBtn  = document.getElementById('backToTopBtn');
  const contentEl     = document.getElementById('mainContent');

  contentEl.addEventListener('scroll', () => {
    if (contentEl.scrollTop > 300) {
      backToTopBtn.style.display = 'flex';
      setTimeout(() => backToTopBtn.classList.add('visible'), 10);
    } else {
      backToTopBtn.classList.remove('visible');
      setTimeout(() => { if (!backToTopBtn.classList.contains('visible')) backToTopBtn.style.display = 'none'; }, 220);
    }
  });

  backToTopBtn.addEventListener('click', () => {
    contentEl.scrollTo({ top: 0, behavior: 'smooth' });
  });

  // Logout
  document.getElementById('btnLogout').addEventListener('click', async () => {
    await apiPost('auth/logout');
    localStorage.clear();
    clearInterval(notifInterval);
    window.location.href = LOGIN_URL;
  });

  // Notification button
  document.getElementById('notifBtn').addEventListener('click', e => {
    e.stopPropagation();
    const dropdown = document.getElementById('notifDropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
  });

  document.getElementById('markAllRead').addEventListener('click', async () => {
    const readAllPath = currentUser?.role === 'klien' ? 'ltk/notifikasi/read-all' : 'notifikasi/read-all'
    await apiPut(readAllPath);
    loadNotifications();
  });

  // Close dropdown on outside click
  document.addEventListener('click', () => {
    document.getElementById('notifDropdown').style.display = 'none';
  });
  document.getElementById('notifDropdown').addEventListener('click', e => e.stopPropagation());

  // Modal close
  document.getElementById('modalClose').addEventListener('click', closeModal);
  document.getElementById('modalOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('modalOverlay')) closeModal();
  });
}

function navigateTo(page) {
  currentPage = page;
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.page === page);
  });

  // Update nav-group header active state & auto-open jika sub-item aktif
  document.querySelectorAll('.nav-group').forEach(group => {
    const hasActive = group.querySelector(`.nav-sub-item[data-page="${page}"]`);
    const header    = group.querySelector('.nav-group-header');
    if (hasActive) {
      header.classList.add('active');
      group.classList.add('open');
    } else {
      header.classList.remove('active');
    }
  });
  const titles = {
    dashboard: 'Dashboard', serasi: 'SERASI — Sistem Terintegrasi Rekomendasi dan Informasi',
    kegiatan: 'On Budget', off_budget: 'Kegiatan Off Budget',
    laporan: 'Laporan', users: 'Manajemen Pengguna', teams: 'Manajemen Tim',
    audit: 'Audit Trail', rpd: 'Rencana Penarikan Dana',
    settings: 'Pengaturan Aplikasi',
    // ── URL Shortener ──
    short_links: 'URL Shortener & QR Code Generator',
    // ── Biolink ──
    biolink: 'Biolink Manager',
    // ── Schedule ──
    schedule:            'Schedule — Kalender Kegiatan',
    schedule_google:     'Pengaturan Google Calendar',
    schedule_categories: 'Kategori Schedule',
    // ── LTK ──
    ltk_dashboard_tu:     'Dashboard Layanan Telaah',
    ltk_dashboard_pic:    'Dashboard Telaah — PIC',
    ltk_dashboard_klien:  'Layanan Telaah Konsep',
    ltk_permohonan:       'Permohonan Telaah',
    ltk_permohonan_klien: 'Layanan Telaah Konsep',
    ltk_antrian:          'Antrian Telaah',
    ltk_tugas_pic:        'Tugas Telaah Saya',
    ltk_form_builder:     'Form Builder',
    ltk_laporan:          'Laporan Telaah',
    ltk_admin:            'Pengaturan Layanan Telaah',
  };
  document.getElementById('pageTitle').textContent = titles[page] || page;
  loadPage(page);
}

function loadPage(page) {
  const content = document.getElementById('mainContent');
  content.innerHTML = '<div class="loading-page"><div class="spinner-lg"></div></div>';

  switch (page) {
  case 'dashboard':  renderDashboard();  break;
  case 'serasi':     renderSerasi();     break;
  case 'kegiatan':   renderKegiatan();   break;
  case 'off_budget': renderOffBudget();  break;
  case 'laporan':    renderLaporan();    break;
  case 'users':      renderUsers();      break;
  case 'teams':      renderTeams();      break;
  case 'audit':      renderAudit();      break;
  case 'rpd':        renderRpd();        break;
  case 'settings':   renderSettings();   break;
  // ── URL Shortener ──
  case 'short_links': renderShortLinks(); break;
  // ── Biolink ──
  case 'biolink': renderBiolink(); break;
  // ── Schedule ──
  case 'schedule':            renderSchedule();            break;
  case 'schedule_google':     renderScheduleGoogle();      break;
  case 'schedule_categories': renderScheduleCategories();  break;
  // ── Layanan Telaah Konsep ──
  case 'ltk_dashboard_tu':     renderLtkDashboardTu();     break;
    case 'ltk_dashboard_pic':    renderLtkDashboardPic();    break;
    case 'ltk_dashboard_klien':  renderLtkDashboardKlien();  break;
    case 'ltk_permohonan':       renderLtkPermohonan();      break;
    case 'ltk_permohonan_klien': renderLtkPermohonanKlien(); break;
    case 'ltk_antrian':          renderLtkAntrian();         break;
    case 'ltk_tugas_pic':        renderLtkTugasPic();        break;
    case 'ltk_form_builder':     renderLtkFormBuilder();     break;
    case 'ltk_laporan':          renderLtkLaporan();         break;
    case 'ltk_admin':            renderLtkAdmin();           break;
    default: content.innerHTML = '<p style="padding:24px">Halaman tidak ditemukan.</p>';
  }
}

// ---------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------
async function loadNotifications() {
  // Klien dan PIC LTK-only menggunakan notifikasi LTK
  const notifPath = currentUser?.role === 'klien' ? 'ltk/notifikasi' : 'notifikasi'
  const res = await apiGet(notifPath);
  if (!res || !res.status) return;

  const { items, unread_count } = res.data;
  const badge = document.getElementById('notifBadge');
  badge.style.display = unread_count > 0 ? 'flex' : 'none';
  badge.textContent   = unread_count;

  // Jika ada notifikasi baru (unread bertambah) dan modal kegiatan sedang terbuka,
  // refresh modal agar task/update terbaru langsung tampil tanpa perlu reload manual.
  if (unread_count > _lastUnreadCount && currentModalKegId) {
    if (typeof refreshCurrentModal === 'function') refreshCurrentModal();
  }
  _lastUnreadCount = unread_count;

  const list = document.getElementById('notifList');
  if (!items || items.length === 0) {
    list.innerHTML = '<div style="padding:16px;text-align:center;color:#94a3b8;font-size:13px">Tidak ada notifikasi</div>';
    return;
  }
  list.innerHTML = items.slice(0, 20).map(n => `
    <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}" onclick="readNotif(${n.id})">
      <div class="notif-title">${esc(n.judul)}</div>
      <div class="notif-pesan">${esc(n.pesan || '')}</div>
      <div class="notif-time">${timeAgo(n.created_at)}</div>
    </div>
  `).join('');
}

async function readNotif(id) {
  await apiPut(`notifikasi/${id}/read`);
  loadNotifications();
}
