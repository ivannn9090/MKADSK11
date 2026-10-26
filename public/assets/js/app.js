/* ============================================================
   Monitoring Kegiatan dan Anggaran — Main App JS (SPA)
   ============================================================ */

'use strict';

// ---------------------------------------------------------------
// Config — URLs injected by PHP via app.php <script> block
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
let notifInterval = null;

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
  initUI();

  // Role LTK-only langsung ke halaman LTK
  const defaultPage = {
    klien:      'ltk_dashboard_klien',
    pic_telaah: 'ltk_dashboard_pic',
  }[currentUser.role] || 'dashboard';

  navigateTo(defaultPage);
  loadNotifications();
  notifInterval = setInterval(loadNotifications, 30000);
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

  return res.json();
}

function apiGet(path)             { return api(path); }
function apiPost(path, data)      { return api(path, { method: 'POST',   body: JSON.stringify(data) }); }
function apiPut(path, data)       { return api(path, { method: 'PUT',    body: JSON.stringify(data) }); }
function apiDelete(path)          { return api(path, { method: 'DELETE' }); }
function apiUpload(path, formData){ return api(path, { method: 'POST',   body: formData }); }

// ---------------------------------------------------------------
// UI Init
// ---------------------------------------------------------------
function initUI() {
  // User info in sidebar
  document.getElementById('userInfo').innerHTML = `
    <div class="user-name">${esc(currentUser.nama)}</div>
    <div class="user-role">${roleLabel(currentUser.role)}</div>
  `;

  // Show/hide nav items by role
  if (currentUser.role !== 'direktur') {
    document.querySelectorAll('.direktur-only').forEach(el => el.style.display = 'none');
  }
  // Perencana anggaran: tampilkan menu RPD
  if (['direktur', 'perencana_anggaran'].includes(currentUser.role)) {
    document.querySelectorAll('.rpd-only').forEach(el => el.style.display = 'flex');
  } else {
    document.querySelectorAll('.rpd-only').forEach(el => el.style.display = 'none');
  }

  // ── Layanan Telaah Konsep — tampilkan item sesuai role ──
  const LTK_ROLES = ['admin', 'tu', 'pic_telaah', 'klien'];
  if (LTK_ROLES.includes(currentUser.role)) {
    // Tampilkan section label
    document.querySelectorAll('.ltk-only').forEach(el => el.style.display = 'flex');
    document.querySelectorAll('.nav-section-label.ltk-only').forEach(el => {
      el.style.display = 'block';
    });
    // Sembunyikan dulu semua item per sub-role
    document.querySelectorAll('.ltk-tu-only').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.ltk-pic-only').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.ltk-klien-only').forEach(el => el.style.display = 'none');

    if (['admin', 'tu'].includes(currentUser.role)) {
      document.querySelectorAll('.ltk-tu-only').forEach(el => el.style.display = 'flex');
    }
    if (currentUser.role === 'pic_telaah') {
      document.querySelectorAll('.ltk-pic-only').forEach(el => el.style.display = 'flex');
    }
    if (currentUser.role === 'klien') {
      document.querySelectorAll('.ltk-klien-only').forEach(el => el.style.display = 'flex');
    }
  } else {
    document.querySelectorAll('.ltk-only').forEach(el => el.style.display = 'none');
  }

  // Sembunyikan menu utama (On Budget, Off Budget, Laporan, dll) untuk role LTK-only
  if (['klien', 'pic_telaah'].includes(currentUser.role)) {
    ['kegiatan','off_budget','laporan','users','teams','audit','rpd','serasi'].forEach(p => {
      const el = document.querySelector(`.nav-item[data-page="${p}"]`);
      if (el) el.style.display = 'none';
    });
  }

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
      const group   = header.closest('.nav-group');
      const sidebar = document.getElementById('sidebar');
      if (sidebar.classList.contains('collapsed')) {
        const firstSub = group.querySelector('.nav-sub-item');
        if (firstSub) navigateTo(firstSub.dataset.page);
        return;
      }
      group.classList.toggle('open');
    });
  });

  // Menu toggle
  document.getElementById('menuToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
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
    await apiPut('notifikasi/read-all');
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
    dashboard: 'Dashboard', kegiatan: 'On Budget', off_budget: 'Kegiatan Off Budget',
    laporan: 'Laporan', users: 'Manajemen Pengguna', teams: 'Manajemen Tim',
    audit: 'Audit Trail', rpd: 'Rencana Penarikan Dana',
    // LTK
    ltk_dashboard_tu:     'Dashboard Layanan Telaah',
    ltk_dashboard_pic:    'Dashboard Telaah — PIC',
    ltk_dashboard_klien:  'Dashboard Pengajuan Telaah',
    ltk_permohonan:       'Permohonan Telaah',
    ltk_permohonan_klien: 'Permohonan Saya',
    ltk_antrian:          'Antrian Telaah',
    ltk_tugas_pic:        'Tugas Telaah Saya',
    ltk_form_builder:     'Form Builder',
    ltk_laporan:          'Laporan Telaah',
  };
  document.getElementById('pageTitle').textContent = titles[page] || page;
  loadPage(page);
}

function loadPage(page) {
  const content = document.getElementById('mainContent');
  content.innerHTML = '<div class="loading-page"><div class="spinner-lg"></div></div>';

  switch (page) {
    case 'dashboard':  renderDashboard();  break;
    case 'kegiatan':   renderKegiatan();   break;
    case 'off_budget': renderOffBudget();  break;
    case 'laporan':    renderLaporan();    break;
    case 'users':      renderUsers();      break;
    case 'teams':      renderTeams();      break;
    case 'audit':      renderAudit();      break;
    case 'rpd':        renderRpd();        break;
    // ── Layanan Telaah Konsep ──
    case 'ltk_dashboard_tu':     renderLtkDashboardTu();    break;
    case 'ltk_dashboard_pic':    renderLtkDashboardPic();   break;
    case 'ltk_dashboard_klien':  renderLtkDashboardKlien(); break;
    case 'ltk_permohonan':       renderLtkPermohonan();     break;
    case 'ltk_permohonan_klien': renderLtkPermohonanKlien();break;
    case 'ltk_antrian':          renderLtkAntrian();        break;
    case 'ltk_tugas_pic':        renderLtkTugasPic();       break;
    case 'ltk_form_builder':     renderLtkFormBuilder();    break;
    case 'ltk_laporan':          renderLtkLaporan();        break;
    default: content.innerHTML = '<p style="padding:24px">Halaman tidak ditemukan.</p>';
  }
}

// ---------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------
async function loadNotifications() {
  const res = await apiGet('notifikasi');
  if (!res || !res.status) return;

  const { items, unread_count } = res.data;
  const badge = document.getElementById('notifBadge');
  badge.style.display = unread_count > 0 ? 'flex' : 'none';
  badge.textContent   = unread_count;

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

// ---------------------------------------------------------------
// DASHBOARD
// ---------------------------------------------------------------
async function renderDashboard() {
  const res = await apiGet('dashboard');
  if (!res || !res.status) { showError('Gagal memuat dashboard'); return; }

  const { kegiatan, anggaran, progress_list, serapan_bulanan, ranking_tim, rpd_dashboard } = res.data;

  document.getElementById('mainContent').innerHTML = `
    <!-- Stat Cards: Kegiatan -->
    <div class="stats-grid">
      <div class="stat-card blue">
        <div class="stat-label">Total Kegiatan</div>
        <div class="stat-value">${kegiatan.total || 0}</div>
      </div>
      <div class="stat-card green">
        <div class="stat-label">Kegiatan Berjalan</div>
        <div class="stat-value">${kegiatan.berjalan || 0}</div>
      </div>
      <div class="stat-card blue">
        <div class="stat-label">Kegiatan Selesai</div>
        <div class="stat-value">${kegiatan.selesai || 0}</div>
      </div>
      <div class="stat-card red">
        <div class="stat-label">Kegiatan Terlambat</div>
        <div class="stat-value">${kegiatan.terlambat || 0}</div>
      </div>
    </div>

    <!-- Stat Cards: Anggaran -->
    <div class="stats-grid">
      <div class="stat-card blue">
        <div class="stat-label">Total Pagu Anggaran</div>
        <div class="stat-value" style="font-size:20px">${rupiah(anggaran.total_pagu)}</div>
      </div>
      <div class="stat-card green">
        <div class="stat-label">Total Realisasi</div>
        <div class="stat-value" style="font-size:20px">${rupiah(anggaran.total_realisasi)}</div>
      </div>
      <div class="stat-card yellow">
        <div class="stat-label">Sisa Anggaran</div>
        <div class="stat-value" style="font-size:20px">${rupiah(anggaran.sisa_anggaran)}</div>
      </div>
      <div class="stat-card blue">
        <div class="stat-label">Persentase Serapan</div>
        <div class="stat-value">${anggaran.persentase_serapan}%</div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="chart-grid">
      <div class="chart-card">
        <div class="chart-title">Serapan Anggaran Bulanan</div>
        <div class="chart-area"><canvas id="chartBulanan"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-title">Status Kegiatan</div>
        <div class="chart-area"><canvas id="chartStatus"></canvas></div>
      </div>
    </div>

    <!-- Progress Per Kegiatan -->
    <div class="section-header">
      <div class="section-title">Progress Kegiatan</div>
    </div>
    <div style="margin-bottom:24px">
      ${progress_list.map(k => `
        <div style="background:#fff;border-radius:10px;border:1px solid #e2e8f0;padding:14px 16px;margin-bottom:10px;cursor:pointer"
             onclick="navigateTo('kegiatan')">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <span style="font-weight:600">${esc(k.nama_kegiatan)}</span>
            <span class="status-badge status-${k.status}">${statusLabel(k.status)}</span>
          </div>
          <div class="progress-wrap">
            <div class="progress-header">
              <span>${k.ketua_tim_nama}</span>
              <span>${k.progress}%</span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill progress-${k.warna_status}" style="width:${k.progress}%"></div>
            </div>
          </div>
          <div style="font-size:12px;color:#64748b;margin-top:6px">
            Pagu: ${rupiah(k.pagu_anggaran)} | Realisasi: ${rupiah(k.total_realisasi)} | Deadline: ${fmtDate(k.tanggal_selesai)}
          </div>
        </div>
      `).join('') || emptyState('Belum ada kegiatan')}
    </div>

    <!-- Ranking Tim -->
    <div class="section-header">
      <div class="section-title">Ranking Tim</div>
    </div>
    <div class="table-wrap" style="margin-bottom:24px">
      <table>
        <thead><tr><th>#</th><th>Nama Tim</th><th>Jumlah Kegiatan</th><th>Rata-rata Progress</th></tr></thead>
        <tbody>
          ${ranking_tim.map((t, i) => `
            <tr>
              <td>${i + 1}</td>
              <td>${esc(t.nama_tim)}</td>
              <td>${t.total_kegiatan}</td>
              <td>
                <div class="progress-bar" style="max-width:200px">
                  <div class="progress-fill progress-${progressColor(t.avg_progress)}" style="width:${t.avg_progress}%"></div>
                </div>
                <span style="font-size:12px;color:#64748b">${t.avg_progress}%</span>
              </td>
            </tr>
          `).join('') || '<tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:24px">Belum ada data</td></tr>'}
        </tbody>
      </table>
    </div>

    ${rpd_dashboard ? `
    <!-- Tabel Realisasi Anggaran per Bulan -->
    <div class="section-header" style="margin-top:8px">
      <div class="section-title" style="color:#1e40af">
        <span style="margin-right:8px">📊</span>1. Tabel Realisasi Anggaran
        <span style="font-size:12px;font-weight:400;color:#64748b;margin-left:8px">Tahun ${rpd_dashboard.tahun}</span>
      </div>
    </div>
    <div class="rpd-table-wrap" style="margin-bottom:28px">
      <div style="overflow-x:auto">
      <table class="rpd-table">
        <thead>
          <tr class="rpd-header-blue">
            <th rowspan="2" style="vertical-align:middle">KEGIATAN</th>
            <th rowspan="2" style="vertical-align:middle">PAGU<br>ANGGARAN</th>
            <th colspan="12" style="text-align:center;background:#1e40af;color:#fff">REALISASI PER BULAN</th>
            <th rowspan="2" style="vertical-align:middle">TOTAL<br>REALISASI</th>
            <th rowspan="2" style="vertical-align:middle">%<br>SERAPAN</th>
            <th rowspan="2" style="vertical-align:middle">SISA<br>ANGGARAN</th>
          </tr>
          <tr class="rpd-header-blue">
            ${['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'].map(b=>`<th>${b}</th>`).join('')}
          </tr>
        </thead>
        <tbody>
          ${rpd_dashboard.tabel_realisasi.rows.map(r => `
            <tr>
              <td class="rpd-unit">${esc(r.nama_kegiatan)}</td>
              <td class="rpd-num">${rupiahSingkat(r.pagu_anggaran)}</td>
              ${['01','02','03','04','05','06','07','08','09','10','11','12'].map(p=>`<td class="rpd-num">${rupiahSingkat(r['real_'+p]||0)}</td>`).join('')}
              <td class="rpd-num rpd-total-col">${rupiahSingkat(r.real_total)}</td>
              <td class="rpd-pct ${pctColor(r.pct_serapan)}">${r.pct_serapan}%</td>
              <td class="rpd-num">${rupiahSingkat(r.sisa_anggaran)}</td>
            </tr>
          `).join('')}
        </tbody>
        <tfoot>
          <tr class="rpd-total">
            <td>TOTAL</td>
            <td class="rpd-num">${rupiahSingkat(rpd_dashboard.tabel_realisasi.total.pagu_anggaran)}</td>
            ${['01','02','03','04','05','06','07','08','09','10','11','12'].map(p=>`<td class="rpd-num">${rupiahSingkat(rpd_dashboard.tabel_realisasi.total['real_'+p]||0)}</td>`).join('')}
            <td class="rpd-num rpd-total-col">${rupiahSingkat(rpd_dashboard.tabel_realisasi.total.real_total)}</td>
            <td class="rpd-pct ${pctColor(rpd_dashboard.tabel_realisasi.total.pct_serapan)}">${rpd_dashboard.tabel_realisasi.total.pct_serapan}%</td>
            <td class="rpd-num">${rupiahSingkat(rpd_dashboard.tabel_realisasi.total.sisa_anggaran)}</td>
          </tr>
        </tfoot>
      </table>
      </div>
    </div>

    <!-- Tabel RPD per Bulan -->
    <div class="section-header">
      <div class="section-title" style="color:#d97706">
        <span style="margin-right:8px">💰</span>2. Tabel Rencana Penarikan Dana (RPD)
      </div>
      ${['direktur','perencana_anggaran'].includes(currentUser.role) ? `
        <button class="btn btn-primary btn-sm" onclick="showRpdForm(${rpd_dashboard.tahun})">✏️ Input RPD</button>
      ` : ''}
    </div>
    <div class="rpd-table-wrap" style="margin-bottom:28px">
      <div style="overflow-x:auto">
      <table class="rpd-table">
        <thead>
          <tr class="rpd-header-yellow">
            <th rowspan="2" style="vertical-align:middle">KEGIATAN</th>
            <th colspan="12" style="text-align:center;background:#d97706;color:#fff">RPD PER BULAN</th>
            <th colspan="12" style="text-align:center;background:#047857;color:#fff">REALISASI PER BULAN</th>
            <th rowspan="2" style="vertical-align:middle">TOTAL<br>RPD</th>
            <th rowspan="2" style="vertical-align:middle">TOTAL<br>REALISASI</th>
            <th rowspan="2" style="vertical-align:middle">%<br>REAL/RPD</th>
            <th rowspan="2" style="vertical-align:middle">SELISIH</th>
          </tr>
          <tr class="rpd-header-yellow">
            ${['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'].map(b=>`<th style="background:#fef3c7;color:#92400e">${b}</th>`).join('')}
            ${['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'].map(b=>`<th style="background:#d1fae5;color:#065f46">${b}</th>`).join('')}
          </tr>
        </thead>
        <tbody>
          ${rpd_dashboard.tabel_rpd.rows.map(r => `
            <tr>
              <td class="rpd-unit">${esc(r.nama_kegiatan)}</td>
              ${['01','02','03','04','05','06','07','08','09','10','11','12'].map(p=>`<td class="rpd-num" style="background:#fffbeb">${rupiahSingkat(r['bln_'+p]||0)}</td>`).join('')}
              ${['01','02','03','04','05','06','07','08','09','10','11','12'].map(p=>`<td class="rpd-num" style="background:#f0fdf4">${rupiahSingkat(r['real_'+p]||0)}</td>`).join('')}
              <td class="rpd-num rpd-total-col">${rupiahSingkat(r.rpd_total)}</td>
              <td class="rpd-num rpd-total-col">${rupiahSingkat(r.real_total)}</td>
              <td class="rpd-pct ${pctColor(r.pct_real_vs_rpd)}">${r.pct_real_vs_rpd}%</td>
              <td class="rpd-num">${rupiahSingkat(r.selisih)}</td>
            </tr>
          `).join('')}
        </tbody>
        <tfoot>
          <tr class="rpd-total">
            <td>TOTAL</td>
            ${['01','02','03','04','05','06','07','08','09','10','11','12'].map(p=>`<td class="rpd-num">${rupiahSingkat(rpd_dashboard.tabel_rpd.total['bln_'+p]||0)}</td>`).join('')}
            ${['01','02','03','04','05','06','07','08','09','10','11','12'].map(p=>`<td class="rpd-num">${rupiahSingkat(rpd_dashboard.tabel_rpd.total['real_'+p]||0)}</td>`).join('')}
            <td class="rpd-num rpd-total-col">${rupiahSingkat(rpd_dashboard.tabel_rpd.total.rpd_total)}</td>
            <td class="rpd-num rpd-total-col">${rupiahSingkat(rpd_dashboard.tabel_rpd.total.real_total)}</td>
            <td class="rpd-pct ${pctColor(rpd_dashboard.tabel_rpd.total.pct_real_vs_rpd)}">${rpd_dashboard.tabel_rpd.total.pct_real_vs_rpd}%</td>
            <td class="rpd-num">${rupiahSingkat(rpd_dashboard.tabel_rpd.total.selisih)}</td>
          </tr>
        </tfoot>
      </table>
      </div>
    </div>
    ` : ''}
  `;

  // Draw charts
  drawBulananChart(serapan_bulanan);
  drawStatusChart(kegiatan);
}

function drawBulananChart(data) {
  const canvas = document.getElementById('chartBulanan');
  if (!canvas || !data || data.length === 0) return;
  const ctx = canvas.getContext('2d');
  const labels = data.map(d => d.bulan);
  const values = data.map(d => parseFloat(d.total));
  const max    = Math.max(...values, 1);
  const w = canvas.offsetWidth, h = canvas.offsetHeight;
  canvas.width = w; canvas.height = h;
  const pad = 32, barW = Math.min(40, (w - pad * 2) / labels.length - 4);

  ctx.clearRect(0, 0, w, h);
  ctx.fillStyle = '#e2e8f0';
  ctx.fillRect(pad, pad, w - pad * 2, h - pad * 2);

  labels.forEach((l, i) => {
    const x      = pad + (i / labels.length) * (w - pad * 2) + barW / 2;
    const barH   = ((values[i] / max) * (h - pad * 2 - 20));
    const y      = h - pad - barH;
    const grad   = ctx.createLinearGradient(0, y, 0, h - pad);
    grad.addColorStop(0, '#3b82f6');
    grad.addColorStop(1, '#1e40af');
    ctx.fillStyle = grad;
    ctx.beginPath();
    ctx.roundRect(x - barW/2, y, barW, barH, 4);
    ctx.fill();
    ctx.fillStyle = '#64748b';
    ctx.font = '10px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(l.slice(5), x, h - pad + 14);
  });
}

function drawStatusChart(kegiatan) {
  const canvas = document.getElementById('chartStatus');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const data   = [+kegiatan.berjalan, +kegiatan.selesai, +kegiatan.terlambat, +kegiatan.draft];
  const labels = ['Berjalan', 'Selesai', 'Terlambat', 'Draft'];
  const colors = ['#3b82f6', '#16a34a', '#dc2626', '#94a3b8'];
  const total  = data.reduce((a, b) => a + b, 0) || 1;
  const w = canvas.offsetWidth, h = canvas.offsetHeight;
  canvas.width = w; canvas.height = h;
  const cx = w / 2, cy = h / 2, r = Math.min(cx, cy) - 24;
  let angle = -Math.PI / 2;

  ctx.clearRect(0, 0, w, h);
  data.forEach((val, i) => {
    const slice = (val / total) * Math.PI * 2;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, r, angle, angle + slice);
    ctx.closePath();
    ctx.fillStyle = colors[i];
    ctx.fill();
    // Legend
    ctx.fillStyle = colors[i];
    ctx.fillRect(4, 12 + i * 20, 12, 12);
    ctx.fillStyle = '#374151';
    ctx.font = '11px sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText(`${labels[i]}: ${val}`, 20, 23 + i * 20);
    angle += slice;
  });
}

// ---------------------------------------------------------------
// KEGIATAN
// ---------------------------------------------------------------
async function renderKegiatan() {
  const res = await apiGet('kegiatan');
  if (!res || !res.status) { showError('Gagal memuat kegiatan'); return; }

  // Hanya tampilkan on_budget di halaman Kegiatan
  const rows = (res.data || []).filter(k => k.tipe !== 'off_budget');

  let addBtn = '';
  if (currentUser.role === 'direktur') {
    addBtn = `<button class="btn btn-primary" onclick="showKegiatanForm()">
      <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
      Tambah Kegiatan
    </button>`;
  }

  document.getElementById('mainContent').innerHTML = `
    <div class="section-header">
      <div class="section-title">Daftar Kegiatan (${rows.length})</div>
      ${addBtn}
    </div>
    <div class="kegiatan-grid" id="kegiatanGrid">
      ${rows.map(k => kegiatanCard(k)).join('') || emptyState('Belum ada kegiatan')}
    </div>
  `;
}

function kegiatanCard(k, onclickFn = null) {
  const isOffBudget  = k.tipe === 'off_budget';
  const clickHandler = onclickFn || `showKegiatanDetail(${k.id})`;
  return `
    <div class="kegiatan-card${isOffBudget ? ' off-budget-card' : ''}" data-keg-id="${k.id}" onclick="${clickHandler}">
      <div class="kegiatan-card-header">
        <div class="kegiatan-name">
          ${isOffBudget ? '<span class="badge-offbudget">Off Budget</span>' : ''}
          ${esc(k.nama_kegiatan)}
        </div>
        <span class="status-badge status-${k.status}">${statusLabel(k.status)}</span>
      </div>
      <div class="kegiatan-meta">
        <span>👤 ${esc(k.ketua_tim_nama || '—')}</span>
        <span>🏢 ${esc(k.nama_tim || '—')}</span>
        ${isOffBudget && k.sumber_nama ? `<span style="color:#7c3aed;font-size:11px">📌 Sumber: ${esc(k.sumber_nama)}</span>` : ''}
      </div>
      <div class="progress-wrap">
        <div class="progress-header">
          <span>Progress (${k.tasks_selesai}/${k.total_tasks} task)</span>
          <span>${k.progress}%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill progress-${k.warna_status}" style="width:${k.progress}%"></div>
        </div>
      </div>
      <div class="anggaran-mini">
        <div class="ang-item">
          <div class="ang-label">${isOffBudget ? 'Alokasi' : 'Pagu'}</div>
          <div class="ang-value">${rupiah(k.pagu_anggaran)}</div>
        </div>
        <div class="ang-item">
          <div class="ang-label">Realisasi</div>
          <div class="ang-value">${rupiah(k.total_realisasi)}</div>
        </div>
        <div class="ang-item">
          <div class="ang-label">Sisa</div>
          <div class="ang-value">${rupiah(k.sisa_anggaran)}</div>
        </div>
      </div>
      <div class="progress-wrap" style="margin-top:10px">
        <div class="progress-header">
          <span>Serapan Anggaran</span>
          <span>${k.persentase_serapan}%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill progress-${anggaranColor(k.persentase_serapan)}" style="width:${Math.min(k.persentase_serapan,100)}%"></div>
        </div>
      </div>
      <div class="deadline-row">📅 Deadline: ${fmtDate(k.tanggal_selesai)}</div>
    </div>
  `;
}

/** Refresh modal yang sedang terbuka DAN update card di grid secara silent */
function refreshCurrentModal() {
  if (!currentModalKegId) return;
  // Update card di background grid tanpa reload penuh
  refreshCardInGrid(currentModalKegId);
  if (currentModalTipe === 'off_budget') showOffBudgetDetail(currentModalKegId);
  else showKegiatanDetail(currentModalKegId);
}

/**
 * Fetch data terbaru kegiatan dan update card-nya di DOM (jika ada di grid).
 * Dipanggil setiap kali ada perubahan — tidak mengganggu modal yang sedang terbuka.
 */
async function refreshCardInGrid(kegiatanId) {
  // Cari card di grid yang ada di DOM sekarang
  const selector = `[data-keg-id="${kegiatanId}"]`;
  const cardEl = document.querySelector(selector);
  if (!cardEl) return;   // card tidak ada di halaman ini, skip

  // Fetch summary terbaru (bukan full detail)
  const res = await apiGet('kegiatan');
  if (!res || !res.status) return;
  const k = (res.data || []).find(x => x.id == kegiatanId);
  if (!k) return;

  // Ganti hanya card element, preserve onclick
  const isOB     = k.tipe === 'off_budget';
  const onclick  = isOB ? `showOffBudgetDetail(${k.id})` : `showKegiatanDetail(${k.id})`;
  const newCard  = document.createElement('div');
  newCard.innerHTML = kegiatanCard(k, onclick);
  const newEl = newCard.firstElementChild;
  cardEl.replaceWith(newEl);
}

async function showKegiatanDetail(id) {
  currentModalKegId = id;
  currentModalTipe  = 'on_budget';
  openModal('Memuat...', '<div class="loading-page"><div class="spinner-lg"></div></div>', [], 'modal-lg');

  const [res, teamsRes, usersRes] = await Promise.all([
    apiGet(`kegiatan/${id}`),
    apiGet('teams'),
    apiGet('users'),
  ]);

  if (!res || !res.status) { closeModal(); showError('Gagal memuat detail'); return; }

  const k     = res.data;
  const users = usersRes?.data || [];

  document.getElementById('modalTitle').textContent = k.nama_kegiatan;

  document.getElementById('modalBody').innerHTML = `
    <div class="tabs">
      <div class="tab active" data-tab="tasks">✅ Task (${k.tasks.length})</div>
      <div class="tab" data-tab="anggaran">💰 Anggaran</div>
      <div class="tab" data-tab="info">ℹ️ Info</div>
      <div class="tab" data-tab="dokumen">📎 Dokumen</div>
    </div>

    <!-- Tab: Tasks -->
    <div class="tab-content active" id="tab-tasks">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <span style="font-size:12px;color:#64748b">${k.tasks_selesai} dari ${k.total_tasks} task selesai</span>
        ${['direktur','ketua_tim'].includes(currentUser.role) ? `
          <button class="btn btn-primary btn-sm" onclick="showAddTask(${k.id}, ${JSON.stringify(users).replace(/"/g, '&quot;')})">+ Tambah Task</button>
        ` : ''}
      </div>
      <div class="progress-wrap" style="margin-bottom:16px">
        <div class="progress-bar">
          <div class="progress-fill progress-${k.warna_status || progressColor(k.progress)}" style="width:${k.progress}%"></div>
        </div>
        <div style="text-align:right;font-size:12px;color:#64748b;margin-top:4px">${k.progress}%</div>
      </div>
      <div class="task-list" id="taskList-${k.id}">
        ${k.tasks.map(t => taskItem(t, k.id, users)).join('') || emptyState('Belum ada task')}
      </div>
    </div>

    <!-- Tab: Anggaran -->
    <div class="tab-content" id="tab-anggaran">
      <div class="anggaran-summary-grid">
        <div class="ang-summary-card">
          <div class="ang-summary-label">Pagu Anggaran</div>
          <div class="ang-summary-value">${rupiah(k.pagu_anggaran)}</div>
        </div>
        <div class="ang-summary-card">
          <div class="ang-summary-label">Total Realisasi</div>
          <div class="ang-summary-value" style="color:#16a34a">${rupiah(k.total_realisasi)}</div>
        </div>
        <div class="ang-summary-card">
          <div class="ang-summary-label">Sisa Anggaran</div>
          <div class="ang-summary-value" style="color:#d97706">${rupiah(k.sisa_anggaran)}</div>
        </div>
        <div class="ang-summary-card">
          <div class="ang-summary-label">Persentase Serapan</div>
          <div class="ang-summary-value">${k.persentase_serapan}%</div>
        </div>
      </div>
      ${['direktur','ketua_tim'].includes(currentUser.role) ? `
        <button class="btn btn-primary btn-sm" style="margin-bottom:12px" onclick="showAddRealisasi(${k.id})">+ Input Realisasi</button>
      ` : ''}
      <div class="table-wrap">
        <table>
          <thead><tr><th>Tanggal</th><th>Uraian</th><th>Nominal</th><th>Oleh</th><th></th></tr></thead>
          <tbody id="realisasiBody-${k.id}">
            ${k.realisasi.map(r => realisasiRow(r, k.id)).join('') || '<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:20px">Belum ada realisasi</td></tr>'}
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab: Info -->
    <div class="tab-content" id="tab-info">
      <div class="form-row form-row-2" style="margin-bottom:12px">
        <div><div class="ang-summary-label">Nama Kegiatan</div><div>${esc(k.nama_kegiatan)}</div></div>
        <div><div class="ang-summary-label">Tim</div><div>${esc(k.nama_tim || '—')}</div></div>
        <div><div class="ang-summary-label">Ketua Tim</div><div>${esc(k.ketua_tim_nama || '—')}</div></div>
        <div><div class="ang-summary-label">Status</div><div><span class="status-badge status-${k.status}">${statusLabel(k.status)}</span></div></div>
        <div><div class="ang-summary-label">Tanggal Mulai</div><div>${fmtDate(k.tanggal_mulai)}</div></div>
        <div><div class="ang-summary-label">Tanggal Selesai</div><div>${fmtDate(k.tanggal_selesai)}</div></div>
      </div>
      <div><div class="ang-summary-label">Deskripsi</div><div style="margin-top:4px;color:#374151">${esc(k.deskripsi || '—')}</div></div>
      <div style="margin-top:16px">
        <div class="ang-summary-label" style="margin-bottom:8px">Anggota Tim</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          ${(k.anggota || []).map(a => `<span style="background:#eff6ff;color:#1e40af;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600">${esc(a.nama)}</span>`).join('')}
        </div>
      </div>
      ${['direktur','ketua_tim'].includes(currentUser.role) ? `
        <div style="margin-top:20px;display:flex;gap:10px">
          <button class="btn btn-outline btn-sm" onclick="showEditKegiatan(${JSON.stringify(k).replace(/"/g,'&quot;')})">Edit Kegiatan</button>
          ${currentUser.role === 'direktur' ? `<button class="btn btn-danger btn-sm" onclick="confirmDeleteKegiatan(${k.id},'${esc(k.nama_kegiatan)}')">Hapus</button>` : ''}
        </div>
      ` : ''}
    </div>

    <!-- Tab: Dokumen -->
    <div class="tab-content" id="tab-dokumen">
      <div id="dokumenTable-${k.id}">
        <div style="padding:20px;text-align:center;color:#94a3b8">Memuat...</div>
      </div>
    </div>
  `;

  // Tab switching — scoped to modal body
  const modalBody = document.getElementById('modalBody');
  modalBody.querySelectorAll('.tabs .tab').forEach(tab => {
    tab.addEventListener('click', () => {
      modalBody.querySelectorAll('.tabs .tab').forEach(t => t.classList.remove('active'));
      modalBody.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      modalBody.querySelector(`#tab-${tab.dataset.tab}`)?.classList.add('active');
      if (tab.dataset.tab === 'dokumen') loadDokumen(k.id);
    });
  });

  document.getElementById('modalFooter').innerHTML = '';
}

function taskItem(t, kegiatanId, users) {
  const done        = t.status === 'selesai';
  const statusClass = { selesai: 'task-selesai', sedang_berjalan: 'task-berjalan', belum_dimulai: 'task-belum' };
  const subtasks    = t.subtasks || [];
  const subDone     = subtasks.filter(s => s.status === 'selesai').length;
  const hasSubtasks = subtasks.length > 0;

  const subtaskHtml = hasSubtasks ? `
    <div class="subtask-list" id="subtask-list-${t.id}">
      ${subtasks.map(s => subtaskItem(s, kegiatanId, users)).join('')}
    </div>
  ` : `<div class="subtask-list" id="subtask-list-${t.id}"></div>`;

  const subtaskProgress = hasSubtasks ? `
    <div class="subtask-progress">
      <div class="subtask-progress-bar">
        <div class="subtask-progress-fill" style="width:${subDone > 0 ? Math.round((subDone/subtasks.length)*100) : 0}%"></div>
      </div>
      <span class="subtask-progress-text">${subDone}/${subtasks.length} subtask</span>
    </div>
  ` : '';

  const addSubtaskBtn = ['direktur','ketua_tim'].includes(currentUser.role) ? `
    <button class="btn-add-subtask" onclick="showAddSubtask(event, ${kegiatanId}, ${t.id}, ${JSON.stringify(users).replace(/"/g,'&quot;')})">
      + Subtask
    </button>
  ` : '';

  return `
    <div class="task-item task-item-parent" id="task-${t.id}">
      <div class="task-item-main">
        <div class="task-check ${done ? 'done' : ''}"
             onclick="toggleTaskStatus(${kegiatanId}, ${t.id}, '${t.status}', ${JSON.stringify(users).replace(/"/g,'&quot;')})">
          <svg viewBox="0 0 24 24" fill="white" width="12" height="12"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
        </div>
        <div style="flex:1;min-width:0">
          <div class="task-name ${done ? 'done' : ''}">${esc(t.nama_task)}</div>
          <div style="display:flex;gap:12px;margin-top:3px;flex-wrap:wrap">
            ${t.pic_nama ? `<span class="task-pic">👤 ${esc(t.pic_nama)}</span>` : ''}
            ${t.target_date ? `<span class="task-meta">📅 ${fmtDate(t.target_date)}</span>` : ''}
          </div>
          ${t.catatan ? `<div style="font-size:11.5px;color:#64748b;margin-top:3px">💬 ${esc(t.catatan)}</div>` : ''}
          ${subtaskProgress}
        </div>
        <span class="task-status-badge ${statusClass[t.status] || 'task-belum'}">${taskStatusLabel(t.status)}</span>
        ${['direktur','ketua_tim'].includes(currentUser.role) ? `
          <div style="display:flex;gap:4px">
            <button class="btn-icon" onclick="showEditTask(${kegiatanId}, ${t.id}, ${JSON.stringify(users).replace(/"/g,'&quot;')}, ${JSON.stringify(t).replace(/"/g,'&quot;')})">✏️</button>
            <button class="btn-icon" onclick="confirmDeleteTask(${kegiatanId}, ${t.id}, '${esc(t.nama_task)}')">🗑️</button>
          </div>
        ` : ''}
      </div>
      ${subtaskHtml}
      <div class="subtask-footer">${addSubtaskBtn}</div>
    </div>
  `;
}

function subtaskItem(s, kegiatanId, users) {
  const done        = s.status === 'selesai';
  const statusClass = { selesai: 'task-selesai', sedang_berjalan: 'task-berjalan', belum_dimulai: 'task-belum' };
  return `
    <div class="subtask-item" id="task-${s.id}">
      <div class="task-check task-check-sm ${done ? 'done' : ''}"
           onclick="toggleTaskStatus(${kegiatanId}, ${s.id}, '${s.status}', ${JSON.stringify(users).replace(/"/g,'&quot;')})">
        <svg viewBox="0 0 24 24" fill="white" width="10" height="10"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
      </div>
      <div style="flex:1;min-width:0">
        <div class="task-name ${done ? 'done' : ''}" style="font-size:13px">${esc(s.nama_task)}</div>
        <div style="display:flex;gap:10px;margin-top:2px;flex-wrap:wrap">
          ${s.pic_nama ? `<span class="task-pic" style="font-size:11px">👤 ${esc(s.pic_nama)}</span>` : ''}
          ${s.target_date ? `<span class="task-meta" style="font-size:11px">📅 ${fmtDate(s.target_date)}</span>` : ''}
        </div>
      </div>
      <span class="task-status-badge ${statusClass[s.status] || 'task-belum'}" style="font-size:10px">${taskStatusLabel(s.status)}</span>
      ${['direktur','ketua_tim'].includes(currentUser.role) ? `
        <div style="display:flex;gap:3px">
          <button class="btn-icon" style="padding:4px;font-size:12px" onclick="showEditSubtask(${kegiatanId}, ${s.id}, ${JSON.stringify(users).replace(/"/g,'&quot;')}, ${JSON.stringify(s).replace(/"/g,'&quot;')})">✏️</button>
          <button class="btn-icon" style="padding:4px;font-size:12px" onclick="confirmDeleteTask(${kegiatanId}, ${s.id}, '${esc(s.nama_task)}')">🗑️</button>
        </div>
      ` : ''}
    </div>
  `;
}

async function toggleTaskStatus(kegiatanId, taskId, currentStatus, users) {
  const newStatus = currentStatus === 'selesai' ? 'belum_dimulai'
                  : currentStatus === 'belum_dimulai' ? 'sedang_berjalan' : 'selesai';
  const res = await apiPut(`kegiatan/${kegiatanId}/tasks/${taskId}`, { status: newStatus });
  if (res && res.status) {
    showToast('Status task diperbarui', 'success');
    refreshCurrentModal();
  } else {
    showToast(res?.message || 'Gagal update status', 'error');
  }
}

function showAddSubtask(event, kegiatanId, parentTaskId, users) {
  event.stopPropagation();
  const userOptions = users.map(u => `<option value="${u.id}">${esc(u.nama)}</option>`).join('');
  openModal('Tambah Subtask', `
    <div class="form-group"><label>Nama Subtask *</label><input type="text" id="f_nama_task" placeholder="Nama subtask"></div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label>PIC (Penanggung Jawab)</label>
        <select id="f_pic_id"><option value="">— Pilih PIC —</option>${userOptions}</select>
      </div>
      <div class="form-group">
        <label>Deadline</label>
        <input type="date" id="f_target_date">
      </div>
    </div>
    <div class="form-group"><label>Catatan</label><textarea id="f_catatan" placeholder="Catatan opsional"></textarea></div>
  `, [
    { label: 'Batal', cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary', action: `saveSubtask(${kegiatanId}, ${parentTaskId})` },
  ]);
}

async function saveSubtask(kegiatanId, parentTaskId) {
  const data = {
    parent_task_id: parentTaskId,
    nama_task:      document.getElementById('f_nama_task')?.value?.trim(),
    pic_id:         document.getElementById('f_pic_id')?.value || null,
    target_date:    document.getElementById('f_target_date')?.value || null,
    catatan:        document.getElementById('f_catatan')?.value?.trim() || null,
  };
  if (!data.nama_task) { showToast('Nama subtask wajib diisi', 'error'); return; }

  const res = await apiPost(`kegiatan/${kegiatanId}/tasks`, data);
  if (res && res.status) {
    showToast('Subtask berhasil dibuat', 'success');
    closeModal();
    refreshCurrentModal();
  } else {
    showToast(res?.message || 'Gagal menyimpan subtask', 'error');
  }
}

function showEditSubtask(kegiatanId, taskId, users, task) {
  // Gunakan form edit task biasa (subtask = task dengan parent)
  showEditTask(kegiatanId, taskId, users, task);
}

function showAddTask(kegiatanId, users) {
  const userOptions = users.map(u => `<option value="${u.id}">${esc(u.nama)}</option>`).join('');
  openModal('Tambah Task', `
    <div class="form-group"><label>Nama Task *</label><input type="text" id="f_nama_task" placeholder="Nama task"></div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label>PIC (Penanggung Jawab)</label>
        <select id="f_pic_id"><option value="">— Pilih PIC —</option>${userOptions}</select>
      </div>
      <div class="form-group">
        <label>Deadline</label>
        <input type="date" id="f_target_date">
      </div>
    </div>
    <div class="form-group"><label>Catatan</label><textarea id="f_catatan" placeholder="Catatan opsional"></textarea></div>
  `, [
    { label: 'Batal', cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary', action: `saveTask(${kegiatanId}, null)` },
  ]);
}

function showEditTask(kegiatanId, taskId, users, task) {
  const userOptions = users.map(u => `<option value="${u.id}" ${u.id == task.pic_id ? 'selected' : ''}>${esc(u.nama)}</option>`).join('');
  openModal('Edit Task', `
    <div class="form-group"><label>Nama Task *</label><input type="text" id="f_nama_task" value="${esc(task.nama_task)}"></div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label>PIC</label>
        <select id="f_pic_id"><option value="">— Pilih PIC —</option>${userOptions}</select>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select id="f_status">
          <option value="belum_dimulai" ${task.status==='belum_dimulai'?'selected':''}>Belum Dimulai</option>
          <option value="sedang_berjalan" ${task.status==='sedang_berjalan'?'selected':''}>Sedang Berjalan</option>
          <option value="selesai" ${task.status==='selesai'?'selected':''}>Selesai</option>
        </select>
      </div>
      <div class="form-group">
        <label>Deadline</label>
        <input type="date" id="f_target_date" value="${task.target_date || ''}">
      </div>
    </div>
    <div class="form-group"><label>Catatan</label><textarea id="f_catatan">${esc(task.catatan || '')}</textarea></div>
  `, [
    { label: 'Batal', cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary', action: `saveTask(${kegiatanId}, ${taskId})` },
  ]);
}

async function saveTask(kegiatanId, taskId) {
  const data = {
    nama_task:   document.getElementById('f_nama_task')?.value?.trim(),
    pic_id:      document.getElementById('f_pic_id')?.value || null,
    target_date: document.getElementById('f_target_date')?.value || null,
    catatan:     document.getElementById('f_catatan')?.value?.trim() || null,
    status:      document.getElementById('f_status')?.value,
  };

  if (!data.nama_task) { showToast('Nama task wajib diisi', 'error'); return; }

  const res = taskId
    ? await apiPut(`kegiatan/${kegiatanId}/tasks/${taskId}`, data)
    : await apiPost(`kegiatan/${kegiatanId}/tasks`, data);

  if (res && res.status) {
    showToast(taskId ? 'Task berhasil diperbarui' : 'Task berhasil dibuat', 'success');
    closeModal();
    refreshCurrentModal();
  } else {
    showToast(res?.message || 'Gagal menyimpan task', 'error');
  }
}

async function confirmDeleteTask(kegiatanId, taskId, nama) {
  if (!confirm(`Hapus task "${nama}"?`)) return;
  const res = await apiDelete(`kegiatan/${kegiatanId}/tasks/${taskId}`);
  if (res && res.status) {
    showToast('Task berhasil dihapus', 'success');
    refreshCurrentModal();
  } else {
    showToast(res?.message || 'Gagal menghapus', 'error');
  }
}

// --- Kegiatan Form ---
async function showKegiatanForm(kegiatan, defaultTipe = 'on_budget') {
  const teamsRes = await apiGet('teams');
  const teams    = teamsRes?.data || [];
  const isEdit   = !!kegiatan;
  const tipe     = kegiatan?.tipe || defaultTipe;

  const teamOptions = teams.map(t =>
    `<option value="${t.id}" ${kegiatan?.team_id == t.id ? 'selected' : ''}>${esc(t.nama_tim)}</option>`
  ).join('');

  openModal(isEdit ? 'Edit Kegiatan' : 'Tambah Kegiatan', `
    <div class="form-group"><label>Nama Kegiatan *</label><input type="text" id="fk_nama" value="${esc(kegiatan?.nama_kegiatan || '')}"></div>
    <div class="form-group"><label>Deskripsi</label><textarea id="fk_deskripsi">${esc(kegiatan?.deskripsi || '')}</textarea></div>
    <div class="form-row form-row-2">
      <div class="form-group"><label>Tanggal Mulai *</label><input type="date" id="fk_mulai" value="${kegiatan?.tanggal_mulai || ''}"></div>
      <div class="form-group"><label>Tanggal Selesai *</label><input type="date" id="fk_selesai" value="${kegiatan?.tanggal_selesai || ''}"></div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group"><label>Pagu / Alokasi Anggaran *</label><input type="number" id="fk_pagu" value="${kegiatan?.pagu_anggaran || ''}"></div>
      <div class="form-group"><label>Tim *</label><select id="fk_team"><option value="">— Pilih Tim —</option>${teamOptions}</select></div>
    </div>
    ${!isEdit ? `
    <div class="form-group">
      <label>Tipe Kegiatan *</label>
      <select id="fk_tipe">
        <option value="on_budget" ${tipe==='on_budget'?'selected':''}>On Budget</option>
        <option value="off_budget" ${tipe==='off_budget'?'selected':''}>Off Budget</option>
      </select>
    </div>
    <div style="background:#f5f3ff;border:1px solid #c4b5fd;border-radius:6px;padding:10px 14px;font-size:12px;color:#6b21a8">
      💡 <strong>Off Budget:</strong> Sumber anggaran dapat dikelola setelah kegiatan dibuat melalui tombol <em>Kelola Sumber</em>.
    </div>` : ''}
    ${isEdit ? `
      <div class="form-group"><label>Status</label>
        <select id="fk_status">
          ${['draft','berjalan','selesai','terlambat'].map(s => `<option value="${s}" ${kegiatan.status===s?'selected':''}>${statusLabel(s)}</option>`).join('')}
        </select>
      </div>` : ''}
  `, [
    { label: 'Batal',  cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary', action: `saveKegiatan(${isEdit ? kegiatan.id : 'null'})` },
  ]);
}

function showEditKegiatan(k) {
  showKegiatanForm(k);
}

async function saveKegiatan(id) {
  const tipe = document.getElementById('fk_tipe')?.value || (id ? null : 'on_budget');
  const data = {
    nama_kegiatan:   document.getElementById('fk_nama')?.value?.trim(),
    deskripsi:       document.getElementById('fk_deskripsi')?.value?.trim(),
    tanggal_mulai:   document.getElementById('fk_mulai')?.value,
    tanggal_selesai: document.getElementById('fk_selesai')?.value,
    pagu_anggaran:   document.getElementById('fk_pagu')?.value,
    team_id:         document.getElementById('fk_team')?.value,
    status:          document.getElementById('fk_status')?.value,
  };
  if (tipe) data.tipe = tipe;

  if (!data.nama_kegiatan || !data.tanggal_mulai || !data.tanggal_selesai || !data.pagu_anggaran || !data.team_id) {
    showToast('Lengkapi semua field yang wajib', 'error'); return;
  }

  const res = id ? await apiPut(`kegiatan/${id}`, data) : await apiPost('kegiatan', data);
  if (res && res.status) {
    showToast(id ? 'Kegiatan diperbarui' : 'Kegiatan dibuat', 'success');
    closeModal();
    if (currentPage === 'off_budget') renderOffBudget();
    else renderKegiatan();
  } else {
    showToast(res?.message || 'Gagal menyimpan', 'error');
  }
}

async function confirmDeleteKegiatan(id, nama) {
  if (!confirm(`Hapus kegiatan "${nama}"? Semua task dan anggaran akan ikut dihapus.`)) return;
  const res = await apiDelete(`kegiatan/${id}`);
  if (res && res.status) {
    showToast('Kegiatan dihapus', 'success');
    closeModal();
    if (currentPage === 'off_budget') renderOffBudget();
    else renderKegiatan();
  } else showToast(res?.message || 'Gagal menghapus', 'error');
}

// ---------------------------------------------------------------
// Off Budget Page
// ---------------------------------------------------------------
async function renderOffBudget() {
  const res = await apiGet('off-budget');
  if (!res || !res.status) { showError('Gagal memuat data off-budget'); return; }

  const rows   = res.data || [];
  const canAdd = ['direktur', 'perencana_anggaran'].includes(currentUser.role);

  // Ringkasan total
  const totalPagu       = rows.reduce((s, k) => s + parseFloat(k.pagu_anggaran || 0), 0);
  const totalRealisasi  = rows.reduce((s, k) => s + parseFloat(k.total_realisasi || 0), 0);
  const totalSumber     = rows.reduce((s, k) => s + parseInt(k.jumlah_sumber || 0), 0);

  document.getElementById('mainContent').innerHTML = `
    <div class="section-header">
      <div class="section-title">Kegiatan Off Budget (${rows.length})</div>
      ${canAdd ? `<button class="btn btn-primary" onclick="showKegiatanForm(null,'off_budget')">
        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        Tambah Off Budget
      </button>` : ''}
    </div>

    ${rows.length > 0 ? `
    <div class="stats-grid" style="margin-bottom:20px">
      <div class="stat-card">
        <div class="stat-label">Total Kegiatan OB</div>
        <div class="stat-value">${rows.length}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Pagu / Alokasi</div>
        <div class="stat-value">${rupiahSingkat(totalPagu)}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Realisasi</div>
        <div class="stat-value" style="color:#16a34a">${rupiahSingkat(totalRealisasi)}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Sumber Terhubung</div>
        <div class="stat-value" style="color:#7c3aed">${totalSumber}</div>
      </div>
    </div>` : ''}

    ${rows.length === 0
      ? emptyState('Belum ada kegiatan off-budget')
      : `<div class="kegiatan-grid">
           ${rows.map(k => kegiatanCard(k, `showOffBudgetDetail(${k.id})`)).join('')}
         </div>`
    }
  `;
}

async function showOffBudgetDetail(id) {
  currentModalKegId = id;
  currentModalTipe  = 'off_budget';
  openModal('Memuat...', '<div class="loading-page"><div class="spinner-lg"></div></div>', [], 'modal-lg');

  const [res, usersRes] = await Promise.all([
    apiGet(`off-budget/${id}`),
    apiGet('users'),
  ]);

  if (!res || !res.status) { closeModal(); showError('Gagal memuat detail'); return; }

  const k       = res.data;
  const users   = usersRes?.data || [];
  const sr      = k.sumber_ringkasan || {};           // { sumber_list:[], total_alokasi:0 }
  const sumberList = sr.sumber_list || [];
  const canManageSumber = ['direktur','perencana_anggaran'].includes(currentUser.role);

  document.getElementById('modalTitle').textContent = `[OFF BUDGET] ${k.nama_kegiatan}`;

  document.getElementById('modalBody').innerHTML = `
    <!-- Ringkasan Sumber Anggaran (multi-sumber) -->
    <div style="background:#f5f3ff;border:1px solid #c4b5fd;border-radius:8px;padding:12px 16px;margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div style="font-size:12px;font-weight:700;color:#7c3aed">
          🔗 Sumber Anggaran (${sumberList.length} sumber)
        </div>
        ${canManageSumber ? `
          <button class="btn btn-sm" style="background:#7c3aed;color:#fff;font-size:11px;padding:3px 10px;border-radius:6px"
            onclick="showSumberManager(${k.id})">⚙ Kelola Sumber</button>
        ` : ''}
      </div>
      ${sumberList.length === 0
        ? `<div style="font-size:12px;color:#9ca3af;font-style:italic">Belum ada sumber anggaran. ${canManageSumber ? 'Klik "Kelola Sumber" untuk menambahkan.' : ''}</div>`
        : `<div class="table-wrap" style="margin:0">
            <table style="font-size:12px">
              <thead><tr><th>Kegiatan Sumber</th><th>Alokasi</th><th>Sumber Pagu</th><th>Sisa Sumber</th><th>Keterangan</th></tr></thead>
              <tbody>
                ${sumberList.map(s => {
                  const sisaSumber = parseFloat(s.sumber_pagu || 0) - parseFloat(s.sumber_total_realisasi || 0) - parseFloat(s.sumber_total_alokasi_ob || 0);
                  return `<tr>
                    <td style="font-weight:600">${esc(s.sumber_nama)}</td>
                    <td style="color:#7c3aed;font-weight:700">${rupiah(s.alokasi)}</td>
                    <td>${rupiah(s.sumber_pagu)}</td>
                    <td style="color:${sisaSumber >= 0 ? '#16a34a' : '#dc2626'}">${rupiah(sisaSumber)}</td>
                    <td style="color:#64748b">${esc(s.keterangan || '—')}</td>
                  </tr>`;
                }).join('')}
              </tbody>
              <tfoot><tr>
                <td style="font-weight:700">Total Alokasi</td>
                <td style="font-weight:700;color:#7c3aed" colspan="4">${rupiah(sr.total_alokasi || 0)}</td>
              </tr></tfoot>
            </table>
          </div>`
      }
    </div>

    <div class="tabs">
      <div class="tab active" data-tab="tasks">✅ Task (${k.tasks?.length || 0})</div>
      <div class="tab" data-tab="anggaran">💰 Anggaran</div>
      <div class="tab" data-tab="info">ℹ️ Info</div>
      <div class="tab" data-tab="dokumen">📎 Dokumen</div>
    </div>

    <!-- Tab: Tasks -->
    <div class="tab-content active" id="tab-tasks">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <span style="font-size:12px;color:#64748b">${k.tasks_selesai} dari ${k.total_tasks} task selesai</span>
        ${['direktur','ketua_tim'].includes(currentUser.role) ? `
          <button class="btn btn-primary btn-sm" onclick="showAddTask(${k.id}, ${JSON.stringify(users).replace(/"/g, '&quot;')})">+ Tambah Task</button>
        ` : ''}
      </div>
      <div class="progress-wrap" style="margin-bottom:16px">
        <div class="progress-bar">
          <div class="progress-fill progress-${k.warna_status || progressColor(k.progress)}" style="width:${k.progress}%"></div>
        </div>
        <div style="text-align:right;font-size:12px;color:#64748b;margin-top:4px">${k.progress}%</div>
      </div>
      <div class="task-list" id="taskList-${k.id}">
        ${(k.tasks || []).map(t => taskItem(t, k.id, users)).join('') || emptyState('Belum ada task')}
      </div>
    </div>

    <!-- Tab: Anggaran -->
    <div class="tab-content" id="tab-anggaran">
      <div class="anggaran-summary-grid">
        <div class="ang-summary-card">
          <div class="ang-summary-label">Alokasi Anggaran</div>
          <div class="ang-summary-value">${rupiah(k.pagu_anggaran)}</div>
        </div>
        <div class="ang-summary-card">
          <div class="ang-summary-label">Total Realisasi</div>
          <div class="ang-summary-value" style="color:#16a34a">${rupiah(k.total_realisasi)}</div>
        </div>
        <div class="ang-summary-card">
          <div class="ang-summary-label">Sisa Anggaran</div>
          <div class="ang-summary-value" style="color:#d97706">${rupiah(k.sisa_anggaran)}</div>
        </div>
        <div class="ang-summary-card">
          <div class="ang-summary-label">Persentase Serapan</div>
          <div class="ang-summary-value">${k.persentase_serapan}%</div>
        </div>
      </div>
      ${['direktur','ketua_tim'].includes(currentUser.role) ? `
        <button class="btn btn-primary btn-sm" style="margin-bottom:12px"
          onclick="showAddRealisasiOB(${k.id})">
          + Input Realisasi
        </button>
      ` : ''}
      <div class="table-wrap">
        <table>
          <thead><tr><th>Tanggal</th><th>Uraian</th><th>Sumber</th><th>Nominal</th><th>Oleh</th><th></th></tr></thead>
          <tbody id="realisasiBody-${k.id}">
            ${(k.realisasi || []).map(r => realisasiRowOB(r, k.id)).join('') || '<tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:20px">Belum ada realisasi</td></tr>'}
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab: Info -->
    <div class="tab-content" id="tab-info">
      <div class="form-row form-row-2" style="margin-bottom:12px">
        <div><div class="ang-summary-label">Nama Kegiatan</div><div>${esc(k.nama_kegiatan)}</div></div>
        <div><div class="ang-summary-label">Tipe</div><div><span style="background:#ede9fe;color:#7c3aed;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:700">Off Budget</span></div></div>
        <div><div class="ang-summary-label">Total Sumber</div><div style="color:#7c3aed;font-weight:600">${sumberList.length} sumber anggaran</div></div>
        <div><div class="ang-summary-label">Tim</div><div>${esc(k.nama_tim || '—')}</div></div>
        <div><div class="ang-summary-label">Ketua Tim</div><div>${esc(k.ketua_tim_nama || '—')}</div></div>
        <div><div class="ang-summary-label">Status</div><div><span class="status-badge status-${k.status}">${statusLabel(k.status)}</span></div></div>
        <div><div class="ang-summary-label">Tanggal Mulai</div><div>${fmtDate(k.tanggal_mulai)}</div></div>
        <div><div class="ang-summary-label">Tanggal Selesai</div><div>${fmtDate(k.tanggal_selesai)}</div></div>
      </div>
      <div><div class="ang-summary-label">Deskripsi</div><div style="margin-top:4px;color:#374151">${esc(k.deskripsi || '—')}</div></div>
      ${['direktur','ketua_tim'].includes(currentUser.role) ? `
        <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn btn-outline btn-sm" onclick="showEditKegiatan(${JSON.stringify(k).replace(/"/g,'&quot;')})">Edit</button>
          ${canManageSumber ? `<button class="btn btn-sm" style="background:#7c3aed;color:#fff" onclick="showSumberManager(${k.id})">⚙ Kelola Sumber</button>` : ''}
          ${currentUser.role === 'direktur' ? `<button class="btn btn-danger btn-sm" onclick="confirmDeleteKegiatan(${k.id},'${esc(k.nama_kegiatan)}')">Hapus</button>` : ''}
        </div>
      ` : ''}
    </div>

    <!-- Tab: Dokumen -->
    <div class="tab-content" id="tab-dokumen">
      <div id="dokumenTable-${k.id}">
        <div style="padding:20px;text-align:center;color:#94a3b8">Memuat...</div>
      </div>
    </div>
  `;

  initTabs(k.id);
  loadDokumen(k.id);
}


// ---------------------------------------------------------------
// Sumber Manager — Kelola sumber anggaran off-budget (multi-sumber)
// Hak akses: direktur + perencana_anggaran
// ---------------------------------------------------------------

async function showSumberManager(offBudgetId) {
  openModal('Memuat...', '<div class="loading-page"><div class="spinner-lg"></div></div>', [], 'modal-lg');
  await refreshSumberManagerContent(offBudgetId);
}

async function refreshSumberManagerContent(offBudgetId) {
  const [sumberRes, onBudgetRes] = await Promise.all([
    apiGet(`off-budget/${offBudgetId}/sumber`),
    apiGet('kegiatan/on-budget'),
  ]);

  const rows      = sumberRes?.data || [];
  const onBudget  = (onBudgetRes?.data || []).filter(k => k.id != offBudgetId);
  // Sumber yang sudah dipilih (hindari duplikasi)
  const usedIds   = new Set(rows.map(r => parseInt(r.sumber_id)));
  const available = onBudget.filter(k => !usedIds.has(k.id));

  document.getElementById('modalTitle').textContent = '⚙ Kelola Sumber Anggaran Off Budget';

  const sumberOptions = available.map(k =>
    `<option value="${k.id}">${esc(k.nama_kegiatan)} (Pagu: ${rupiahSingkat(k.pagu_anggaran)})</option>`
  ).join('');

  document.getElementById('modalBody').innerHTML = `
    <!-- Tabel sumber yang sudah ada -->
    <div style="margin-bottom:20px">
      <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:10px">
        Daftar Sumber Anggaran (${rows.length})
      </div>
      ${rows.length === 0
        ? `<div style="color:#9ca3af;font-size:12px;font-style:italic;padding:12px 0">Belum ada sumber. Tambahkan di bawah.</div>`
        : `<div class="table-wrap" style="margin:0">
            <table style="font-size:12px">
              <thead>
                <tr>
                  <th>Kegiatan Sumber</th>
                  <th>Alokasi</th>
                  <th>Sisa Sumber</th>
                  <th>Keterangan</th>
                  <th style="width:80px">Aksi</th>
                </tr>
              </thead>
              <tbody>
                ${rows.map(s => {
                  const sisaSumber = parseFloat(s.sumber_pagu || 0)
                    - parseFloat(s.sumber_total_realisasi || 0)
                    - parseFloat(s.sumber_total_alokasi_ob || 0);
                  return `
                  <tr id="sumber-row-${s.id}">
                    <td style="font-weight:600">${esc(s.sumber_nama)}</td>
                    <td>
                      <input type="number" id="sm_alokasi_${s.id}"
                        value="${s.alokasi}"
                        style="width:120px;padding:4px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px">
                    </td>
                    <td style="color:${sisaSumber >= 0 ? '#16a34a' : '#dc2626'}">${rupiah(sisaSumber)}</td>
                    <td>
                      <input type="text" id="sm_ket_${s.id}"
                        value="${esc(s.keterangan || '')}"
                        placeholder="Keterangan..."
                        style="width:140px;padding:4px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px">
                    </td>
                    <td style="white-space:nowrap">
                      <button class="btn btn-sm" style="background:#1d4ed8;color:#fff;font-size:11px;margin-right:4px"
                        onclick="saveSumber(${offBudgetId}, ${s.id})">💾</button>
                      <button class="btn btn-sm btn-danger" style="font-size:11px"
                        onclick="deleteSumber(${offBudgetId}, ${s.id}, '${esc(s.sumber_nama)}')">🗑</button>
                    </td>
                  </tr>`;
                }).join('')}
              </tbody>
            </table>
          </div>`
      }
    </div>

    <!-- Form tambah sumber baru -->
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:16px">
      <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:12px">+ Tambah Sumber Baru</div>
      ${available.length === 0
        ? `<div style="color:#9ca3af;font-size:12px;font-style:italic">Semua kegiatan on-budget sudah ditambahkan sebagai sumber.</div>`
        : `<div class="form-row form-row-2">
            <div class="form-group">
              <label>Kegiatan Sumber *</label>
              <select id="sm_new_sumber">
                <option value="">— Pilih Kegiatan —</option>
                ${sumberOptions}
              </select>
            </div>
            <div class="form-group">
              <label>Alokasi (Rp) *</label>
              <input type="number" id="sm_new_alokasi" placeholder="0">
            </div>
          </div>
          <div class="form-group">
            <label>Keterangan</label>
            <input type="text" id="sm_new_ket" placeholder="Opsional">
          </div>
          <button class="btn btn-primary btn-sm" onclick="addSumber(${offBudgetId})">+ Tambahkan Sumber</button>`
      }
    </div>
  `;

  document.getElementById('modalFooter').innerHTML = `
    <button class="btn btn-outline" onclick="closeModal()">Tutup</button>
  `;
}

async function addSumber(offBudgetId) {
  const sumber_id  = document.getElementById('sm_new_sumber')?.value;
  const alokasi    = document.getElementById('sm_new_alokasi')?.value;
  const keterangan = document.getElementById('sm_new_ket')?.value?.trim() || null;

  if (!sumber_id) { showToast('Pilih kegiatan sumber', 'error'); return; }
  if (!alokasi || parseFloat(alokasi) <= 0) { showToast('Masukkan alokasi yang valid', 'error'); return; }

  const res = await apiPost(`off-budget/${offBudgetId}/sumber`, { sumber_id, alokasi, keterangan });
  if (res && res.status) {
    showToast('Sumber berhasil ditambahkan', 'success');
    await refreshSumberManagerContent(offBudgetId);
  } else {
    showToast(res?.message || 'Gagal menambahkan sumber', 'error');
  }
}

async function saveSumber(offBudgetId, rowId) {
  const alokasi    = document.getElementById(`sm_alokasi_${rowId}`)?.value;
  const keterangan = document.getElementById(`sm_ket_${rowId}`)?.value?.trim() || null;

  if (!alokasi || parseFloat(alokasi) <= 0) { showToast('Alokasi tidak valid', 'error'); return; }

  const res = await apiPut(`off-budget/${offBudgetId}/sumber/${rowId}`, { alokasi, keterangan });
  if (res && res.status) {
    showToast('Sumber diperbarui', 'success');
    await refreshSumberManagerContent(offBudgetId);
  } else {
    showToast(res?.message || 'Gagal memperbarui', 'error');
  }
}

async function deleteSumber(offBudgetId, rowId, nama) {
  if (!confirm(`Hapus sumber "${nama}" dari kegiatan ini?`)) return;
  const res = await apiDelete(`off-budget/${offBudgetId}/sumber/${rowId}`);
  if (res && res.status) {
    showToast('Sumber dihapus', 'success');
    await refreshSumberManagerContent(offBudgetId);
  } else {
    showToast(res?.message || 'Gagal menghapus', 'error');
  }
}


// --- Realisasi Anggaran (On-Budget) ---
function showAddRealisasi(kegiatanId) {
  openModal('Input Realisasi Anggaran', `
    <div class="form-row form-row-2">
      <div class="form-group"><label>Tanggal *</label><input type="date" id="fr_tanggal" value="${todayWIB()}"></div>
      <div class="form-group"><label>Nominal (Rp) *</label><input type="number" id="fr_nominal" placeholder="0"></div>
    </div>
    <div class="form-group"><label>Uraian *</label><input type="text" id="fr_uraian" placeholder="Keterangan penggunaan anggaran"></div>
    <div class="form-group"><label>Bukti Transaksi (URL)</label><input type="text" id="fr_bukti" placeholder="https://..."></div>
  `, [
    { label: 'Batal',  cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary', action: `saveRealisasi(${kegiatanId}, null)` },
  ]);
}

async function saveRealisasi(kegiatanId, id) {
  const data = {
    tanggal:         document.getElementById('fr_tanggal')?.value,
    uraian:          document.getElementById('fr_uraian')?.value?.trim(),
    nominal:         document.getElementById('fr_nominal')?.value,
    bukti_transaksi: document.getElementById('fr_bukti')?.value?.trim() || null,
  };
  if (!data.tanggal || !data.uraian || !data.nominal) { showToast('Lengkapi field wajib', 'error'); return; }

  const res = id
    ? await apiPut(`kegiatan/${kegiatanId}/anggaran/${id}`, data)
    : await apiPost(`kegiatan/${kegiatanId}/anggaran`, data);

  if (res && res.status) {
    showToast('Realisasi berhasil disimpan', 'success');
    closeModal();
    refreshCardInGrid(kegiatanId);
    showKegiatanDetail(kegiatanId);
  } else {
    showToast(res?.message || 'Gagal menyimpan', 'error');
  }
}

function realisasiRow(r, kegiatanId) {
  // Mirror entry dari kegiatan off-budget — tampilkan badge OB, non-deletable
  const isMirror = !r.sumber_ob_id && r.ref_realisasi_id && r.mirror_dari_kegiatan;
  const mirrorBadge = isMirror
    ? `<span style="background:#ede9fe;color:#7c3aed;padding:1px 7px;border-radius:8px;font-size:10px;font-weight:700;margin-left:6px">OB</span>
       <div style="font-size:10px;color:#9ca3af;margin-top:2px">dari: ${esc(r.mirror_dari_kegiatan)}</div>`
    : '';
  return `
    <tr style="${isMirror ? 'background:#faf5ff' : ''}">
      <td>${fmtDate(r.tanggal)}</td>
      <td>
        ${esc(r.uraian)}
        ${mirrorBadge}
      </td>
      <td style="font-weight:600">${rupiah(r.nominal)}</td>
      <td>${esc(r.created_by_nama || '—')}</td>
      <td>
        ${!isMirror && currentUser.role === 'direktur' ? `
          <button class="btn-icon btn-sm" onclick="confirmDeleteRealisasi(${kegiatanId}, ${r.id}, '${esc(r.uraian)}')">🗑️</button>
        ` : (isMirror ? '<span title="Dihapus otomatis saat realisasi OB dihapus" style="color:#c4b5fd;font-size:12px">🔗</span>' : '')}
      </td>
    </tr>
  `;
}

async function confirmDeleteRealisasi(kegiatanId, id, uraian) {
  if (!confirm(`Hapus realisasi "${uraian}"?`)) return;
  const res = await apiDelete(`kegiatan/${kegiatanId}/anggaran/${id}`);
  if (res && res.status) {
    showToast('Realisasi dihapus', 'success');
    refreshCardInGrid(kegiatanId);
    showKegiatanDetail(kegiatanId);
  } else showToast(res?.message || 'Gagal', 'error');
}

// --- Realisasi Anggaran (Off-Budget) — dengan pilihan sumber ---

/**
 * Tampilkan form input realisasi khusus off-budget.
 * sumberList: array dari sumber_ringkasan.sumber_list (sudah ada di detail modal).
 * Form ditampilkan sebagai modal baru (modal standar), lalu setelah save kembali ke showOffBudgetDetail.
 */
// Cache sumber list untuk off-budget realisasi form (hindari passing via onclick attribute)
let _obSumberCache = [];   // cache sumber list untuk form realisasi OB

async function showAddRealisasiOB(kegiatanId) {
  openModal('Input Realisasi Anggaran (Off Budget)',
    '<div class="loading-page"><div class="spinner-lg"></div></div>', []);

  const res = await apiGet(`off-budget/${kegiatanId}/sumber`);
  const sumberList = res?.data || [];
  _obSumberCache = sumberList;

  if (sumberList.length === 0) {
    openModal('Input Realisasi Anggaran (Off Budget)', `
      <div style="text-align:center;padding:24px">
        <div style="font-size:32px;margin-bottom:12px">⚠️</div>
        <div style="font-weight:600;margin-bottom:8px">Belum Ada Sumber Anggaran</div>
        <div style="font-size:13px;color:#64748b">
          Tambahkan sumber anggaran terlebih dahulu melalui tombol <strong>⚙ Kelola Sumber</strong>.
        </div>
      </div>
    `, [{ label: 'Tutup', cls: 'btn-outline', action: 'closeModal()' }]);
    return;
  }

  // Hitung sisa alokasi per sumber dari cache
  const totalSisa = sumberList.reduce((s, x) => s + getSisaAlokasi(x), 0);

  openModal('Input Realisasi Anggaran (Off Budget)', `
    <div class="form-row form-row-2">
      <div class="form-group">
        <label>Tanggal *</label>
        <input type="date" id="frob_tanggal" value="${todayWIB()}">
      </div>
      <div class="form-group">
        <label>Total Nominal (Rp) *</label>
        <input type="number" id="frob_nominal_total" placeholder="0" min="1"
          oninput="autoDistribusiOB()"
          style="font-weight:700;font-size:15px">
        <div style="font-size:11px;color:#64748b;margin-top:3px">
          Maks tersedia: <strong>${rupiahSingkat(totalSisa)}</strong>
        </div>
      </div>
    </div>
    <div class="form-group">
      <label>Uraian *</label>
      <input type="text" id="frob_uraian" placeholder="Keterangan penggunaan anggaran">
    </div>

    <!-- Tabel distribusi per sumber -->
    <div class="form-group">
      <label style="display:flex;justify-content:space-between;align-items:center">
        <span>Distribusi per Sumber Anggaran</span>
        <button type="button" class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 10px"
          onclick="autoDistribusiOB()">🔄 Auto Distribusi</button>
      </label>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-top:6px">
        <table style="width:100%;font-size:12px;border-collapse:collapse">
          <thead>
            <tr style="background:#f1f5f9">
              <th style="padding:8px 10px;text-align:left;font-weight:600">Sumber Anggaran</th>
              <th style="padding:8px 10px;text-align:right;font-weight:600">Sisa Alokasi</th>
              <th style="padding:8px 10px;text-align:right;font-weight:600;width:160px">Nominal Dipakai *</th>
            </tr>
          </thead>
          <tbody>
            ${sumberList.map(s => {
              const sisa = getSisaAlokasi(s);
              return `
              <tr style="border-top:1px solid #e2e8f0">
                <td style="padding:8px 10px">
                  <div style="font-weight:600">${esc(s.sumber_nama)}</div>
                  <div style="color:#9ca3af;font-size:10px">Alokasi: ${rupiahSingkat(s.alokasi)}</div>
                </td>
                <td style="padding:8px 10px;text-align:right;color:${sisa > 0 ? '#16a34a' : '#dc2626'};font-weight:600">
                  ${rupiahSingkat(sisa)}
                </td>
                <td style="padding:6px 10px">
                  <input type="number" id="frob_split_${s.id}"
                    data-sisa="${sisa}" data-id="${s.id}"
                    value="0" min="0" max="${sisa}" placeholder="0"
                    oninput="updateSplitTotal()"
                    style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;text-align:right;font-size:13px;font-weight:600">
                </td>
              </tr>`;
            }).join('')}
          </tbody>
          <tfoot>
            <tr style="border-top:2px solid #e2e8f0;background:#f8fafc">
              <td colspan="2" style="padding:8px 10px;font-weight:700;font-size:13px">Total Split</td>
              <td style="padding:8px 10px;text-align:right">
                <span id="frob_split_total" style="font-weight:700;font-size:14px;color:#1e40af">Rp 0</span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div id="frob_split_warning" style="margin-top:6px;font-size:12px;color:#dc2626;font-weight:600;min-height:16px"></div>
    </div>

    <div class="form-group">
      <label>Bukti Transaksi (URL)</label>
      <input type="text" id="frob_bukti" placeholder="https://...">
    </div>
  `, [
    { label: 'Batal',  cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary',  action: `saveRealisasiOB(${kegiatanId})` },
  ]);
}

/** Hitung sisa alokasi sumber dari cache fields */
function getSisaAlokasi(s) {
  return Math.max(0,
    parseFloat(s.alokasi || 0)
    - parseFloat(s.sumber_total_realisasi || 0)
    - parseFloat(s.sumber_total_alokasi_ob || 0)
  );
}

/** Auto-distribusi nominal total ke sumber secara greedy (isi sumber pertama dulu) */
function autoDistribusiOB() {
  let remaining = parseFloat(document.getElementById('frob_nominal_total')?.value) || 0;
  _obSumberCache.forEach(s => {
    const sisa  = getSisaAlokasi(s);
    const input = document.getElementById(`frob_split_${s.id}`);
    if (!input) return;
    const take = Math.min(remaining, sisa);
    input.value = take > 0 ? take : 0;
    remaining  -= take;
  });
  updateSplitTotal();
}

/** Update tampilan total split + warning */
function updateSplitTotal() {
  const totalEl   = document.getElementById('frob_split_total');
  const warnEl    = document.getElementById('frob_split_warning');
  const targetEl  = document.getElementById('frob_nominal_total');
  if (!totalEl) return;

  let total = 0;
  _obSumberCache.forEach(s => {
    const v = parseFloat(document.getElementById(`frob_split_${s.id}`)?.value) || 0;
    total += v;
  });
  totalEl.textContent = 'Rp ' + rupiahSingkat(total);

  const target = parseFloat(targetEl?.value) || 0;
  if (!warnEl) return;
  if (target > 0 && Math.abs(total - target) > 1) {
    warnEl.textContent = `⚠ Total split Rp ${rupiahSingkat(total)} ≠ nominal Rp ${rupiahSingkat(target)} (selisih: Rp ${rupiahSingkat(Math.abs(total - target))})`;
  } else if (total > 0) {
    warnEl.textContent = '';
  }
}

async function saveRealisasiOB(kegiatanId) {
  const tanggal = document.getElementById('frob_tanggal')?.value;
  const uraian  = document.getElementById('frob_uraian')?.value?.trim();
  const nominal = parseFloat(document.getElementById('frob_nominal_total')?.value) || 0;
  const bukti   = document.getElementById('frob_bukti')?.value?.trim() || null;

  if (!tanggal) { showToast('Tanggal wajib diisi', 'error'); return; }
  if (!uraian)  { showToast('Uraian wajib diisi', 'error'); return; }
  if (nominal <= 0) { showToast('Nominal harus lebih dari 0', 'error'); return; }

  // Kumpulkan splits dari tabel
  const splits = [];
  let totalSplit = 0;
  _obSumberCache.forEach(s => {
    const v = parseFloat(document.getElementById(`frob_split_${s.id}`)?.value) || 0;
    if (v > 0) {
      splits.push({ sumber_ob_id: parseInt(s.id), nominal: v });
      totalSplit += v;
    }
  });

  if (splits.length === 0) {
    showToast('Masukkan nominal pada minimal satu sumber anggaran', 'error'); return;
  }
  if (Math.abs(totalSplit - nominal) > 1) {
    showToast(`Total split (${rupiahSingkat(totalSplit)}) tidak sama dengan nominal (${rupiahSingkat(nominal)})`, 'error');
    return;
  }

  const data = { tanggal, uraian, nominal, bukti_transaksi: bukti, splits };
  const res  = await apiPost(`kegiatan/${kegiatanId}/anggaran`, data);
  if (res && res.status) {
    showToast(res.message || 'Realisasi berhasil disimpan', 'success');
    closeModal();
    showOffBudgetDetail(kegiatanId);
  } else {
    showToast(res?.message || 'Gagal menyimpan', 'error');
  }
}

/** Baris tabel realisasi untuk off-budget (tampilkan kolom sumber) */
function realisasiRowOB(r, kegiatanId) {
  const sumberBadge = r.sumber_nama_kegiatan
    ? `<span style="background:#ede9fe;color:#7c3aed;padding:1px 7px;border-radius:8px;font-size:11px;font-weight:600">${esc(r.sumber_nama_kegiatan)}</span>`
    : '<span style="color:#94a3b8;font-size:11px">—</span>';
  return `
    <tr>
      <td>${fmtDate(r.tanggal)}</td>
      <td>${esc(r.uraian)}</td>
      <td>${sumberBadge}</td>
      <td style="font-weight:600">${rupiah(r.nominal)}</td>
      <td>${esc(r.created_by_nama || '—')}</td>
      <td>
        ${currentUser.role === 'direktur' ? `
          <button class="btn-icon btn-sm" onclick="confirmDeleteRealisasiOB(${kegiatanId}, ${r.id}, '${esc(r.uraian)}')">🗑️</button>
        ` : ''}
      </td>
    </tr>
  `;
}

async function confirmDeleteRealisasiOB(kegiatanId, id, uraian) {
  if (!confirm(`Hapus realisasi "${uraian}"?\n\nEntri sumber anggaran yang terhubung juga akan dihapus.`)) return;
  const res = await apiDelete(`kegiatan/${kegiatanId}/anggaran/${id}`);
  if (res && res.status) {
    showToast('Realisasi dihapus', 'success');
    showOffBudgetDetail(kegiatanId);
  } else {
    showToast(res?.message || 'Gagal', 'error');
  }
}

// --- Dokumen ---
// loadDokumen, saveDokumen, deleteDokumen — didefinisikan di kegiatan.js

// ---------------------------------------------------------------
// LAPORAN
// ---------------------------------------------------------------
async function renderLaporan() {
  document.getElementById('mainContent').innerHTML = `
    <div style="display:flex;gap:10px;margin-bottom:20px">
      <button class="btn btn-primary" id="btnLaporanProgress" onclick="loadLaporanProgress()">📊 Progress</button>
      <button class="btn btn-outline" id="btnLaporanAnggaran" onclick="loadLaporanAnggaran()">💰 Anggaran</button>
    </div>
    <div id="laporanContent"></div>
  `;
  loadLaporanProgress();
}

async function loadLaporanProgress() {
  document.getElementById('btnLaporanProgress').className = 'btn btn-primary';
  document.getElementById('btnLaporanAnggaran').className = 'btn btn-outline';
  document.getElementById('laporanContent').innerHTML = '<div class="loading-page"><div class="spinner-lg"></div></div>';

  const res = await apiGet('laporan/progress');
  if (!res || !res.status) { showError('Gagal memuat laporan'); return; }
  const rows = res.data.rows;

  document.getElementById('laporanContent').innerHTML = `
    <div class="section-header">
      <div class="section-title">Laporan Progress Kegiatan</div>
      <button class="btn btn-outline btn-sm" onclick="exportTableToCSV('tblProgress','laporan_progress')">⬇️ Export CSV</button>
    </div>
    <div class="table-wrap">
      <table id="tblProgress">
        <thead>
          <tr>
            <th>No</th><th>Nama Kegiatan</th><th>Ketua Tim</th>
            <th>Progress</th><th>Task Selesai</th><th>Task Total</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          ${rows.map((r, i) => `
            <tr>
              <td>${i+1}</td>
              <td>${esc(r.nama_kegiatan)}</td>
              <td>${esc(r.ketua_tim_nama || '—')}</td>
              <td>
                <div class="progress-bar" style="max-width:120px;display:inline-block;vertical-align:middle;margin-right:6px">
                  <div class="progress-fill progress-${progressColor(r.progress)}" style="width:${r.progress}%"></div>
                </div>
                ${r.progress}%
              </td>
              <td>${r.tasks_selesai}</td>
              <td>${r.total_tasks}</td>
              <td><span class="status-badge status-${r.status}">${statusLabel(r.status)}</span></td>
            </tr>
          `).join('') || '<tr><td colspan="7" style="text-align:center;padding:24px;color:#94a3b8">Tidak ada data</td></tr>'}
        </tbody>
      </table>
    </div>
  `;
}

async function loadLaporanAnggaran() {
  document.getElementById('btnLaporanProgress').className = 'btn btn-outline';
  document.getElementById('btnLaporanAnggaran').className = 'btn btn-primary';
  document.getElementById('laporanContent').innerHTML = '<div class="loading-page"><div class="spinner-lg"></div></div>';

  const res = await apiGet('laporan/anggaran');
  if (!res || !res.status) { showError('Gagal memuat laporan'); return; }
  const { rows, totals } = res.data;

  document.getElementById('laporanContent').innerHTML = `
    <div class="section-header">
      <div class="section-title">Laporan Anggaran Kegiatan</div>
      <button class="btn btn-outline btn-sm" onclick="exportTableToCSV('tblAnggaran','laporan_anggaran')">⬇️ Export CSV</button>
    </div>
    <div class="stats-grid" style="margin-bottom:16px">
      <div class="stat-card blue"><div class="stat-label">Total Pagu</div><div class="stat-value" style="font-size:18px">${rupiah(totals.total_pagu)}</div></div>
      <div class="stat-card green"><div class="stat-label">Total Realisasi</div><div class="stat-value" style="font-size:18px">${rupiah(totals.total_realisasi)}</div></div>
      <div class="stat-card yellow"><div class="stat-label">Total Sisa</div><div class="stat-value" style="font-size:18px">${rupiah(totals.total_sisa)}</div></div>
      <div class="stat-card blue"><div class="stat-label">Serapan</div><div class="stat-value">${totals.persentase_serapan}%</div></div>
    </div>
    <div class="table-wrap">
      <table id="tblAnggaran">
        <thead>
          <tr>
            <th>No</th><th>Nama Kegiatan</th><th>Tim</th>
            <th>Pagu Anggaran</th><th>Realisasi</th><th>Sisa</th><th>Serapan</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          ${rows.map((r, i) => `
            <tr>
              <td>${i+1}</td>
              <td>${esc(r.nama_kegiatan)}</td>
              <td>${esc(r.nama_tim || '—')}</td>
              <td>${rupiah(r.pagu_anggaran)}</td>
              <td>${rupiah(r.total_realisasi)}</td>
              <td>${rupiah(r.sisa_anggaran)}</td>
              <td>${r.persentase_serapan}%</td>
              <td><span class="status-badge status-${r.status}">${statusLabel(r.status)}</span></td>
            </tr>
          `).join('') || '<tr><td colspan="8" style="text-align:center;padding:24px;color:#94a3b8">Tidak ada data</td></tr>'}
        </tbody>
      </table>
    </div>
  `;
}

// ---------------------------------------------------------------
// USERS
// ---------------------------------------------------------------
async function renderUsers() {
  const res = await apiGet('users');
  if (!res || !res.status) { showError('Gagal memuat pengguna'); return; }
  const rows = res.data;

  document.getElementById('mainContent').innerHTML = `
    <div class="section-header">
      <div class="section-title">Manajemen Pengguna (${rows.length})</div>
      <button class="btn btn-primary" onclick="showUserForm()">+ Tambah Pengguna</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nama</th><th>Email</th><th>Jabatan</th><th>Role</th><th>Status</th><th></th></tr></thead>
        <tbody>
          ${rows.map(u => `
            <tr>
              <td style="font-weight:600">${esc(u.nama)}</td>
              <td>${esc(u.email)}</td>
              <td>${esc(u.jabatan || '—')}</td>
              <td><span class="status-badge ${roleBadge(u.role)}">${roleLabel(u.role)}</span></td>
              <td><span class="status-badge ${u.status==='aktif'?'status-selesai':'status-terlambat'}">${u.status}</span></td>
              <td><button class="btn-icon" onclick="showUserForm(${JSON.stringify(u).replace(/"/g,'&quot;')})">✏️</button></td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
}

function showUserForm(user) {
  const isEdit = !!user;
  openModal(isEdit ? 'Edit Pengguna' : 'Tambah Pengguna', `
    <div class="form-row form-row-2">
      <div class="form-group"><label>Nama *</label><input type="text" id="fu_nama" value="${esc(user?.nama || '')}"></div>
      <div class="form-group"><label>Email *</label><input type="email" id="fu_email" value="${esc(user?.email || '')}"></div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group"><label>Jabatan</label><input type="text" id="fu_jabatan" value="${esc(user?.jabatan || '')}"></div>
      <div class="form-group"><label>Role *</label>
        <select id="fu_role">
          ${['direktur','ketua_tim','anggota_tim','perencana_anggaran'].map(r => `<option value="${r}" ${user?.role===r?'selected':''}>${roleLabel(r)}</option>`).join('')}
        </select>
      </div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group"><label>${isEdit ? 'Password Baru (kosongkan jika tidak diubah)' : 'Password *'}</label><input type="password" id="fu_password"></div>
      ${isEdit ? `<div class="form-group"><label>Status</label>
        <select id="fu_status">
          <option value="aktif" ${user?.status==='aktif'?'selected':''}>Aktif</option>
          <option value="nonaktif" ${user?.status==='nonaktif'?'selected':''}>Nonaktif</option>
        </select>
      </div>` : ''}
    </div>
  `, [
    { label: 'Batal', cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary', action: `saveUser(${isEdit ? user.id : 'null'})` },
  ]);
}

async function saveUser(id) {
  const data = {
    nama:     document.getElementById('fu_nama')?.value?.trim(),
    email:    document.getElementById('fu_email')?.value?.trim(),
    jabatan:  document.getElementById('fu_jabatan')?.value?.trim(),
    role:     document.getElementById('fu_role')?.value,
    status:   document.getElementById('fu_status')?.value,
    password: document.getElementById('fu_password')?.value,
  };
  if (!data.nama || !data.email) { showToast('Nama dan email wajib diisi', 'error'); return; }

  const res = id ? await apiPut(`users/${id}`, data) : await apiPost('users', data);
  if (res && res.status) { showToast(id ? 'Pengguna diperbarui' : 'Pengguna dibuat', 'success'); closeModal(); renderUsers(); }
  else showToast(res?.message || 'Gagal menyimpan', 'error');
}

// ---------------------------------------------------------------
// TEAMS
// ---------------------------------------------------------------
async function renderTeams() {
  const [teamsRes, usersRes] = await Promise.all([apiGet('teams'), apiGet('users')]);
  const teams = teamsRes?.data || [];
  const users = usersRes?.data || [];

  document.getElementById('mainContent').innerHTML = `
    <div class="section-header">
      <div class="section-title">Manajemen Tim (${teams.length})</div>
      <button class="btn btn-primary" onclick="showTeamForm(null, ${JSON.stringify(users).replace(/"/g,'&quot;')})">+ Tambah Tim</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nama Tim</th><th>Ketua Tim</th><th>Anggota</th><th></th></tr></thead>
        <tbody>
          ${teams.map(t => `
            <tr>
              <td style="font-weight:600">${esc(t.nama_tim)}</td>
              <td>${esc(t.ketua_tim_nama || '—')}</td>
              <td>${t.jumlah_anggota || 0} orang</td>
              <td style="display:flex;gap:4px">
                <button class="btn-icon" onclick="showTeamDetail(${t.id})">👁️</button>
                <button class="btn-icon" onclick="showTeamForm(${JSON.stringify(t).replace(/"/g,'&quot;')}, ${JSON.stringify(users).replace(/"/g,'&quot;')})">✏️</button>
                <button class="btn-icon" onclick="confirmDeleteTeam(${t.id},'${esc(t.nama_tim)}')">🗑️</button>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
}

async function showTeamDetail(id) {
  const res = await apiGet(`teams/${id}`);
  if (!res || !res.status) return;
  const team = res.data;
  openModal(team.nama_tim, `
    <div style="margin-bottom:12px"><b>Ketua:</b> ${esc(team.ketua_tim_nama || '—')}</div>
    <div style="font-weight:600;margin-bottom:8px">Anggota Tim:</div>
    ${team.anggota.map(a => `
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9">
        <span>${esc(a.nama)}</span>
        <span class="status-badge ${roleBadge(a.role)}">${roleLabel(a.role)}</span>
      </div>
    `).join('')}
  `, [{ label: 'Tutup', cls: 'btn-outline', action: 'closeModal()' }]);
}

function showTeamForm(team, users) {
  const isEdit    = !!team;
  const ketuaOpts = users.filter(u => ['direktur','ketua_tim'].includes(u.role))
                         .map(u => `<option value="${u.id}" ${team?.ketua_tim_id==u.id?'selected':''}>${esc(u.nama)}</option>`).join('');
  const anggOpts  = users.map(u => `<option value="${u.id}">${esc(u.nama)}</option>`).join('');

  openModal(isEdit ? 'Edit Tim' : 'Tambah Tim', `
    <div class="form-group"><label>Nama Tim *</label><input type="text" id="ft_nama" value="${esc(team?.nama_tim || '')}"></div>
    <div class="form-group"><label>Ketua Tim *</label><select id="ft_ketua"><option value="">— Pilih —</option>${ketuaOpts}</select></div>
    <div class="form-group">
      <label>Anggota Tim (Ctrl+Click untuk multi)</label>
      <select id="ft_anggota" multiple style="min-height:120px">${anggOpts}</select>
    </div>
  `, [
    { label: 'Batal', cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary', action: `saveTeam(${isEdit ? team.id : 'null'})` },
  ]);
}

async function saveTeam(id) {
  const anggota = Array.from(document.getElementById('ft_anggota').selectedOptions).map(o => o.value);
  const data = {
    nama_tim:     document.getElementById('ft_nama')?.value?.trim(),
    ketua_tim_id: document.getElementById('ft_ketua')?.value,
    anggota,
  };
  if (!data.nama_tim || !data.ketua_tim_id) { showToast('Nama tim dan ketua wajib diisi', 'error'); return; }

  const res = id ? await apiPut(`teams/${id}`, data) : await apiPost('teams', data);
  if (res && res.status) { showToast(id ? 'Tim diperbarui' : 'Tim dibuat', 'success'); closeModal(); renderTeams(); }
  else showToast(res?.message || 'Gagal menyimpan', 'error');
}

async function confirmDeleteTeam(id, nama) {
  if (!confirm(`Hapus tim "${nama}"?`)) return;
  const res = await apiDelete(`teams/${id}`);
  if (res && res.status) { showToast('Tim dihapus', 'success'); renderTeams(); }
  else showToast(res?.message || 'Gagal', 'error');
}

// ---------------------------------------------------------------
// AUDIT TRAIL
// ---------------------------------------------------------------
async function renderAudit() {
  let page = 1;
  await loadAuditPage(page);
}

async function loadAuditPage(page = 1) {
  document.getElementById('mainContent').innerHTML = '<div class="loading-page"><div class="spinner-lg"></div></div>';
  const res = await apiGet(`audit-log?page=${page}`);
  if (!res || !res.status) { showError('Gagal memuat audit log'); return; }
  const { rows, total, total_pages } = res.data;

  document.getElementById('mainContent').innerHTML = `
    <div class="section-header">
      <div class="section-title">Audit Trail (${total} entri)</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Role</th><th>Aktivitas</th><th>Tabel</th><th>IP</th></tr></thead>
        <tbody>
          ${rows.map(r => `
            <tr>
              <td style="white-space:nowrap;font-size:12px">${r.waktu || '—'}</td>
              <td>${esc(r.user_nama || '—')}</td>
              <td><span class="status-badge ${roleBadge(r.user_role)}">${roleLabel(r.user_role)}</span></td>
              <td>${esc(r.aktivitas)}</td>
              <td><span style="font-size:11px;background:#f1f5f9;padding:2px 8px;border-radius:4px">${r.tabel_terkait || '—'}</span></td>
              <td style="font-size:12px">${r.ip_address || '—'}</td>
            </tr>
          `).join('') || '<tr><td colspan="6" style="text-align:center;padding:24px;color:#94a3b8">Tidak ada data</td></tr>'}
        </tbody>
      </table>
    </div>
    ${total_pages > 1 ? `
      <div style="display:flex;justify-content:center;gap:8px;margin-top:16px">
        ${Array.from({length: total_pages}, (_, i) => `
          <button class="btn btn-sm ${i+1===page?'btn-primary':'btn-outline'}" onclick="loadAuditPage(${i+1})">${i+1}</button>
        `).join('')}
      </div>
    ` : ''}
  `;
}

// ---------------------------------------------------------------
// RPD Page
// ---------------------------------------------------------------
// Label bulan untuk tampilan
const BULAN_LABEL = ['Januari','Februari','Maret','April','Mei','Juni',
                     'Juli','Agustus','September','Oktober','November','Desember'];
const BULAN_SHORT = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];

async function renderRpd() {
  const tahun = new Date().getFullYear();
  const [res, kegRes] = await Promise.all([
    apiGet(`rpd?tahun=${tahun}`),
    apiGet('kegiatan'),
  ]);
  const rows    = res?.data || [];
  const kegList = kegRes?.data || [];
  const canEdit = ['direktur','perencana_anggaran'].includes(currentUser.role);

  // Header kolom bulan
  const thBulan = BULAN_SHORT.map(b => `<th>${b}</th>`).join('');

  document.getElementById('mainContent').innerHTML = `
    <div class="section-header">
      <div class="section-title">Rencana Penarikan Dana (RPD) — ${tahun}</div>
      ${canEdit ? `<button class="btn btn-primary" onclick="showRpdForm(${tahun})">✏️ Input / Edit RPD</button>` : ''}
    </div>
    <div class="rpd-table-wrap">
      <div style="overflow-x:auto">
      <table class="rpd-table">
        <thead>
          <tr class="rpd-header-yellow">
            <th rowspan="2" style="vertical-align:middle;min-width:160px">KEGIATAN</th>
            <th rowspan="2" style="vertical-align:middle">PAGU<br>ANGGARAN</th>
            <th colspan="12" style="text-align:center;background:#d97706;color:#fff">RENCANA PENARIKAN PER BULAN (Rp)</th>
            <th rowspan="2" style="vertical-align:middle">TOTAL RPD</th>
            ${canEdit ? '<th rowspan="2"></th>' : ''}
          </tr>
          <tr class="rpd-header-yellow">
            ${thBulan}
          </tr>
        </thead>
        <tbody>
          ${kegList.map(k => {
            const r = rows.find(x => x.kegiatan_id == k.id) || {};
            const pads = ['01','02','03','04','05','06','07','08','09','10','11','12'];
            const total = pads.reduce((s,p) => s + (parseFloat(r['bln_'+p])||0), 0);
            return `
              <tr>
                <td style="font-weight:600">${esc(k.nama_kegiatan)}</td>
                <td class="rpd-num">${rupiahSingkat(r.pagu_anggaran || k.pagu_anggaran || 0)}</td>
                ${pads.map(p => `<td class="rpd-num">${rupiahSingkat(r['bln_'+p]||0)}</td>`).join('')}
                <td class="rpd-num rpd-total-col">${rupiahSingkat(total)}</td>
                ${canEdit ? `<td><button class="btn-icon" title="Edit RPD" onclick="showRpdForm(${tahun}, ${k.id}, ${JSON.stringify(r).replace(/"/g,'&quot;')})">✏️</button></td>` : ''}
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
      </div>
    </div>
  `;
}

async function showRpdForm(tahun, kegiatanId = null, existing = null) {
  const kegRes  = await apiGet('kegiatan');
  const kegList = kegRes?.data || [];
  const kegOpts = kegList.map(k =>
    `<option value="${k.id}" ${kegiatanId == k.id ? 'selected' : ''}>${esc(k.nama_kegiatan)}</option>`
  ).join('');

  // Input 12 bulan dalam grid 3 kolom x 4 baris
  const pads = ['01','02','03','04','05','06','07','08','09','10','11','12'];
  const bulanInputs = `
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:4px">
      ${pads.map((p,i) => `
        <div class="form-group" style="margin:0">
          <label style="font-size:12px">${BULAN_LABEL[i]}</label>
          <input type="number" id="frpd_bln_${p}" value="${existing?.['bln_'+p] || 0}" min="0" style="font-size:13px">
        </div>
      `).join('')}
    </div>
  `;

  openModal(`Input RPD ${tahun}`, `
    <div class="form-group">
      <label>Kegiatan *</label>
      <select id="frpd_kegiatan">${kegOpts}</select>
    </div>
    <div class="form-group">
      <label>Pagu Anggaran (Rp) *</label>
      <input type="number" id="frpd_pagu" value="${existing?.pagu_anggaran || ''}" placeholder="0">
    </div>
    <div class="form-group">
      <label>Rencana Penarikan Dana Per Bulan (Rp)</label>
      ${bulanInputs}
    </div>
    <div class="form-group"><label>Keterangan</label><textarea id="frpd_ket">${esc(existing?.keterangan || '')}</textarea></div>
  `, [
    { label: 'Batal', cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary', action: `saveRpd(${tahun})` },
  ]);
}

async function saveRpd(tahun) {
  const pads = ['01','02','03','04','05','06','07','08','09','10','11','12'];
  const data = {
    tahun_anggaran: tahun,
    kegiatan_id:    document.getElementById('frpd_kegiatan')?.value,
    pagu_anggaran:  document.getElementById('frpd_pagu')?.value || 0,
    keterangan:     document.getElementById('frpd_ket')?.value?.trim() || null,
  };
  pads.forEach(p => {
    data['bln_' + p] = parseFloat(document.getElementById('frpd_bln_' + p)?.value) || 0;
  });
  if (!data.kegiatan_id || !data.pagu_anggaran) {
    showToast('Kegiatan dan pagu wajib diisi', 'error'); return;
  }
  const res = await apiPost('rpd', data);
  if (res && res.status) {
    showToast('RPD berhasil disimpan', 'success');
    closeModal();
    if (currentPage === 'rpd') renderRpd();
    else renderDashboard();
  } else {
    showToast(res?.message || 'Gagal menyimpan RPD', 'error');
  }
}

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------
/**
 * initTabs(kegiatanId) — pasang event listener tab switching pada modal body.
 * Dipakai oleh showOffBudgetDetail (dan bisa dipakai modal lain yang punya .tabs).
 * Harus dipanggil SETELAH innerHTML modal di-set.
 */
function initTabs(kegiatanId) {
  const modalBody = document.getElementById('modalBody');
  if (!modalBody) return;
  modalBody.querySelectorAll('.tabs .tab').forEach(tab => {
    tab.addEventListener('click', () => {
      modalBody.querySelectorAll('.tabs .tab').forEach(t => t.classList.remove('active'));
      modalBody.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      modalBody.querySelector(`#tab-${tab.dataset.tab}`)?.classList.add('active');
      if (tab.dataset.tab === 'dokumen' && kegiatanId) loadDokumen(kegiatanId);
    });
  });
}

function rupiahSingkat(val) {
  if (!val || isNaN(val)) return '0';
  return parseFloat(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function pctColor(pct) {
  if (pct >= 80) return 'pct-green';
  if (pct >= 50) return 'pct-yellow';
  return 'pct-red';
}

function openModal(title, body, btns = [], cls = '') {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalBody').innerHTML   = body;
  document.getElementById('modal').className       = `modal ${cls}`;
  document.getElementById('modalFooter').innerHTML = btns.map(b =>
    `<button class="btn ${b.cls}" onclick="${b.action}">${b.label}</button>`
  ).join('');
  document.getElementById('modalOverlay').style.display = 'flex';
}

function closeModal() {
  document.getElementById('modalOverlay').style.display = 'none';
}

function showToast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.textContent = msg;
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

function showError(msg) {
  document.getElementById('mainContent').innerHTML = `<div class="empty-state"><p style="color:#dc2626">${msg}</p></div>`;
}

function emptyState(msg) {
  return `<div class="empty-state"><p>${msg}</p></div>`;
}

function rupiah(val) {
  if (val == null || isNaN(val)) return 'Rp0';
  return 'Rp' + parseFloat(val).toLocaleString('id-ID', { minimumFractionDigits: 0 });
}

function fmtDate(d) {
  if (!d) return '—';
  const dt = new Date(d);
  return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function timeAgo(datetime) {
  if (!datetime) return '';
  const diff = Math.floor((Date.now() - new Date(datetime)) / 1000);
  if (diff < 60) return 'Baru saja';
  if (diff < 3600) return `${Math.floor(diff/60)} menit lalu`;
  if (diff < 86400) return `${Math.floor(diff/3600)} jam lalu`;
  return fmtDate(datetime);
}

function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function statusLabel(s) {
  return { draft:'Draft', berjalan:'Berjalan', selesai:'Selesai', terlambat:'Terlambat' }[s] || s;
}

function taskStatusLabel(s) {
  return { belum_dimulai:'Belum Dimulai', sedang_berjalan:'Berjalan', selesai:'Selesai' }[s] || s;
}

function roleLabel(r) {
  return {
    direktur:'Direktur', ketua_tim:'Ketua Tim',
    anggota_tim:'Anggota Tim', perencana_anggaran:'Perencana Anggaran',
    admin:'Admin', tu:'Tata Usaha',
    pic_telaah:'PIC Telaah', klien:'Klien',
  }[r] || r;
}

function roleBadge(r) {
  return {
    direktur:'status-berjalan', ketua_tim:'status-selesai',
    anggota_tim:'status-draft', perencana_anggaran:'status-terlambat',
    admin:'status-berjalan', tu:'status-selesai',
    pic_telaah:'status-berjalan', klien:'status-draft',
  }[r] || '';
}

function progressColor(pct) {
  return pct > 80 ? 'hijau' : pct >= 50 ? 'kuning' : 'merah';
}

// Warna serapan anggaran: hijau jika wajar (≤100%), kuning jika mendekati, merah jika melebihi pagu
function anggaranColor(pct) {
  if (pct > 100) return 'merah';   // Over budget
  if (pct >= 80) return 'hijau';   // Serapan baik
  if (pct >= 40) return 'kuning';  // Serapan sedang
  return 'merah';                  // Serapan rendah
}

function exportTableToCSV(tableId, filename) {
  const table = document.getElementById(tableId);
  if (!table) return;
  let csv = '';
  table.querySelectorAll('tr').forEach(row => {
    csv += Array.from(row.querySelectorAll('th,td')).map(c => `"${c.textContent.trim()}"`).join(',') + '\n';
  });
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
  const link  = document.createElement('a');
  link.href   = URL.createObjectURL(blob);
  link.download = `${filename}_${todayWIB()}.csv`;
  link.click();
}
