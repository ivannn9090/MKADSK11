/* ============================================================
   audit.js — Audit Trail page
   ============================================================ */
'use strict';

let _auditPage   = 1;
let _auditQ      = '';
let _auditTabel  = '';

async function renderAudit() {
  _auditPage  = 1;
  _auditQ     = '';
  _auditTabel = '';
  _renderAuditShell();
  await _loadAuditData();
}

function _renderAuditShell() {
  document.getElementById('mainContent').innerHTML = `
    <div class="section-header" style="margin-bottom:16px">
      <div class="section-title">Audit Trail</div>
    </div>

    <!-- Filter bar -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
      <div style="position:relative;flex:1;min-width:220px">
        <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"
          style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gray-400)">
          <path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16a6.47 6.47 0 0 0 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
        </svg>
        <input type="text" id="auditSearch" class="form-control" placeholder="Cari aktivitas atau pengguna..."
          style="padding-left:32px" oninput="_auditOnSearch(this.value)">
      </div>
      <select id="auditTabelFilter" class="form-control" style="width:180px" onchange="_auditOnTabel(this.value)">
        <option value="">Semua Tabel</option>
        <option value="users">users</option>
        <option value="kegiatan">kegiatan</option>
        <option value="tasks">tasks</option>
        <option value="teams">teams</option>
        <option value="realisasi_anggaran">realisasi_anggaran</option>
        <option value="rincian_anggaran">rincian_anggaran</option>
        <option value="rencana_penarikan_dana">rencana_penarikan_dana</option>
        <option value="app_settings">app_settings</option>
      </select>
      <button class="btn btn-ghost" onclick="_auditReset()" style="font-size:12.5px">
        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M17.65 6.35A7.96 7.96 0 0 0 12 4a8 8 0 1 0 8 8h-2a6 6 0 1 1-1.76-4.24l-2.83 2.83H22V4l-4.35 4.35z"/></svg>
        Reset
      </button>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <table id="auditTable">
        <thead>
          <tr>
            <th style="width:150px">Waktu</th>
            <th>Pengguna</th>
            <th style="width:120px">Role</th>
            <th>Aktivitas</th>
            <th style="width:140px">Tabel</th>
            <th style="width:110px">IP Address</th>
          </tr>
        </thead>
        <tbody id="auditBody">
          <tr><td colspan="6" style="text-align:center;padding:32px">
            <div class="spinner-border" style="width:1.4rem;height:1.4rem"></div>
          </td></tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div id="auditPagination" style="display:flex;justify-content:space-between;align-items:center;
      margin-top:16px;flex-wrap:wrap;gap:8px;font-size:13px;color:var(--gray-500)">
    </div>
  `;
}

async function _loadAuditData() {
  const tbody = document.getElementById('auditBody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:32px">
    <div class="spinner-border" style="width:1.4rem;height:1.4rem"></div></td></tr>`;

  const params = new URLSearchParams({ page: _auditPage });
  if (_auditQ)     params.set('q', _auditQ);
  if (_auditTabel) params.set('tabel', _auditTabel);

  const res = await apiGet(`audit-log?${params}`);
  if (!res || !res.status) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--danger)">
      Gagal memuat data audit trail.</td></tr>`;
    return;
  }

  const { rows, total, page, total_pages, per_page } = res.data;

  if (!rows || rows.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--gray-400)">
      Tidak ada data.</td></tr>`;
    _renderAuditPagination(0, 0, 0, 0);
    return;
  }

  tbody.innerHTML = rows.map(r => `
    <tr>
      <td style="white-space:nowrap;font-size:12px;color:var(--gray-500)">${esc(r.waktu || '—')}</td>
      <td>
        <div style="font-weight:600;font-size:13px">${esc(r.user_nama || '—')}</div>
      </td>
      <td><span class="status-badge ${roleBadge(r.user_role)}" style="font-size:11px">${roleLabel(r.user_role || '—')}</span></td>
      <td style="font-size:13px">${esc(r.aktivitas || '—')}</td>
      <td>
        ${r.tabel_terkait
          ? `<span style="font-size:11px;background:var(--gray-100);color:var(--gray-700);
              padding:2px 8px;border-radius:4px;font-family:monospace">${esc(r.tabel_terkait)}</span>`
          : '<span style="color:var(--gray-300)">—</span>'}
      </td>
      <td style="font-size:12px;color:var(--gray-500)">${esc(r.ip_address || '—')}</td>
    </tr>
  `).join('');

  _renderAuditPagination(total, page, total_pages, per_page);
}

function _renderAuditPagination(total, page, totalPages, perPage) {
  const el = document.getElementById('auditPagination');
  if (!el) return;

  const start = total === 0 ? 0 : (page - 1) * perPage + 1;
  const end   = Math.min(page * perPage, total);

  // Build page buttons — show max 7 pages around current
  let pageButtons = '';
  if (totalPages > 1) {
    const range   = 2;
    const pages   = [];
    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= page - range && i <= page + range)) {
        pages.push(i);
      }
    }
    // Insert ellipsis
    const withGaps = [];
    pages.forEach((p, idx) => {
      if (idx > 0 && p - pages[idx - 1] > 1) withGaps.push('…');
      withGaps.push(p);
    });

    pageButtons = `
      <div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
        <button class="btn btn-sm ${page === 1 ? 'btn-ghost' : 'btn-outline'}"
          ${page === 1 ? 'disabled' : ''} onclick="_auditGoPage(${page - 1})"
          style="padding:4px 10px">‹</button>
        ${withGaps.map(p => typeof p === 'number'
          ? `<button class="btn btn-sm ${p === page ? 'btn-primary' : 'btn-outline'}"
              onclick="_auditGoPage(${p})" style="padding:4px 10px;min-width:32px">${p}</button>`
          : `<span style="padding:0 4px;color:var(--gray-400)">…</span>`
        ).join('')}
        <button class="btn btn-sm ${page === totalPages ? 'btn-ghost' : 'btn-outline'}"
          ${page === totalPages ? 'disabled' : ''} onclick="_auditGoPage(${page + 1})"
          style="padding:4px 10px">›</button>
      </div>`;
  }

  el.innerHTML = `
    <div style="color:var(--gray-500);font-size:12.5px">
      ${total > 0 ? `Menampilkan <strong>${start}–${end}</strong> dari <strong>${total}</strong> entri` : 'Tidak ada data'}
    </div>
    ${pageButtons}
  `;
}

// ---- Event handlers ----
let _auditSearchTimer = null;
function _auditOnSearch(val) {
  clearTimeout(_auditSearchTimer);
  _auditSearchTimer = setTimeout(() => {
    _auditQ    = val.trim();
    _auditPage = 1;
    _loadAuditData();
  }, 350);
}

function _auditOnTabel(val) {
  _auditTabel = val;
  _auditPage  = 1;
  _loadAuditData();
}

function _auditGoPage(p) {
  _auditPage = p;
  _loadAuditData();
  document.getElementById('mainContent').scrollTo({ top: 0, behavior: 'smooth' });
}

function _auditReset() {
  _auditQ     = '';
  _auditPage  = 1;
  _auditTabel = '';
  const s = document.getElementById('auditSearch');
  const t = document.getElementById('auditTabelFilter');
  if (s) s.value = '';
  if (t) t.value = '';
  _loadAuditData();
}
