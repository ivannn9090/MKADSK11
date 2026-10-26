/* ============================================================
   serasi.js v2 — SERASI: Sistem Terintegrasi Rekomendasi dan Informasi
   SharePoint REST API Integration
   Portal Temu Balik Arsip Direktorat Sistem Kearsipan ANRI
   ============================================================ */
'use strict';

// ---------------------------------------------------------------
// State
// ---------------------------------------------------------------
let _serasiDocs        = [];
let _serasiTagList     = [];
let _serasiFolderTree  = [];
let _serasiSearchQuery = '';
let _serasiFilters     = { tahun:'', jenis:'', program:'', sub_kegiatan:'', tim:'', tag:'', folder_id:'' };
let _serasiEditId      = null;

const _JENIS_OPTS = [
  'Policy Brief','Policy Paper','Policy Memo','Telaah','Kajian Analisis',
  'Draf Kajian','Rancangan Akhir Kajian','Notula','Daftar Hadir',
  'POK','KAK','Laporan','SK Tim','Lainnya',
];

// ---------------------------------------------------------------
// Entry point — load data then render full module
// ---------------------------------------------------------------
function renderSerasi() {
  const mc = document.getElementById('mainContent');
  mc.innerHTML = `
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;
                min-height:60vh;text-align:center;padding:40px 20px">
      <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#1e3a5f,#2563eb);
                  display:flex;align-items:center;justify-content:center;margin-bottom:24px;box-shadow:0 4px 20px rgba(37,99,235,.25)">
        <svg viewBox="0 0 24 24" fill="white" width="36" height="36">
          <path d="M20 6h-2.18c.07-.44.18-.86.18-1.3C18 2.12 15.88 0 13.3 0c-1.3 0-2.49.56-3.3 1.44C9.19.56 8 0 6.7 0 4.12 0 2 2.12 2 4.7c0 .44.11.86.18 1.3H0v14c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-8.5 12H4V8h7.5v10zm8.5 0h-7V8h7v10z"/>
        </svg>
      </div>
      <div style="font-size:11px;font-weight:700;letter-spacing:3px;color:#2563eb;text-transform:uppercase;margin-bottom:8px">
        Segera Hadir
      </div>
      <h1 style="font-size:32px;font-weight:800;color:#1e3a5f;margin:0 0 8px">SERASI</h1>
      <p style="font-size:14px;color:#64748b;margin:0 0 4px;font-weight:500">
        Sistem Terintegrasi Rekomendasi dan Informasi
      </p>
      <p style="font-size:13px;color:#94a3b8;margin:0 0 32px">
        Portal Temu Balik Arsip Direktorat Sistem Kearsipan
      </p>
      <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;max-width:480px">
        ${['Temu Balik Arsip','Pencarian Dokumen','Metadata & Tagging','Integrasi OneDrive','Knowledge Management','Struktur Folder'].map(f =>
          `<span style="background:#f0f4ff;color:#3b5bdb;border:1px solid #c7d7ff;border-radius:20px;
                         padding:5px 14px;font-size:12px;font-weight:500">${f}</span>`
        ).join('')}
      </div>
      <p style="margin-top:36px;font-size:12px;color:#94a3b8">
        Modul ini sedang dalam pengembangan. Akan segera tersedia.
      </p>
    </div>
  `;
}

// ---------------------------------------------------------------
// Shell HTML
// ---------------------------------------------------------------
function _serasiShell(stats) {
  const canEdit = hasPermission('serasi.view');
  const isAdmin = hasPermission('serasi.manage');

  return `
  <div class="serasi-wrap">

    <!-- HERO -->
    <div class="serasi-hero">
      <div class="serasi-hero-icon">
        <svg viewBox="0 0 24 24" fill="white" width="36" height="36">
          <path d="M20 6h-2.18c.07-.44.18-.86.18-1.3C18 2.12 15.88 0 13.3 0c-1.3 0-2.49.56-3.3 1.44C9.19.56 8 0 6.7 0 4.12 0 2 2.12 2 4.7c0 .44.11.86.18 1.3H0v14c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-8.5 12H4V8h7.5v10zm8.5 0h-7V8h7v10z"/>
        </svg>
      </div>
      <div style="flex:1">
        <div class="serasi-hero-title">SERASI</div>
        <div class="serasi-hero-sub">Sistem Terintegrasi Rekomendasi dan Informasi</div>
        <div class="serasi-hero-desc">Portal Temu Balik Arsip Direktorat Sistem Kearsipan</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;position:relative;z-index:1">
        ${canEdit ? `<button class="serasi-hero-btn" onclick="serasiOpenForm(null)">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
          Tambah Dokumen
        </button>` : ''}
        ${isAdmin ? `<button class="serasi-hero-btn serasi-hero-btn-outline" onclick="serasiSwitchTabByName('sharepoint')">
            <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
            Integrasi OneDrive
          </button>` : ''}
      </div>
    </div>

    <!-- SEARCH -->
    <div class="serasi-search-wrap">
      <svg class="serasi-search-icon" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
        <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
      </svg>
      <input type="text" id="serasiSearchInput" class="serasi-search-input"
             placeholder="Cari dokumen, kata kunci, tag, kegiatan, tahun, tim penyusun atau folder..."
             oninput="serasiOnSearch(this.value)">
      <button class="serasi-search-btn" onclick="serasiDoSearch()">Cari</button>
    </div>

    <!-- STATS -->
    <div class="serasi-stats-grid" id="serasiStatsGrid">
      ${_serasiStatsHTML(stats)}
    </div>

    <!-- TABS -->
    <div class="serasi-tabs">
      <button class="serasi-tab active" data-tab="arsip"      onclick="serasiSwitchTab('arsip',this)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z"/></svg>
        Temu Balik Arsip
      </button>
      <button class="serasi-tab" data-tab="folder" onclick="serasiSwitchTab('folder',this)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
        Struktur Folder
      </button>
      <button class="serasi-tab" data-tab="knowledge" onclick="serasiSwitchTab('knowledge',this)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
        Knowledge Map
      </button>
      <button class="serasi-tab" data-tab="statistik" onclick="serasiSwitchTab('statistik',this)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
        Statistik
      </button>
      ${canEdit ? `<button class="serasi-tab" data-tab="kelola-tag" onclick="serasiSwitchTab('kelola-tag',this)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/></svg>
        Kelola Tag
      </button>` : ''}
      ${isAdmin ? `<button class="serasi-tab" data-tab="sharepoint" onclick="serasiSwitchTab('sharepoint',this)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-1 7H6V8h12v2zm0 4H6v-2h12v2zm-6 4H6v-2h6v2z"/></svg>
        Integrasi OneDrive
      </button>` : ''}
    </div>

    <!-- PANEL: Temu Balik Arsip -->
    <div class="serasi-panel" id="serasi-panel-arsip">
      <div class="serasi-filter-row">
        <select class="serasi-filter-select" id="filterTahun"       onchange="serasiApplyFilter()"><option value="">Semua Tahun</option></select>
        <select class="serasi-filter-select" id="filterJenis"       onchange="serasiApplyFilter()">
          <option value="">Semua Jenis</option>
          ${_JENIS_OPTS.map(j=>`<option>${j}</option>`).join('')}
        </select>
        <select class="serasi-filter-select" id="filterProgram"     onchange="serasiApplyFilter()"><option value="">Semua Program</option></select>
        <select class="serasi-filter-select" id="filterSubKegiatan" onchange="serasiApplyFilter()"><option value="">Semua Sub Kegiatan</option></select>
        <select class="serasi-filter-select" id="filterTim"         onchange="serasiApplyFilter()"><option value="">Semua Tim</option></select>
        <select class="serasi-filter-select" id="filterTag"         onchange="serasiApplyFilter()"><option value="">Semua Tag</option></select>
        <button class="btn btn-ghost btn-sm" onclick="serasiResetFilter()">Reset</button>
        <div class="serasi-result-count" id="serasiResultCount"></div>
        ${canEdit ? `<button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="serasiOpenForm(null)">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
          Tambah
        </button>` : ''}
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th><th>Nama Dokumen</th><th>Jenis</th><th>Tag</th>
              <th>Tahun</th><th>Tim Penyusun</th><th>Lokasi Folder</th>
              <th>Tgl Dibuat</th><th>Tgl Diubah</th><th>Aksi</th>
            </tr>
          </thead>
          <tbody id="serasiTableBody"></tbody>
        </table>
      </div>
    </div>

    <!-- PANEL: Struktur Folder -->
    <div class="serasi-panel" id="serasi-panel-folder" style="display:none">
      <div class="serasi-folder-layout">
        <div class="serasi-folder-tree-col">
          <div class="serasi-section-label" style="margin-bottom:10px">Pohon Folder OneDrive</div>
          <div id="serasiFolderTree" class="serasi-tree"></div>
        </div>
        <div class="serasi-folder-detail-col" id="serasiFolderDetail">
          <div class="serasi-folder-empty">
            <svg viewBox="0 0 24 24" fill="currentColor" width="48" height="48" style="color:var(--gray-300)"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z"/></svg>
            <div>Pilih folder untuk melihat detail</div>
          </div>
        </div>
      </div>
    </div>

    <!-- PANEL: Knowledge Map -->
    <div class="serasi-panel" id="serasi-panel-knowledge" style="display:none">
      <div class="serasi-knowledge-wrap" id="serasiKnowledgeMap"></div>
    </div>

    <!-- PANEL: Statistik -->
    <div class="serasi-panel" id="serasi-panel-statistik" style="display:none">
      <div id="serasiStatistikContent"></div>
    </div>

    <!-- PANEL: Kelola Tag -->
    <div class="serasi-panel" id="serasi-panel-kelola-tag" style="display:none">
      <div id="serasiTagPanel"></div>
    </div>

    <!-- PANEL: Integrasi OneDrive -->
    <div class="serasi-panel" id="serasi-panel-sharepoint" style="display:none">
      <div id="serasiSharePointPanel"></div>
    </div>

  </div>

  <!-- Detail slide-over -->
  <div class="serasi-detail-overlay" id="serasiDetailOverlay" style="display:none" onclick="serasiCloseDetail(event)">
    <div class="serasi-detail-panel" id="serasiDetailPanel"></div>
  </div>

  <!-- Form modal -->
  <div class="serasi-modal-overlay" id="serasiFormOverlay" style="display:none" onclick="serasiCloseForm(event)">
    <div class="serasi-modal">
      <div class="serasi-modal-header">
        <div class="serasi-modal-title" id="serasiFormTitle">Tambah Dokumen</div>
        <button class="serasi-detail-close" onclick="serasiCloseForm()">
          <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </button>
      </div>
      <div class="serasi-modal-body" id="serasiFormBody"></div>
    </div>
  </div>
  `;
}

// ---------------------------------------------------------------
// Stats cards
// ---------------------------------------------------------------
function _serasiStatsHTML(s) {
  const cards = [
    { label:'Total Arsip',     value:s.total        ||0, color:'blue',   icon:'M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z' },
    { label:'Policy Brief',    value:s.policy_brief ||0, color:'blue',   icon:'M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z' },
    { label:'Policy Paper',    value:s.policy_paper ||0, color:'blue',   icon:'M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z' },
    { label:'Telaah',          value:s.telaah        ||0, color:'green',  icon:'M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z' },
    { label:'Kajian Analisis', value:s.kajian        ||0, color:'yellow', icon:'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z' },
    { label:'POK dan KAK',     value:s.pok_kak       ||0, color:'yellow', icon:'M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z' },
    { label:'Notula',          value:s.notula        ||0, color:'green',  icon:'M21 6.5l-4-4-8.5 8.5-1 4.5 4.5-1L21 6.5z' },
    { label:'Daftar Hadir',    value:s.daftar_hadir  ||0, color:'green',  icon:'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z' },
    { label:'Laporan',         value:s.laporan       ||0, color:'blue',   icon:'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z' },
  ];
  return cards.map(c=>`
    <div class="stat-card ${c.color} serasi-stat-card">
      <div class="serasi-stat-icon"><svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="${c.icon}"/></svg></div>
      <div><div class="stat-label">${c.label}</div><div class="stat-value">${c.value}</div></div>
    </div>`).join('');
}

// ---------------------------------------------------------------
// Event binding
// ---------------------------------------------------------------
function _serasiBindEvents() {
  document.getElementById('serasiSearchInput')
    .addEventListener('keydown', e => { if (e.key==='Enter') serasiDoSearch(); });
}

// ---------------------------------------------------------------
// Search & Filter
// ---------------------------------------------------------------
function serasiOnSearch(v) { _serasiSearchQuery = v.trim(); }
function serasiDoSearch() {
  _serasiSearchQuery = (document.getElementById('serasiSearchInput').value||'').trim();
  _serasiApplyFiltersAndSearch();
}

function _serasiPopulateFilters() {
  const years  = [...new Set(_serasiDocs.map(d=>d.tahun).filter(Boolean))].sort().reverse();
  const progs  = [...new Set(_serasiDocs.map(d=>d.program).filter(Boolean))].sort();
  const subs   = [...new Set(_serasiDocs.map(d=>d.sub_kegiatan).filter(Boolean))].sort();
  const tims   = [...new Set(_serasiDocs.map(d=>d.tim_penyusun).filter(Boolean))].sort();
  const tags   = [...new Set(_serasiDocs.flatMap(d=>d.tags||[]))].sort();

  const add = (id, items) => {
    const sel = document.getElementById(id); if(!sel) return;
    items.forEach(v => { const o=document.createElement('option'); o.value=v; o.textContent=v; sel.appendChild(o); });
  };
  add('filterTahun', years); add('filterProgram', progs);
  add('filterSubKegiatan', subs); add('filterTim', tims); add('filterTag', tags);
}

function serasiApplyFilter() {
  _serasiFilters.tahun        = document.getElementById('filterTahun').value;
  _serasiFilters.jenis        = document.getElementById('filterJenis').value;
  _serasiFilters.program      = document.getElementById('filterProgram').value;
  _serasiFilters.sub_kegiatan = document.getElementById('filterSubKegiatan').value;
  _serasiFilters.tim          = document.getElementById('filterTim').value;
  _serasiFilters.tag          = document.getElementById('filterTag').value;
  _serasiApplyFiltersAndSearch();
}

function serasiResetFilter() {
  _serasiFilters = { tahun:'',jenis:'',program:'',sub_kegiatan:'',tim:'',tag:'',folder_id:'' };
  _serasiSearchQuery = '';
  document.getElementById('serasiSearchInput').value = '';
  ['filterTahun','filterJenis','filterProgram','filterSubKegiatan','filterTim','filterTag']
    .forEach(id => { const el=document.getElementById(id); if(el) el.value=''; });
  _serasiRenderTable(_serasiDocs);
}

function _serasiApplyFiltersAndSearch() {
  let list = _serasiDocs;
  const q = _serasiSearchQuery.toLowerCase();
  if (q) list = list.filter(d =>
    (d.file_name    ||'').toLowerCase().includes(q) ||
    (d.kata_kunci   ||'').toLowerCase().includes(q) ||
    (d.jenis_dokumen||'').toLowerCase().includes(q) ||
    (d.tim_penyusun ||'').toLowerCase().includes(q) ||
    (d.program      ||'').toLowerCase().includes(q) ||
    (d.sub_kegiatan ||'').toLowerCase().includes(q) ||
    (d.full_path    ||'').toLowerCase().includes(q) ||
    String(d.tahun||'').includes(q) ||
    (d.tags||[]).some(t=>t.toLowerCase().includes(q))
  );
  if (_serasiFilters.tahun)        list = list.filter(d=>String(d.tahun)===_serasiFilters.tahun);
  if (_serasiFilters.jenis)        list = list.filter(d=>d.jenis_dokumen===_serasiFilters.jenis);
  if (_serasiFilters.program)      list = list.filter(d=>(d.program||'')===_serasiFilters.program);
  if (_serasiFilters.sub_kegiatan) list = list.filter(d=>(d.sub_kegiatan||'')===_serasiFilters.sub_kegiatan);
  if (_serasiFilters.tim)          list = list.filter(d=>(d.tim_penyusun||'')===_serasiFilters.tim);
  if (_serasiFilters.tag)          list = list.filter(d=>(d.tags||[]).includes(_serasiFilters.tag));
  _serasiRenderTable(list);
}

// ---------------------------------------------------------------
// Table
// ---------------------------------------------------------------
function _serasiRenderTable(list) {
  const tbody = document.getElementById('serasiTableBody');
  const count = document.getElementById('serasiResultCount');
  if (!tbody) return;
  count.textContent = `${list.length} dokumen ditemukan`;
  const canEdit = hasPermission('serasi.view');
  const isAdmin = hasPermission('serasi.manage');

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;padding:32px;color:var(--gray-400)">Tidak ada dokumen yang cocok.</td></tr>`;
    return;
  }
  tbody.innerHTML = list.map((d,i) => `
    <tr>
      <td style="color:var(--gray-400);font-size:12px">${i+1}</td>
      <td>
        <div style="font-weight:600;font-size:13px">${esc(d.file_name)}</div>
        ${d.kata_kunci?`<div style="font-size:11px;color:var(--gray-400);margin-top:2px">${esc(d.kata_kunci)}</div>`:''}
      </td>
      <td><span class="serasi-jenis-badge">${esc(d.jenis_dokumen||'-')}</span></td>
      <td>${_serasiTagBadges(d.tags||[])}</td>
      <td style="font-weight:600">${d.tahun||'-'}</td>
      <td style="font-size:12px">${esc(d.tim_penyusun||'-')}</td>
      <td style="font-size:11px;color:var(--gray-500);max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
          title="${esc(d.full_path||'')}">${esc(_serasiFolderShort(d.full_path||d.folder_name))}</td>
      <td style="font-size:12px;color:var(--gray-400)">${d.created_date?d.created_date.substring(0,10):'-'}</td>
      <td style="font-size:12px;color:var(--gray-400)">${d.modified_date?d.modified_date.substring(0,10):'-'}</td>
      <td>
        <div style="display:flex;gap:4px;flex-wrap:wrap">
          <button class="btn btn-ghost btn-sm serasi-action-btn" onclick="serasiShowDetail(${d.document_id})"
              title="Lihat detail dokumen">
            <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/></svg>
            Detail
          </button>
          ${d.sharepoint_web_url||d.file_url ? `<a href="${d.sharepoint_web_url||d.file_url}" target="_blank"
              class="btn btn-ghost btn-sm serasi-action-btn" style="color:#0078d4" title="Buka di OneDrive">
            <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13"><path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
            OneDrive
          </a>` : ''}
          ${canEdit?`<button class="btn btn-ghost btn-sm serasi-action-btn" style="color:var(--warning)" onclick="serasiOpenForm(${d.document_id})">
            <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
            Edit
          </button>`:''}
          ${isAdmin?`<button class="btn btn-ghost btn-sm serasi-action-btn" style="color:var(--danger)"
              onclick="serasiDeleteDoc(${d.document_id},'${esc(d.file_name).replace(/'/g,"\\'")}')">
            <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
            Hapus
          </button>`:''}
        </div>
      </td>
    </tr>`).join('');
}

function _serasiTagBadges(tags) {
  if (!tags||!tags.length) return '<span style="color:var(--gray-300);font-size:11px">—</span>';
  return tags.slice(0,3).map(t=>`<span class="serasi-tag-chip">${esc(t)}</span>`).join('') +
    (tags.length>3?`<span class="serasi-tag-chip serasi-tag-more">+${tags.length-3}</span>`:'');
}

function _serasiFolderShort(path) {
  if (!path) return '-';
  const p = path.replace(/\\/g,'/').split('/');
  return p.length<=2 ? path : '…/'+p.slice(-2).join('/');
}

// ---------------------------------------------------------------
// Tab switching
// ---------------------------------------------------------------
function serasiSwitchTab(tab, btn) {
  document.querySelectorAll('.serasi-tab').forEach(t=>t.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.querySelectorAll('.serasi-panel').forEach(p=>p.style.display='none');
  const panel = document.getElementById(`serasi-panel-${tab}`);
  if (panel) panel.style.display='block';
  if (tab==='statistik')  _serasiRenderStatistik();
  if (tab==='knowledge')  _serasiRenderKnowledgeMap();
  if (tab==='kelola-tag') _serasiRenderTagPanel();
  if (tab==='sharepoint') _serasiRenderSharePointPanel();
}

function serasiSwitchTabByName(name) {
  const btn = document.querySelector(`.serasi-tab[data-tab="${name}"]`);
  serasiSwitchTab(name, btn);
  if (btn) btn.scrollIntoView({behavior:'smooth',block:'nearest'});
}

// ---------------------------------------------------------------
// Folder Tree
// ---------------------------------------------------------------
function _serasiRenderFolderTree(tree) {
  const c = document.getElementById('serasiFolderTree');
  if (c) c.innerHTML = tree.length ? _serasiTreeHTML(tree,0) :
    '<div style="color:var(--gray-400);font-size:13px;padding:12px">Belum ada folder. Sinkronisasi dari SharePoint terlebih dahulu.</div>';
}

function _serasiTreeHTML(nodes, depth) {
  return nodes.map(n=>`
    <div class="serasi-tree-node" style="padding-left:${depth*16}px">
      <div class="serasi-tree-item" onclick="serasiSelectFolder(this,${JSON.stringify(n).replace(/"/g,'&quot;')})">
        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" class="serasi-folder-icon"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z"/></svg>
        <span>${esc(n.folder_name||n.nama||'')}</span>
        ${n.item_count!=null?`<span class="serasi-folder-count">${n.item_count}</span>`:''}
      </div>
      ${n.children&&n.children.length?`<div class="serasi-tree-children">${_serasiTreeHTML(n.children,depth+1)}</div>`:''}`
  ).join('</div>');
}

function serasiSelectFolder(el, node) {
  document.querySelectorAll('.serasi-tree-item').forEach(i=>i.classList.remove('selected'));
  el.classList.add('selected');
  const ch = el.closest('.serasi-tree-node').querySelectorAll(':scope > .serasi-tree-children');
  ch.forEach(c=>{c.style.display=c.style.display==='none'?'block':'none';});

  const docs = _serasiDocs.filter(d=>(d.full_path||'').startsWith(node.full_path||node.folder_name||''));
  const tags = [...new Set(docs.flatMap(d=>d.tags||[]))].slice(0,8);
  document.getElementById('serasiFolderDetail').innerHTML = `
    <div class="serasi-folder-detail-inner">
      <div class="serasi-folder-detail-title">
        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z"/></svg>
        ${esc(node.folder_name||node.nama||'')}
      </div>
      <div style="font-size:11.5px;color:var(--gray-400);margin:6px 0 12px;font-family:monospace">${esc(node.full_path||'')}</div>
      <div class="serasi-stats-grid" style="grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:16px">
        <div class="stat-card blue serasi-stat-card"><div><div class="stat-label">Dokumen Terindeks</div><div class="stat-value">${docs.length}</div></div></div>
        <div class="stat-card green serasi-stat-card"><div><div class="stat-label">Sub Folder</div><div class="stat-value">${(node.children||[]).length}</div></div></div>
      </div>
      ${tags.length?`<div class="serasi-section-label" style="margin-bottom:8px">Tag Populer</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px">${tags.map(t=>`<span class="serasi-tag-chip">${esc(t)}</span>`).join('')}</div>`:''}
      ${docs.length?`<div class="serasi-section-label" style="margin-bottom:8px">Dokumen Terbaru</div>
        ${docs.slice(0,5).map(d=>`<div class="serasi-mini-doc" onclick="serasiShowDetail(${d.document_id})">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-3 14H8v-2h3v2zm5-4H8v-2h8v2zm0-4H8V7h8v2z"/></svg>
          <span>${esc(d.file_name)}</span></div>`).join('')}`
      :'<div style="color:var(--gray-400);font-size:13px">Tidak ada dokumen terindeks di folder ini.</div>'}
    </div>`;
}

// ---------------------------------------------------------------
// Detail slide-over
// ---------------------------------------------------------------
function serasiShowDetail(id) {
  const d = _serasiDocs.find(x=>x.document_id===id);
  if (!d) return;

  // Breadcrumb dari full_path
  const pathStr = d.full_path || d.folder_name || '';
  const parts   = pathStr.replace(/\\/g,'/').split('/').filter(Boolean);
  const bc = parts.map((p,i,arr)=>
    i<arr.length-1
      ? `<span class="serasi-bc-part">${esc(p)}</span><span class="serasi-bc-sep">›</span>`
      : `<span class="serasi-bc-current">${esc(p)}</span>`).join('');
  const canEdit = hasPermission('serasi.view');

  document.getElementById('serasiDetailPanel').innerHTML = `
    <div class="serasi-detail-header">
      <button class="serasi-detail-close" onclick="serasiCloseDetail()">
        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
      </button>
      <div class="serasi-detail-title">${esc(d.file_name)}</div>
    </div>
    <div class="serasi-detail-body">
      <div class="serasi-detail-breadcrumb">${bc||esc(d.file_name)}</div>
      <div class="serasi-detail-grid">
        ${_dRow('Jenis Dokumen',`<span class="serasi-jenis-badge">${esc(d.jenis_dokumen||'-')}</span>`)}
        ${_dRow('Tahun',`<strong>${d.tahun||'-'}</strong>`)}
        ${_dRow('Program',esc(d.program||'-'))}
        ${_dRow('Sub Kegiatan',esc(d.sub_kegiatan||'-'))}
        ${_dRow('Tim Penyusun',esc(d.tim_penyusun||'-'))}
        ${d.kata_kunci?_dRow('Kata Kunci',esc(d.kata_kunci)):''}
        ${_dRow('Tag',_serasiTagBadges(d.tags||[]))}
        ${_dRow('Tgl Dibuat', d.created_date?d.created_date.substring(0,10):'-')}
        ${_dRow('Tgl Diubah', d.modified_date?d.modified_date.substring(0,10):'-')}
        ${_dRow('Ukuran', d.file_size?_serasiFileSize(d.file_size):'-')}
        ${_dRow('Lokasi Folder',`<span style="font-size:11.5px;color:var(--gray-500);word-break:break-all">${esc(d.full_path||'-')}</span>`)}
      </div>
      <div style="display:flex;gap:8px;margin-top:20px;flex-wrap:wrap">
        ${(d.sharepoint_web_url||d.file_url)?`<a href="${d.sharepoint_web_url||d.file_url}" target="_blank"
            class="btn btn-primary" style="flex:1;justify-content:center">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
          Buka di OneDrive
        </a>`:''}
        ${canEdit?`<button class="btn btn-secondary" style="flex:1;justify-content:center"
            onclick="serasiCloseDetail();serasiOpenForm(${d.document_id})">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z"/></svg>
          Edit Metadata
        </button>`:''}
      </div>
    </div>`;
  document.getElementById('serasiDetailOverlay').style.display='flex';
}

function _dRow(label, val) {
  return `<div class="serasi-detail-row"><div class="serasi-detail-label">${label}</div><div class="serasi-detail-value">${val}</div></div>`;
}
function _serasiFileSize(bytes) {
  if (bytes < 1024) return bytes+' B';
  if (bytes < 1048576) return (bytes/1024).toFixed(1)+' KB';
  return (bytes/1048576).toFixed(1)+' MB';
}
function serasiCloseDetail(e) {
  if (e && e.target!==document.getElementById('serasiDetailOverlay')) return;
  document.getElementById('serasiDetailOverlay').style.display='none';
}

// ---------------------------------------------------------------
// Form modal — Tambah / Edit Dokumen + Metadata
// ---------------------------------------------------------------
function serasiOpenForm(id) {
  _serasiEditId = id;
  const d = id ? _serasiDocs.find(x=>x.document_id===id) : null;
  document.getElementById('serasiFormTitle').textContent = id ? 'Edit Metadata Dokumen' : 'Tambah Dokumen Baru';

  const tagVal = (d&&d.tags) ? d.tags.join(', ') : '';
  const jenisSelected = v => d&&d.jenis_dokumen===v?'selected':'';

  document.getElementById('serasiFormBody').innerHTML = `
    <form id="serasiDocForm" onsubmit="serasiSubmitForm(event)">
      <div class="serasi-form-section-label">Informasi Dokumen</div>
      <div class="serasi-form-grid">
        <div class="serasi-form-group serasi-form-full">
          <label class="serasi-form-label">Nama Dokumen / File <span style="color:var(--danger)">*</span></label>
          <input type="text" class="serasi-form-input" name="file_name" required
                 value="${d?esc(d.file_name):''}" placeholder="Contoh: Policy Brief SIKN 2026.docx">
        </div>
        <div class="serasi-form-group serasi-form-full">
          <label class="serasi-form-label">Path Folder OneDrive (ServerRelativeUrl)</label>
          <input type="text" class="serasi-form-input" name="file_url"
                 value="${d?esc(d.file_url||''):''}"
                 placeholder="/sites/DSK/Shared Documents/Direktorat Sistem Kearsipan/...">
        </div>
        <div class="serasi-form-group serasi-form-full">
          <label class="serasi-form-label">URL Buka di OneDrive</label>
          <input type="url" class="serasi-form-input" name="sharepoint_web_url"
                 value="${d?esc(d.sharepoint_web_url||''):''}"
                 placeholder="https://contoso.sharepoint.com/...">
        </div>
        <div class="serasi-form-group">
          <label class="serasi-form-label">Tanggal Dibuat</label>
          <input type="date" class="serasi-form-input" name="created_date"
                 value="${d&&d.created_date?d.created_date.substring(0,10):''}">
        </div>
        <div class="serasi-form-group">
          <label class="serasi-form-label">Tanggal Diubah</label>
          <input type="date" class="serasi-form-input" name="modified_date"
                 value="${d&&d.modified_date?d.modified_date.substring(0,10):''}">
        </div>
      </div>

      <div class="serasi-form-section-label" style="margin-top:8px">Metadata Kurator</div>
      <div class="serasi-form-grid">
        <div class="serasi-form-group">
          <label class="serasi-form-label">Jenis Dokumen <span style="color:var(--danger)">*</span></label>
          <select class="serasi-form-input" name="meta_jenis_dokumen" required>
            ${_JENIS_OPTS.map(j=>`<option value="${j}" ${jenisSelected(j)}>${j}</option>`).join('')}
          </select>
        </div>
        <div class="serasi-form-group">
          <label class="serasi-form-label">Tahun</label>
          <input type="number" class="serasi-form-input" name="meta_tahun" min="2000" max="2099"
                 value="${d?d.tahun||'':new Date().getFullYear()}">
        </div>
        <div class="serasi-form-group">
          <label class="serasi-form-label">Program / Kegiatan</label>
          <input type="text" class="serasi-form-input" name="meta_program"
                 value="${d?esc(d.program||''):''}" placeholder="Contoh: Rekomendasi Kebijakan 2026">
        </div>
        <div class="serasi-form-group">
          <label class="serasi-form-label">Sub Kegiatan</label>
          <input type="text" class="serasi-form-input" name="meta_sub_kegiatan"
                 value="${d?esc(d.sub_kegiatan||''):''}" placeholder="Contoh: GD SIKN">
        </div>
        <div class="serasi-form-group">
          <label class="serasi-form-label">Tim Penyusun</label>
          <input type="text" class="serasi-form-input" name="meta_tim_penyusun"
                 value="${d?esc(d.tim_penyusun||''):''}" placeholder="Contoh: Tim SIKN">
        </div>
        <div class="serasi-form-group serasi-form-full">
          <label class="serasi-form-label">Kata Kunci</label>
          <input type="text" class="serasi-form-input" name="meta_kata_kunci"
                 value="${d?esc(d.kata_kunci||''):''}"
                 placeholder="Pisahkan koma — SIKN, kebijakan, grand desain">
        </div>
        <div class="serasi-form-group serasi-form-full">
          <label class="serasi-form-label">Tag</label>
          <input type="text" class="serasi-form-input" id="serasiTagInput" name="tag_names_raw"
                 value="${tagVal}" placeholder="Pisahkan koma — Telaah, SIKN, Knowledge Management">
          <div style="font-size:11.5px;color:var(--gray-400);margin-top:4px">
            Tag tersedia: ${_serasiTagList.slice(0,12).map(t=>`<span class="serasi-tag-chip" style="cursor:pointer"
              onclick="serasiAddTagInput('${esc(t.tag_name||t)}')">${esc(t.tag_name||t)}</span>`).join('')}
          </div>
        </div>
      </div>
      <div class="serasi-form-actions">
        <button type="button" class="btn btn-ghost" onclick="serasiCloseForm()">Batal</button>
        <button type="submit" class="btn btn-primary" id="serasiFormBtn">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
          ${id?'Simpan Perubahan':'Tambah Dokumen'}
        </button>
      </div>
    </form>`;
  document.getElementById('serasiFormOverlay').style.display='flex';
}

function serasiAddTagInput(tag) {
  const inp = document.getElementById('serasiTagInput'); if (!inp) return;
  const existing = inp.value.split(',').map(t=>t.trim()).filter(Boolean);
  if (!existing.includes(tag)) inp.value = [...existing, tag].join(', ');
}

function serasiCloseForm(e) {
  if (e && e.target!==document.getElementById('serasiFormOverlay')) return;
  document.getElementById('serasiFormOverlay').style.display='none';
}

async function serasiSubmitForm(e) {
  e.preventDefault();
  const btn = document.getElementById('serasiFormBtn');
  btn.disabled=true; btn.textContent='Menyimpan...';

  const fd   = new FormData(document.getElementById('serasiDocForm'));
  const raw  = {}; fd.forEach((v,k)=>{raw[k]=v;});

  const docData = {
    file_name:          raw.file_name,
    file_url:           raw.file_url       || null,
    sharepoint_web_url: raw.sharepoint_web_url || null,
    created_date:       raw.created_date   || null,
    modified_date:      raw.modified_date  || null,
  };
  const metaData = {
    jenis_dokumen: raw.meta_jenis_dokumen,
    tahun:         raw.meta_tahun ? parseInt(raw.meta_tahun) : null,
    program:       raw.meta_program       || null,
    sub_kegiatan:  raw.meta_sub_kegiatan  || null,
    tim_penyusun:  raw.meta_tim_penyusun  || null,
    kata_kunci:    raw.meta_kata_kunci    || null,
  };
  const tagNames = (raw.tag_names_raw||'').split(',').map(t=>t.trim()).filter(Boolean);

  const payload = { ...docData, metadata: metaData, tag_names: tagNames };

  const res = _serasiEditId
    ? await apiPut(`serasi/documents/${_serasiEditId}`, payload)
    : await apiPost('serasi/documents', payload);

  btn.disabled=false;
  if (!res||!res.status) {
    showError(res?res.message:'Gagal menyimpan dokumen');
    btn.textContent = _serasiEditId?'Simpan Perubahan':'Tambah Dokumen';
    return;
  }
  serasiCloseForm();
  showSuccess(res.message||'Berhasil');
  const docsRes = await apiGet('serasi/documents');
  if (docsRes&&docsRes.status) _serasiDocs = docsRes.data.items;
  _serasiRenderTable(_serasiDocs);
  _serasiPopulateFilters();
}

async function serasiDeleteDoc(id, name) {
  if (!confirm(`Hapus dokumen "${name}"?`)) return;
  const res = await apiDelete(`serasi/documents/${id}`);
  if (!res||!res.status) { showError(res?res.message:'Gagal menghapus'); return; }
  showSuccess('Dokumen dihapus.');
  _serasiDocs = _serasiDocs.filter(d=>d.document_id!==id);
  _serasiRenderTable(_serasiDocs);
}

// ---------------------------------------------------------------
// Tag panel
// ---------------------------------------------------------------
function _serasiRenderTagPanel() {
  const el = document.getElementById('serasiTagPanel'); if (!el) return;
  el.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div class="section-title">Kelola Tag</div>
    </div>
    <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
      <input type="text" class="serasi-form-input" id="newTagName" placeholder="Nama tag baru..." style="max-width:220px">
      <input type="color" id="newTagColor" value="#8b5cf6" style="width:40px;height:38px;border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;padding:2px">
      <button class="btn btn-primary btn-sm" onclick="serasiCreateTag()">
        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        Tambah Tag
      </button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Tag</th><th>Warna</th><th>Jumlah Dokumen</th><th>Aksi</th></tr></thead>
        <tbody id="serasiTagBody"></tbody>
      </table>
    </div>`;
  _serasiRenderTagTable();
}

function _serasiRenderTagTable() {
  const tbody = document.getElementById('serasiTagBody'); if (!tbody) return;
  const isAdmin = hasPermission('serasi.manage');
  if (!_serasiTagList.length) {
    tbody.innerHTML=`<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--gray-400)">Belum ada tag.</td></tr>`;
    return;
  }
  tbody.innerHTML = _serasiTagList.map((t,i)=>{
    const nm = t.tag_name||t; const warna = t.warna||'#8b5cf6'; const jml = t.jumlah_dokumen||0; const tid = t.tag_id||i;
    return `<tr>
      <td style="color:var(--gray-400);font-size:12px">${i+1}</td>
      <td><span class="serasi-tag-chip" style="background:${warna}22;color:${warna}">${esc(nm)}</span></td>
      <td><div style="width:20px;height:20px;border-radius:4px;background:${warna};border:1px solid var(--gray-200)"></div></td>
      <td style="font-weight:600">${jml}</td>
      <td>${isAdmin?`<button class="btn btn-ghost btn-sm serasi-action-btn" style="color:var(--danger)"
          onclick="serasiDeleteTag(${tid},'${esc(nm).replace(/'/g,"\\'")}')">
        <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
        Hapus</button>`:'-'}</td>
    </tr>`;
  }).join('');
}

async function serasiCreateTag() {
  const nm=(document.getElementById('newTagName').value||'').trim();
  const warna=document.getElementById('newTagColor').value;
  if (!nm) { showError('Nama tag tidak boleh kosong'); return; }
  const res = await apiPost('serasi/tags',{tag_name:nm,warna});
  if (!res||!res.status) { showError(res?res.message:'Gagal'); return; }
  showSuccess('Tag ditambahkan.');
  document.getElementById('newTagName').value='';
  const tagRes=await apiGet('serasi/tags');
  if (tagRes&&tagRes.status) _serasiTagList=tagRes.data.items;
  _serasiRenderTagTable();
}

async function serasiDeleteTag(id, name) {
  if (!confirm(`Hapus tag "${name}"?`)) return;
  const res=await apiDelete(`serasi/tags/${id}`);
  if (!res||!res.status) { showError(res?res.message:'Gagal'); return; }
  showSuccess('Tag dihapus.');
  _serasiTagList=_serasiTagList.filter(t=>t.tag_id!==id);
  _serasiRenderTagTable();
}

// ---------------------------------------------------------------
// Knowledge Map
// ---------------------------------------------------------------
function _serasiRenderKnowledgeMap() {
  const c=document.getElementById('serasiKnowledgeMap'); if (!c) return;
  const chain=[
    {label:'Policy Brief',    color:'#3b82f6',count:_serasiDocs.filter(d=>d.jenis_dokumen==='Policy Brief').length},
    {label:'Telaah',          color:'#8b5cf6',count:_serasiDocs.filter(d=>d.jenis_dokumen==='Telaah').length},
    {label:'Kajian Analisis', color:'#10b981',count:_serasiDocs.filter(d=>d.jenis_dokumen==='Kajian Analisis').length},
    {label:'Notula',          color:'#f59e0b',count:_serasiDocs.filter(d=>d.jenis_dokumen==='Notula').length},
    {label:'Laporan',         color:'#ef4444',count:_serasiDocs.filter(d=>d.jenis_dokumen==='Laporan').length},
  ];
  const tagMap={};
  _serasiDocs.forEach(d=>(d.tags||[]).forEach(t=>{if(!tagMap[t])tagMap[t]=[];tagMap[t].push(d);}));
  const topTags=Object.entries(tagMap).sort((a,b)=>b[1].length-a[1].length).slice(0,10);
  c.innerHTML=`
    <div class="serasi-km-section">
      <div class="serasi-section-label" style="margin-bottom:16px">Rantai Produksi Dokumen</div>
      <div class="serasi-km-chain">
        ${chain.map((n,i)=>`
          <div class="serasi-km-node" style="border-color:${n.color}">
            <div class="serasi-km-node-label" style="color:${n.color}">${n.label}</div>
            <div class="serasi-km-node-count">${n.count} dok.</div>
          </div>
          ${i<chain.length-1?'<div class="serasi-km-arrow">↓</div>':''}`).join('')}
      </div>
    </div>
    <div class="serasi-km-section" style="margin-top:32px">
      <div class="serasi-section-label" style="margin-bottom:16px">Top 10 Tag — Kluster Dokumen</div>
      <div class="serasi-km-tags">
        ${topTags.map(([tag,docs])=>`
          <div class="serasi-km-tag-cluster" onclick="serasiFilterByTag('${esc(tag).replace(/'/g,"\\'")}')">
            <div class="serasi-km-tag-name">${esc(tag)}</div>
            <div class="serasi-km-tag-docs">${docs.length} dokumen</div>
            <div class="serasi-km-tag-bar"><div style="width:${Math.min(100,Math.round(docs.length/Math.max(_serasiDocs.length,1)*500))}%;height:4px;background:var(--primary-light);border-radius:2px"></div></div>
          </div>`).join('')}
      </div>
    </div>`;
}

function serasiFilterByTag(tag) {
  const btn=document.querySelector('[data-tab="arsip"]');
  serasiSwitchTab('arsip',btn);
  const sel=document.getElementById('filterTag'); if(sel) sel.value=tag;
  _serasiFilters.tag=tag;
  _serasiApplyFiltersAndSearch();
}

// ---------------------------------------------------------------
// Statistik
// ---------------------------------------------------------------
function _serasiRenderStatistik() {
  const el=document.getElementById('serasiStatistikContent');
  if (!el) return;
  const byJenis={},byTahun={},byTim={},byProgram={};
  _serasiDocs.forEach(d=>{
    byJenis[d.jenis_dokumen||'Lainnya']=(byJenis[d.jenis_dokumen||'Lainnya']||0)+1;
    byTahun[d.tahun||'?']=(byTahun[d.tahun||'?']||0)+1;
    byTim[d.tim_penyusun||'Tidak Diketahui']=(byTim[d.tim_penyusun||'Tidak Diketahui']||0)+1;
    byProgram[d.program||'Tidak Diketahui']=(byProgram[d.program||'Tidak Diketahui']||0)+1;
  });
  const bar=(obj,title)=>{
    const entries=Object.entries(obj).sort((a,b)=>b[1]-a[1]);
    const mx=entries[0]?.[1]||1;
    return `<div class="serasi-stat-section">
      <div class="serasi-section-label" style="margin-bottom:12px">${title}</div>
      ${entries.map(([k,v])=>`<div class="serasi-stat-row">
        <div class="serasi-stat-row-label">${esc(String(k))}</div>
        <div class="serasi-stat-row-bar"><div class="serasi-stat-row-fill" style="width:${Math.round(v/mx*100)}%"></div></div>
        <div class="serasi-stat-row-val">${v}</div>
      </div>`).join('')}
    </div>`;
  };
  el.innerHTML=`<div class="serasi-stat-grid3">
    ${bar(byJenis,'Dokumen per Jenis')}${bar(byTahun,'Dokumen per Tahun')}
    ${bar(byProgram,'Dokumen per Program')}${bar(byTim,'Dokumen per Tim')}
  </div>`;
}

// ---------------------------------------------------------------
// SharePoint Panel
// ---------------------------------------------------------------
function _serasiRenderSharePointPanel() {
  const el=document.getElementById('serasiSharePointPanel'); if (!el) return;
  el.innerHTML=`
    <div class="serasi-od-wrap">

      <!-- Status -->
      <div class="serasi-od-card">
        <div class="serasi-od-card-header">
          <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" style="color:#0078d4"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
          <div>
            <div class="serasi-od-card-title">Integrasi OneDrive / SharePoint Online</div>
            <div class="serasi-od-card-sub">via Microsoft Graph API / SharePoint REST API · OAuth 2.0 · Azure AD</div>
          </div>
          <div id="serasiSpStatus" class="serasi-od-status-badge serasi-od-status-pending">
            <span class="serasi-od-status-dot"></span> Memeriksa...
          </div>
        </div>
        <div class="serasi-od-info-grid">
          <div class="serasi-od-info-item">
            <div class="serasi-od-info-label">Metode Auth</div>
            <div class="serasi-od-info-val" id="serasiSpAuthMethod">—</div>
          </div>
          <div class="serasi-od-info-item">
            <div class="serasi-od-info-label">Endpoint Dasar</div>
            <div class="serasi-od-info-val"><code>{SITE_URL}/_api/web/...</code></div>
          </div>
          <div class="serasi-od-info-item">
            <div class="serasi-od-info-label">Terakhir Sinkronisasi</div>
            <div class="serasi-od-info-val" id="serasiSpLastSync">—</div>
          </div>
          <div class="serasi-od-info-item">
            <div class="serasi-od-info-label">Library Path</div>
            <div class="serasi-od-info-val" id="serasiSpLibPath">—</div>
          </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
          <button class="btn btn-primary" onclick="serasiSpTest()" id="serasiSpTestBtn">
            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
            Test Koneksi
          </button>
          <button class="btn btn-secondary" onclick="serasiSpSyncFolders()" id="serasiSpSyncBtn">
            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
            Sinkronisasi Folder
          </button>
        </div>
        <div id="serasiSpTestResult" style="margin-top:10px"></div>
      </div>

      <!-- Konfigurasi .env -->
      <div class="serasi-od-card">
        <div class="serasi-od-card-title" style="margin-bottom:12px">Konfigurasi (.env) — Pilih Salah Satu Metode</div>

        <!-- Tab metode -->
        <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap" id="serasiAuthTabs">
          <button class="btn btn-primary btn-sm" onclick="serasiShowAuthMethod('apponly',this)" id="tabApponly">
            ★ Metode 2 — AppInv.aspx (Tanpa Azure Admin)
          </button>
          <button class="btn btn-ghost btn-sm" onclick="serasiShowAuthMethod('azure',this)" id="tabAzure">
            Metode 1 — Azure AD
          </button>
          <button class="btn btn-ghost btn-sm" onclick="serasiShowAuthMethod('password',this)" id="tabPassword">
            Metode 3 — Username/Password
          </button>
        </div>

        <!-- Konten per metode -->
        <div id="serasiAuthMethodContent">
          <div id="serasiAuthApponly">
            <div style="background:#e6f4ea;border:1px solid #1a7f37;border-radius:6px;padding:10px 14px;font-size:12.5px;margin-bottom:10px">
              ✅ <strong>Tidak perlu Azure Portal Admin.</strong> Cukup akses Site Collection Admin SharePoint.
            </div>
            <div class="serasi-od-code">SHAREPOINT_AUTH_METHOD   = apponly
SHAREPOINT_SITE_URL      = https://contoso.sharepoint.com/sites/NamaSite
SHAREPOINT_CLIENT_ID     = &lt;client-id&gt;@&lt;tenant-id&gt;
SHAREPOINT_CLIENT_SECRET = &lt;client-secret&gt;
SHAREPOINT_LIB_PATH      = /sites/NamaSite/Shared Documents/Direktorat Sistem Kearsipan

# SHAREPOINT_TENANT_ID tidak wajib — diambil otomatis dari SharePoint</div>
          </div>
          <div id="serasiAuthAzure" style="display:none">
            <div style="background:#fff3e0;border:1px solid #e07b28;border-radius:6px;padding:10px 14px;font-size:12.5px;margin-bottom:10px">
              ⚠️ Memerlukan <strong>Global Admin Azure AD</strong> untuk Grant Consent.
            </div>
            <div class="serasi-od-code">SHAREPOINT_AUTH_METHOD   = azure
SHAREPOINT_SITE_URL      = https://contoso.sharepoint.com/sites/NamaSite
SHAREPOINT_TENANT_ID     = &lt;tenant-id&gt;
SHAREPOINT_CLIENT_ID     = &lt;app-client-id&gt;
SHAREPOINT_CLIENT_SECRET = &lt;app-client-secret&gt;
SHAREPOINT_LIB_PATH      = /sites/NamaSite/Shared Documents/Direktorat Sistem Kearsipan</div>
          </div>
          <div id="serasiAuthPassword" style="display:none">
            <div style="background:#fde8e8;border:1px solid #cf222e;border-radius:6px;padding:10px 14px;font-size:12.5px;margin-bottom:10px">
              ⚠️ Hanya untuk akun service yang <strong>MFA-nya dinonaktifkan</strong>. Tidak disarankan untuk akun personal.
            </div>
            <div class="serasi-od-code">SHAREPOINT_AUTH_METHOD   = password
SHAREPOINT_SITE_URL      = https://contoso.sharepoint.com/sites/NamaSite
SHAREPOINT_TENANT_ID     = &lt;tenant-id&gt;
SHAREPOINT_USERNAME      = serviceaccount@contoso.onmicrosoft.com
SHAREPOINT_PASSWORD      = &lt;password-akun-service&gt;
SHAREPOINT_LIB_PATH      = /sites/NamaSite/Shared Documents/Direktorat Sistem Kearsipan

# SHAREPOINT_CLIENT_ID tidak wajib (pakai client ID Microsoft Office bawaan)</div>
          </div>
        </div>
      </div>

      <!-- Browse & Import -->
      <div class="serasi-od-card">
        <div class="serasi-od-card-title" style="margin-bottom:4px">Browse &amp; Import File</div>
        <div style="font-size:12.5px;color:var(--gray-400);margin-bottom:14px">
          Jelajahi folder SharePoint dan impor metadata file ke SERASI.
        </div>
        <div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap">
          <input type="text" class="serasi-form-input" id="serasiSpBrowsePath"
                 placeholder="Server Relative URL folder, contoh: /sites/DSK/Shared Documents/..."
                 style="flex:1;min-width:260px">
          <button class="btn btn-secondary" onclick="serasiSpBrowse()">
            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
            Browse Folder
          </button>
        </div>
        <div id="serasiSpBrowseResult"></div>
      </div>

      <!-- Import file individual -->
      <div class="serasi-od-card">
        <div class="serasi-od-card-title" style="margin-bottom:4px">Import Metadata File</div>
        <div style="font-size:12.5px;color:var(--gray-400);margin-bottom:14px">
          Masukkan Server Relative URL file untuk membaca metadata dari SharePoint.
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
          <input type="text" class="serasi-form-input" id="serasiSpImportPath"
                 placeholder="/sites/DSK/Shared Documents/.../nama-file.docx"
                 style="flex:1;min-width:260px">
          <button class="btn btn-primary" onclick="serasiSpImportFile()" id="serasiSpImportBtn">
            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
            Import Metadata
          </button>
        </div>
        <div id="serasiSpImportResult"></div>
      </div>

      <!-- SharePoint REST API Endpoints -->
      <div class="serasi-od-card">
        <div class="serasi-od-card-title" style="margin-bottom:12px">SharePoint REST API — Endpoint Referensi</div>
        <div class="serasi-od-code">GET {SITE_URL}/_api/web
GET {SITE_URL}/_api/web/lists
GET {SITE_URL}/_api/web/GetFolderByServerRelativeUrl('{path}')
GET {SITE_URL}/_api/web/GetFolderByServerRelativeUrl('{path}')/Folders
GET {SITE_URL}/_api/web/GetFolderByServerRelativeUrl('{path}')/Files
GET {SITE_URL}/_api/web/GetFileByServerRelativeUrl('{path}')
Headers: Accept: application/json;odata=nometadata
         Authorization: Bearer {access_token}</div>
      </div>

      <!-- Panduan per metode -->
      <div class="serasi-od-card">
        <div class="serasi-od-card-title" style="margin-bottom:12px">
          ★ Panduan Setup — Metode 2: AppInv.aspx (Tanpa Azure Portal Admin)
        </div>
        <div class="serasi-od-steps">
          <div class="serasi-od-step"><div class="serasi-od-step-num">1</div>
            <div>Buka browser, login sebagai <strong>Site Collection Admin</strong> SharePoint, lalu buka:<br>
            <code>https://&lt;tenant&gt;.sharepoint.com/sites/&lt;site&gt;/_layouts/15/appregnew.aspx</code></div>
          </div>
          <div class="serasi-od-step"><div class="serasi-od-step-num">2</div>
            <div>Klik <strong>Generate</strong> di samping <em>Client Id</em> dan <em>Client Secret</em> → Isi:<br>
            • Title: SERASI-DSK<br>
            • App Domain: <code>localhost</code><br>
            • Redirect URI: <code>https://localhost</code><br>
            → Klik <strong>Create</strong>. <strong>Salin Client Id dan Client Secret!</strong></div>
          </div>
          <div class="serasi-od-step"><div class="serasi-od-step-num">3</div>
            <div>Buka halaman trust app:<br>
            <code>https://&lt;tenant&gt;.sharepoint.com/sites/&lt;site&gt;/_layouts/15/appinv.aspx</code><br>
            Paste <strong>Client Id</strong> → klik <strong>Lookup</strong></div>
          </div>
          <div class="serasi-od-step"><div class="serasi-od-step-num">4</div>
            <div>Di kolom <em>Permission Request XML</em>, paste:<br>
            <code>&lt;AppPermissionRequests AllowAppOnlyPolicy="true"&gt;<br>
            &nbsp;&nbsp;&lt;AppPermissionRequest Scope="http://sharepoint/content/sitecollection" Right="Read"/&gt;<br>
            &lt;/AppPermissionRequests&gt;</code><br>
            → Klik <strong>Create</strong> → <strong>Trust It</strong></div>
          </div>
          <div class="serasi-od-step"><div class="serasi-od-step-num">5</div>
            <div>Cari <strong>Tenant ID</strong> Anda: buka <code>https://&lt;tenant&gt;.sharepoint.com/_layouts/15/appprincipals.aspx</code>
            atau biarkan kosong — sistem akan mengambilnya otomatis.</div>
          </div>
          <div class="serasi-od-step"><div class="serasi-od-step-num">6</div>
            <div>Isi <code>.env</code> dengan format:<br>
            <code>SHAREPOINT_CLIENT_ID = &lt;client-id&gt;@&lt;tenant-id&gt;</code><br>
            <code>SHAREPOINT_CLIENT_SECRET = &lt;client-secret&gt;</code></div>
          </div>
          <div class="serasi-od-step"><div class="serasi-od-step-num">7</div>
            <div>Klik <strong>Test Koneksi</strong> → jika berhasil klik <strong>Sinkronisasi Folder</strong></div>
          </div>
        </div>
      </div>
    </div>`;
  _serasiSpCheckStatus();
}

function serasiShowAuthMethod(method, btn) {
  ['apponly','azure','password'].forEach(m => {
    const el = document.getElementById('serasiAuth' + m.charAt(0).toUpperCase() + m.slice(1));
    if (el) el.style.display = m === method ? '' : 'none';
  });
  document.querySelectorAll('#serasiAuthTabs .btn').forEach(b => {
    b.className = b === btn ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';
  });
}

async function _serasiSpCheckStatus() {
  const res = await apiGet('serasi/sharepoint/status');
  const badge  = document.getElementById('serasiSpStatus');
  const last   = document.getElementById('serasiSpLastSync');
  const lib    = document.getElementById('serasiSpLibPath');
  const method = document.getElementById('serasiSpAuthMethod');
  if (!badge) return;
  if (res && res.status && res.data && res.data.configured) {
    badge.className = 'serasi-od-status-badge serasi-od-status-ok';
    badge.innerHTML = '<span class="serasi-od-status-dot"></span> Dikonfigurasi';
    if (last)   last.textContent   = res.data.last_sync || 'Belum pernah';
    if (lib)    lib.textContent    = res.data.lib_path  || '—';
    if (method) {
      const labels = { azure:'Azure AD (Client Credentials)', apponly:'App-Only (AppInv.aspx)', password:'Username + Password (ROPC)' };
      method.textContent = labels[res.data.auth_method] || res.data.auth_method || '—';
    }
  } else {
    badge.className = 'serasi-od-status-badge serasi-od-status-pending';
    badge.innerHTML = '<span class="serasi-od-status-dot"></span> Belum dikonfigurasi';
    if (method) method.textContent = 'Belum dikonfigurasi';
  }
}

async function serasiSpTest() {
  const btn=document.getElementById('serasiSpTestBtn');
  const res_el=document.getElementById('serasiSpTestResult');
  btn.disabled=true; btn.textContent='Menguji...';
  const res = await apiPost('serasi/sharepoint/test',{});
  btn.disabled=false;
  btn.innerHTML=`<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg> Test Koneksi`;
  if (res&&res.status) {
    res_el.innerHTML=`<div class="serasi-od-alert serasi-od-alert-ok">✓ ${esc(res.message)} — Site: ${esc(res.data&&res.data.site_title?res.data.site_title:'')}</div>`;
    _serasiSpCheckStatus();
  } else {
    res_el.innerHTML=`<div class="serasi-od-alert serasi-od-alert-err">✗ ${res?esc(res.message):'Koneksi gagal. Periksa konfigurasi .env'}</div>`;
  }
}

async function serasiSpSyncFolders() {
  const btn=document.getElementById('serasiSpSyncBtn');
  btn.disabled=true; btn.textContent='Menyinkronkan...';
  const res=await apiPost('serasi/sharepoint/sync-folders',{});
  btn.disabled=false;
  btn.innerHTML=`<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg> Sinkronisasi Folder`;
  if (res&&res.status) {
    showSuccess(res.message||'Sinkronisasi selesai');
    const fr=await apiGet('serasi/folders');
    if (fr&&fr.status) { _serasiFolderTree=fr.data.items; _serasiRenderFolderTree(_serasiFolderTree); }
    document.getElementById('serasiSpTestResult').innerHTML=
      `<div class="serasi-od-alert serasi-od-alert-ok">✓ ${esc(res.message)}</div>`;
  } else {
    document.getElementById('serasiSpTestResult').innerHTML=
      `<div class="serasi-od-alert serasi-od-alert-err">✗ ${res?esc(res.message):'Sinkronisasi gagal.'}</div>`;
  }
}

async function serasiSpBrowse() {
  const path=(document.getElementById('serasiSpBrowsePath').value||'').trim();
  const res_el=document.getElementById('serasiSpBrowseResult');
  res_el.innerHTML='<div style="color:var(--gray-400);font-size:13px">Memuat...</div>';
  const url = path ? `serasi/sharepoint/browse?path=${encodeURIComponent(path)}` : 'serasi/sharepoint/browse';
  const res  = await apiGet(url);
  if (!res||!res.status) {
    res_el.innerHTML=`<div class="serasi-od-alert serasi-od-alert-err">✗ ${res?esc(res.message):'Gagal browse folder'}</div>`;
    return;
  }
  const items=res.data.items||[];
  res_el.innerHTML=items.length
    ? `<div class="serasi-od-folder-list">${items.map(f=>`
        <div class="serasi-od-folder-row">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="color:#f59e0b;flex-shrink:0"><path d="M20 6h-8l-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z"/></svg>
          <span style="flex:1">${esc(f.name)}</span>
          <span style="font-size:11px;color:var(--gray-400)">${f.item_count||0} item</span>
          <button class="btn btn-ghost btn-sm serasi-action-btn" style="font-size:11px"
              onclick="document.getElementById('serasiSpBrowsePath').value='${esc(f.path)}';serasiSpBrowse()">Buka</button>
        </div>`).join('')}</div>`
    : '<div class="serasi-od-alert serasi-od-alert-ok">Folder kosong atau tidak ada sub-folder.</div>';
}

async function serasiSpImportFile() {
  const path=(document.getElementById('serasiSpImportPath').value||'').trim();
  const res_el=document.getElementById('serasiSpImportResult');
  const btn=document.getElementById('serasiSpImportBtn');
  if (!path) { showError('Masukkan path file SharePoint'); return; }
  btn.disabled=true; btn.textContent='Mengimpor...';
  const res=await apiPost('serasi/sharepoint/import-file',{path});
  btn.disabled=false;
  btn.innerHTML=`<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg> Import Metadata`;
  if (res&&res.status) {
    res_el.innerHTML=`<div class="serasi-od-alert serasi-od-alert-ok">✓ ${esc(res.message)}</div>`;
    const docsRes=await apiGet('serasi/documents');
    if (docsRes&&docsRes.status) { _serasiDocs=docsRes.data.items; _serasiRenderTable(_serasiDocs); }
  } else {
    res_el.innerHTML=`<div class="serasi-od-alert serasi-od-alert-err">✗ ${res?esc(res.message):'Import gagal'}</div>`;
  }
}

// ---------------------------------------------------------------
// Demo / Fallback data
// ---------------------------------------------------------------
function _serasiDemoStats() {
  return {total:12,policy_brief:3,policy_paper:2,telaah:1,kajian:2,pok_kak:2,notula:3,daftar_hadir:0,laporan:1};
}
function _serasiDemoTags() {
  return ['SIKN','Telaah','Kemaritiman','Grand Desain','SOP','Tata Kelola','Kebijakan','Jurnal']
    .map((t,i)=>({tag_id:i+1,tag_name:t,warna:'#8b5cf6',jumlah_dokumen:Math.floor(Math.random()*5+1)}));
}
function _serasiDemoFolders() {
  return [{folder_id:1,folder_name:'Direktorat Sistem Kearsipan',full_path:'/sites/DSK/Shared Documents/Direktorat Sistem Kearsipan',item_count:42,children:[
    {folder_id:2,folder_name:'Rekomendasi Kebijakan 2026',full_path:'/sites/DSK/Shared Documents/Direktorat Sistem Kearsipan/Rekomendasi Kebijakan 2026',item_count:18,children:[
      {folder_id:3,folder_name:'GD Sistem Informasi Kearsipan Nasional',full_path:'.../GD Sistem Informasi Kearsipan Nasional',item_count:8,children:[
        {folder_id:4,folder_name:'GRAND DESAIN SIKN',full_path:'.../GRAND DESAIN SIKN',item_count:3,children:[]},
        {folder_id:5,folder_name:'Arsip Final',full_path:'.../Arsip Final',item_count:5,children:[]},
      ]},
      {folder_id:6,folder_name:'Knowledge Management',full_path:'.../Knowledge Management',item_count:6,children:[]},
    ]},
    {folder_id:7,folder_name:'Probis dan SOP',full_path:'.../Probis dan SOP',item_count:8,children:[]},
    {folder_id:8,folder_name:'Progress Report',full_path:'.../Progress Report',item_count:4,children:[]},
  ]}];
}
function _serasiDemoDocs() {
  return [
    {document_id:1,file_name:'Policy Brief SIKN 2026.docx',jenis_dokumen:'Policy Brief',tahun:2026,program:'Rekomendasi Kebijakan 2026',sub_kegiatan:'GD SIKN',tim_penyusun:'Tim SIKN',kata_kunci:'SIKN kebijakan sistem informasi',tags:['SIKN','Grand Desain'],full_path:'/sites/DSK/.../GD Sistem Informasi Kearsipan Nasional',modified_date:'2026-03-01',sharepoint_web_url:'#'},
    {document_id:2,file_name:'Telaah Tata Kelola Kearsipan.docx',jenis_dokumen:'Telaah',tahun:2026,program:'Rekomendasi Kebijakan 2026',sub_kegiatan:'Knowledge Management',tim_penyusun:'Tim Kajian',kata_kunci:'telaah tata kelola arsip digital',tags:['Telaah','Tata Kelola'],full_path:'/sites/DSK/.../Knowledge Management',modified_date:'2026-01-20',sharepoint_web_url:'#'},
    {document_id:3,file_name:'Notula Rapat GD SIKN - 12 Feb 2026.docx',jenis_dokumen:'Notula',tahun:2026,program:'Rekomendasi Kebijakan 2026',sub_kegiatan:'GD SIKN',tim_penyusun:'Sekretariat',kata_kunci:'rapat SIKN notulensi',tags:['SIKN','Notula'],full_path:'/sites/DSK/.../GD Sistem Informasi Kearsipan Nasional',modified_date:'2026-02-13',sharepoint_web_url:'#'},
    {document_id:4,file_name:'KAK Pengembangan SERASI 2026.docx',jenis_dokumen:'KAK',tahun:2026,program:'Rekomendasi Kebijakan 2026',sub_kegiatan:'SERASI',tim_penyusun:'Tim IT',kata_kunci:'KAK SERASI pengembangan',tags:['KAK'],full_path:'/sites/DSK/.../KAK 2026',modified_date:'2026-01-06',sharepoint_web_url:'#'},
    {document_id:5,file_name:'Kajian Analisis Kemaritiman.pdf',jenis_dokumen:'Kajian Analisis',tahun:2025,program:'Rekomendasi Kebijakan 2025',sub_kegiatan:'ISDRA',tim_penyusun:'Tim Kemaritiman',kata_kunci:'kemaritiman analisis kebijakan',tags:['Kemaritiman','Telaah'],full_path:'/sites/DSK/.../ISDRA',modified_date:'2025-11-20',sharepoint_web_url:'#'},
    {document_id:6,file_name:'SOP Tata Naskah Dinas 2025.pdf',jenis_dokumen:'Laporan',tahun:2025,program:'Probis dan SOP',sub_kegiatan:'SOP',tim_penyusun:'Tim Probis',kata_kunci:'SOP naskah dinas prosedur',tags:['SOP','Tata Kelola'],full_path:'/sites/DSK/.../Probis dan SOP',modified_date:'2025-07-01',sharepoint_web_url:'#'},
  ];
}
