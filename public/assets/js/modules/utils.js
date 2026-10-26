/* ============================================================
   utils.js — Helper functions & shared utilities
   ============================================================ */
'use strict';

/**
 * Tanggal hari ini dalam format YYYY-MM-DD sesuai zona waktu lokal browser.
 * Menggunakan toLocaleDateString dengan locale 'en-CA' yang menghasilkan
 * format ISO YYYY-MM-DD agar aman dipakai sebagai value input[type=date].
 */
function todayWIB() {
  return new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' });
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

  const footer = document.getElementById('modalFooter');
  footer.innerHTML = '';
  btns.forEach(b => {
    const btn = document.createElement('button');
    btn.className = `btn ${b.cls}`;
    btn.textContent = b.label;
    // Dukung action berupa Function maupun string onclick
    if (typeof b.action === 'function') {
      btn.addEventListener('click', b.action);
    } else if (typeof b.action === 'string') {
      btn.setAttribute('onclick', b.action);
    }
    footer.appendChild(btn);
  });

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

function showSuccess(msg) { showToast(msg, 'success'); }

function showError(msg) {
  // Jika di dalam modal/panel, tampilkan sebagai toast — jangan overwrite mainContent
  showToast(msg, 'error');
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

// Fallback statis (dipakai sebelum _rolesCache terisi)
const _ROLE_LABEL_FALLBACK = {
  direktur:'Direktur', ketua_tim:'Ketua Tim',
  anggota_tim:'Anggota Tim', perencana_anggaran:'Perencana Anggaran',
  admin:'Administrator', tu:'Tata Usaha',
  pic_telaah:'PIC Telaah', klien:'Klien',
};
function roleLabel(r) {
  // Pakai cache dinamis jika sudah terisi (di-set oleh users.js)
  if (typeof _rolesCache !== 'undefined' && _rolesCache.length) {
    const found = _rolesCache.find(x => x.slug === r);
    if (found) return found.label;
  }
  return _ROLE_LABEL_FALLBACK[r] || r;
}

function roleBadge(r) {
  // Untuk role dinamis, warna badge diambil dari warna role
  // Fungsi ini masih dipakai beberapa tempat — kembalikan class fallback
  const fallback = {
    direktur:'status-berjalan', ketua_tim:'status-selesai',
    anggota_tim:'status-draft', perencana_anggaran:'status-terlambat',
    admin:'status-berjalan', tu:'status-terlambat',
    pic_telaah:'status-berjalan', klien:'status-draft',
  };
  return fallback[r] || 'status-draft';
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

/**
 * initTabs(kegiatanId) — pasang event listener tab switching pada modal body.
 * Dipakai oleh showOffBudgetDetail dan showKegiatanDetail.
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
