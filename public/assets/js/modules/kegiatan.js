/* ============================================================
   kegiatan.js — Kegiatan page, Tasks, Realisasi (On-Budget),
                 Dokumen
   ============================================================ */
'use strict';

// Registry untuk menyimpan object data agar tidak perlu JSON.stringify di inline onclick
// Mencegah crash template literal akibat karakter backtick/$ di dalam data
const _kegReg   = {};   // kegiatanId  → kegiatan object
const _usersReg = {};   // kegiatanId  → users array
const _taskReg  = {};   // taskId      → task/subtask object

function _getKeg(id)   { return _kegReg[id]; }
function _getUsers(id) { return _usersReg[id] || []; }
function _getTask(id)  { return _taskReg[id]; }

// ---------------------------------------------------------------
// Kegiatan Page
// ---------------------------------------------------------------
async function renderKegiatan() {
  const [kegRes, roRes] = await Promise.all([
    apiGet('kegiatan'),
    apiGet('ro'),
  ]);

  if (!kegRes || !kegRes.status) { showError('Gagal memuat kegiatan'); return; }

  // Hanya tampilkan on_budget di halaman Kegiatan
  const rows  = (kegRes.data || []).filter(k => k.tipe !== 'off_budget');
  const roList = (roRes?.status && roRes.data) ? roRes.data : [];

  // Build add buttons
  let addBtns = '';
  if (hasPermission('kegiatan.create')) {
    addBtns += `<button class="btn btn-primary" onclick="showKegiatanForm()">
      <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
      Tambah Kegiatan
    </button>`;
  }
  if (hasPermission('kegiatan.create')) {
    addBtns += `<button class="btn btn-outline-primary ms-2" onclick="showRoForm()">
      <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
      Tambah RO
    </button>`;
  }

  // Group kegiatan by ro_id
  const byRo = {};
  const standalone = [];
  for (const k of rows) {
    if (k.ro_id) {
      if (!byRo[k.ro_id]) byRo[k.ro_id] = [];
      byRo[k.ro_id].push(k);
    } else {
      standalone.push(k);
    }
  }

  // Build RO sections
  let roHtml = '';
  for (const ro of roList) {
    const children = byRo[ro.id] || [];
    const cards    = children.map(k => kegiatanCard(k)).join('');
    roHtml += renderRoSection(ro, cards);
  }

  // Standalone section
  let standaloneHtml = '';
  if (standalone.length > 0) {
    standaloneHtml = `
      <div class="ro-standalone-header">Kegiatan Lainnya (tanpa RO)</div>
      <div class="kegiatan-grid">
        ${standalone.map(k => kegiatanCard(k)).join('')}
      </div>
    `;
  }

  const hasContent = roList.length > 0 || rows.length > 0;

  document.getElementById('mainContent').innerHTML = `
    <div class="section-header">
      <div class="section-title">Daftar Kegiatan (${rows.length})</div>
      <div class="d-flex gap-2 flex-wrap">${addBtns}</div>
    </div>
    <div id="kegiatanGrid">
      ${hasContent
        ? roHtml + standaloneHtml
        : emptyState('Belum ada kegiatan')}
    </div>
  `;
}

function kegiatanCard(k, onclickFn = null) {
  const isOffBudget  = k.tipe === 'off_budget';
  const clickHandler = onclickFn || `showKegiatanDetail(${k.id})`;
  return `
    <div class="kegiatan-card${isOffBudget ? ' off-budget-card' : ''}" data-keg-id="${k.id}"${k.ro_id ? ` data-ro-id="${k.ro_id}"` : ''} onclick="${clickHandler}">
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
          <span>Progress Task
            <span style="font-weight:400;color:#6b7280">
              (${k.tasks_selesai}/${k.total_tasks} selesai${k.tasks_berjalan > 0 ? ` · <span style="color:#d97706">${k.tasks_berjalan} berjalan</span>` : ''})
            </span>
          </span>
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
  refreshCardInGrid(currentModalKegId);
  if (currentModalTipe === 'off_budget') showOffBudgetDetail(currentModalKegId);
  else showKegiatanDetail(currentModalKegId);
}

/**
 * Fetch data terbaru kegiatan dan update card-nya di DOM (jika ada di grid).
 * Dipanggil setiap kali ada perubahan — tidak mengganggu modal yang sedang terbuka.
 */
async function refreshCardInGrid(kegiatanId) {
  const selector = `[data-keg-id="${kegiatanId}"]`;
  const cardEl = document.querySelector(selector);
  if (!cardEl) return;

  // Catat ro_id sebelum card diganti
  const roId = cardEl.dataset.roId ? parseInt(cardEl.dataset.roId) : null;

  // Gunakan endpoint single-item agar on_budget & off_budget sama-sama ter-refresh
  const res = await apiGet(`kegiatan/${kegiatanId}`);
  if (!res || !res.status) return;
  const k = res.data;
  if (!k) return;

  const isOB    = k.tipe === 'off_budget';
  const onclick = isOB ? `showOffBudgetDetail(${k.id})` : `showKegiatanDetail(${k.id})`;
  const newCard = document.createElement('div');
  newCard.innerHTML = kegiatanCard(k, onclick);
  const newEl = newCard.firstElementChild;
  cardEl.replaceWith(newEl);

  // Perbarui progress bar RO secara real-time (tanpa re-render halaman)
  const effectiveRoId = roId || (k.ro_id ? parseInt(k.ro_id) : null);
  if (effectiveRoId) refreshRoStats(effectiveRoId);
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

  // Simpan ke registry agar inline onclick tidak perlu JSON.stringify
  _kegReg[k.id]   = k;
  _usersReg[k.id] = users;
  (k.tasks || []).forEach(t => {
    _taskReg[t.id] = t;
    (t.subtasks || []).forEach(s => { _taskReg[s.id] = s; });
  });

  document.getElementById('modalTitle').textContent = k.nama_kegiatan;

  document.getElementById('modalBody').innerHTML = `
    <div class="tabs">
      <div class="tab active" data-tab="tasks">✅ Task (${k.tasks.length})</div>
      <div class="tab" data-tab="rincian">📋 Rincian Anggaran</div>
      <div class="tab" data-tab="anggaran">💰 Realisasi</div>
      <div class="tab" data-tab="info">ℹ️ Info</div>
      <div class="tab" data-tab="dokumen">📎 Dokumen</div>
    </div>

    <!-- Tab: Tasks -->
    <div class="tab-content active" id="tab-tasks">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <span style="font-size:12px;color:#64748b">${k.tasks_selesai} dari ${k.total_tasks} task selesai${k.tasks_berjalan > 0 ? ` · <span style="color:#d97706">${k.tasks_berjalan} sedang berjalan</span>` : ''}</span>
        ${hasPermission('task.create') ? `
          <button class="btn btn-primary btn-sm" onclick="showAddTask(${k.id})">+ Tambah Sub-Kegiatan</button>
        ` : ''}
      </div>
      <div class="progress-wrap" style="margin-bottom:16px">
        <div class="progress-bar">
          <div class="progress-fill progress-${k.warna_status || progressColor(k.progress)}" style="width:${k.progress}%"></div>
        </div>
        <div style="text-align:right;font-size:12px;color:#64748b;margin-top:4px">${k.progress}%</div>
      </div>
      <div class="task-list" id="taskList-${k.id}">
        ${k.tasks.map(t => taskItem(t, k.id)).join('') || emptyState('Belum ada task')}
      </div>
    </div>

    <!-- Tab: Rincian Anggaran -->
    <div class="tab-content" id="tab-rincian">
      <div id="tab-rincian-${k.id}">
        <div style="padding:20px;text-align:center"><div class="spinner-lg"></div></div>
      </div>
    </div>

    <!-- Tab: Realisasi Anggaran -->
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
      ${hasPermission('anggaran.input') ? `
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

      <!-- Info header: detail kegiatan -->
      <div class="form-row form-row-2" style="margin-bottom:14px">
        <div><div class="ang-summary-label">Nama Kegiatan</div><div style="font-weight:600">${esc(k.nama_kegiatan)}</div></div>
        <div><div class="ang-summary-label">Tim</div><div>${esc(k.nama_tim || '—')}</div></div>
        <div><div class="ang-summary-label">Ketua Tim</div><div>${esc(k.ketua_tim_nama || '—')}</div></div>
        <div><div class="ang-summary-label">Status</div><div><span class="status-badge status-${k.status}">${statusLabel(k.status)}</span></div></div>
        <div><div class="ang-summary-label">Tanggal Mulai Kegiatan</div><div>${fmtDate(k.tanggal_mulai)}</div></div>
        <div><div class="ang-summary-label">Deadline Kegiatan</div><div>${fmtDate(k.tanggal_selesai)}</div></div>
        <div><div class="ang-summary-label">Pagu Anggaran</div><div>${rupiah(k.pagu_anggaran)}</div></div>
        <div><div class="ang-summary-label">Realisasi</div><div>${rupiah(k.total_realisasi)}</div></div>
      </div>
      <div style="margin-bottom:12px"><div class="ang-summary-label">Deskripsi</div><div style="margin-top:4px;color:#374151">${esc(k.deskripsi || '—')}</div></div>
      <div style="margin-bottom:16px">
        <div class="ang-summary-label" style="margin-bottom:6px">Anggota Tim</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          ${(k.anggota || []).map(a => `<span style="background:#eff6ff;color:#1e40af;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600">${esc(a.nama)}</span>`).join('')}
        </div>
      </div>

      <!-- Tabel Task & Subtask -->
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div style="font-weight:700;font-size:13.5px;color:var(--gray-900)">
          Ringkasan Task & Subtask
          <span style="font-size:12px;font-weight:400;color:var(--gray-500);margin-left:6px">(${k.tasks_selesai}/${k.total_tasks} selesai · ${k.progress}%)</span>
        </div>
        <button class="btn btn-sm" onclick="downloadLaporanKegiatan(${k.id})"
          style="background:var(--primary);color:#fff;border:none;border-radius:7px;padding:6px 14px;
                 font-size:12.5px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
            <path d="M19 9h-4V3H9v6H5l7 7 7-7zm-8 2V5h2v6h1.17L12 13.17 9.83 11H11zm-6 8v2h14v-2H5z"/>
          </svg>
          Download Laporan
        </button>
      </div>

      <div class="table-wrap" style="margin-bottom:16px">
        <table style="font-size:12.5px">
          <thead>
            <tr style="background:var(--gray-50)">
              <th style="width:32px">#</th>
              <th>Task / Subtask</th>
              <th style="width:90px">PIC</th>
              <th style="width:100px;text-align:center">Status</th>
              <th style="width:110px;text-align:center">Tgl Berjalan</th>
              <th style="width:110px;text-align:center">Tgl Selesai</th>
              <th style="width:110px;text-align:center">Deadline</th>
              <th style="width:70px;text-align:center">Tepat Waktu</th>
            </tr>
          </thead>
          <tbody>
            ${_kegiatanTaskTableRows(k.tasks)}
          </tbody>
        </table>
      </div>

      <!-- Tombol edit/hapus kegiatan -->
      ${hasPermission('kegiatan.edit') ? `
        <div style="display:flex;gap:10px">
          <button class="btn btn-outline btn-sm" onclick="showEditKegiatan(_getKeg(${k.id}))">Edit Kegiatan</button>
          ${hasPermission('kegiatan.delete') ? `<button class="btn btn-danger btn-sm" onclick="confirmDeleteKegiatan(${k.id},'${esc(k.nama_kegiatan)}')">Hapus</button>` : ''}
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
      if (tab.dataset.tab === 'dokumen')  loadDokumen(k.id);
      if (tab.dataset.tab === 'rincian')  loadRincianAnggaran(k.id);
    });
  });

  // Auto-load tab rincian saat modal pertama buka
  loadRincianAnggaran(k.id);

  document.getElementById('modalFooter').innerHTML = '';
}

// ---------------------------------------------------------------
// Tasks — helper render lampiran (shared task & subtask)
// lampiran berupa link URL — bukan file upload
// ---------------------------------------------------------------
function _taskLampiranHtml(lampiran, kegiatanId, taskId, isSubtask) {
  const canEdit = hasPermission('task.edit')
  const chips   = (lampiran || []).map(l => `
    <span class="task-lampiran-chip" title="${esc(l.uploaded_by_nama||'')} · ${esc(l.created_at||'')}">
      <a href="${esc(l.file_url)}" target="_blank" rel="noopener"
         style="color:inherit;text-decoration:none;max-width:130px;overflow:hidden;
                text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:middle">
        🔗 ${esc(l.nama_file)}
      </a>
      ${canEdit
        ? `<button onclick="event.stopPropagation();_deleteAttachment(${kegiatanId},${taskId},${l.id},this)"
                   style="background:none;border:none;cursor:pointer;color:#dc2626;
                          font-size:13px;line-height:1;padding:0 0 0 3px;vertical-align:middle"
                   title="Hapus bukti dukung">×</button>`
        : ''}
    </span>`).join('')

  const addBtn = canEdit ? `
    <button class="task-lampiran-upload"
            onclick="event.stopPropagation();_showAddLinkModal(${kegiatanId},${taskId})"
            title="Tambah link bukti dukung">
      + Link
    </button>` : ''

  if (!chips && !addBtn) return ''
  return `<div class="task-lampiran-row" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:5px;align-items:center">
    ${chips}${addBtn}
  </div>`
}

// ---------------------------------------------------------------
function taskItem(t, kegiatanId) {
  const users       = _getUsers(kegiatanId);
  const done        = t.status === 'selesai';
  const statusClass = { selesai: 'task-selesai', sedang_berjalan: 'task-berjalan', belum_dimulai: 'task-belum' };
  const subtasks    = t.subtasks || [];
  const subSelesai  = subtasks.filter(s => s.status === 'selesai').length;
  const subBerjalan = subtasks.filter(s => s.status === 'sedang_berjalan').length;
  const hasSubtasks = subtasks.length > 0;
  const lampiran    = t.lampiran || [];

  // Task punya subtask  → lampiran tidak diperlukan di task induk
  // Task tanpa subtask  → harus ada lampiran sebelum bisa selesai
  const hasLampiran    = lampiran.length > 0
  const needsLampiran  = !hasSubtasks   // hanya task tanpa subtask yang wajib lampiran

  const subtaskHtml = hasSubtasks ? `
    <div class="subtask-list" id="subtask-list-${t.id}">
      ${subtasks.map(s => subtaskItem(s, kegiatanId)).join('')}
    </div>
  ` : `<div class="subtask-list" id="subtask-list-${t.id}"></div>`;

  const subPct   = subtasks.length > 0
    ? Math.round(((subSelesai + subBerjalan * 0.5) / subtasks.length) * 100) : 0;
  const subColor = subPct === 100 ? '#16a34a' : subPct >= 50 ? '#3b82f6' : '#f59e0b';
  const subtaskProgress = hasSubtasks ? `
    <div class="subtask-progress">
      <div class="subtask-progress-bar">
        <div class="subtask-progress-fill" style="width:${subPct}%;background:${subColor}"></div>
      </div>
      <span class="subtask-progress-text">${subSelesai}/${subtasks.length} selesai${subBerjalan > 0 ? ` · ${subBerjalan} berjalan` : ''} · ${subPct}%</span>
    </div>
  ` : '';

  const addSubtaskBtn = hasPermission('task.create') ? `
    <button class="btn-add-subtask" onclick="showAddSubtask(event, ${kegiatanId}, ${t.id})">
      + Komponen-Komponen
    </button>
  ` : '';

  // Lock: hanya saat akan menandai SELESAI (sedang_berjalan → selesai) tanpa lampiran
  const checkLocked = (needsLampiran && !hasLampiran && t.status === 'sedang_berjalan')
  const checkTitle  = checkLocked ? 'Tambah link bukti dukung terlebih dahulu' : ''

  const btnLabel = t.status === 'selesai' ? '✓ Selesai'
                 : t.status === 'sedang_berjalan' ? '▶ Berjalan' : '▷ Mulai';
  const btnCls   = t.status === 'selesai' ? 'btn-task-status btn-task-selesai'
                 : t.status === 'sedang_berjalan' ? 'btn-task-status btn-task-berjalan'
                 : 'btn-task-status btn-task-belum';

  return `
    <div class="task-item task-item-parent status-${t.status || 'belum_dimulai'}" id="task-${t.id}">
      <div class="task-item-main">
        <button class="${btnCls} ${checkLocked ? 'btn-task-locked' : ''}"
                title="${checkTitle}"
                onclick="toggleTaskStatus(${kegiatanId}, ${t.id}, '${t.status}', ${!needsLampiran || hasLampiran})">
          ${checkLocked ? '🔒' : btnLabel}
        </button>
        <div style="flex:1;min-width:0">
          <div class="task-name ${done ? 'done' : ''}">${esc(t.nama_task)}</div>
          <div style="display:flex;gap:12px;margin-top:3px;flex-wrap:wrap">
            ${t.pic_nama ? `<span class="task-pic">👤 ${esc(t.pic_nama)}</span>` : ''}
            ${t.target_date ? `<span class="task-meta">📅 ${fmtDate(t.target_date)}</span>` : ''}
            ${lampiran.length > 0 ? `<span class="task-meta" style="color:#16a34a">🔗 ${lampiran.length} bukti dukung</span>` : ''}
          </div>
          ${t.catatan ? `<div style="font-size:11.5px;color:#64748b;margin-top:3px">💬 ${esc(t.catatan)}</div>` : ''}
          ${needsLampiran ? _taskLampiranHtml(lampiran, kegiatanId, t.id, false) : ''}
          ${subtaskProgress}
        </div>
        <span class="task-status-badge ${statusClass[t.status] || 'task-belum'}">${taskStatusLabel(t.status)}</span>
        ${hasPermission('task.edit') ? `
          <div style="display:flex;gap:4px">
            <button class="btn-icon" onclick="showEditTask(${kegiatanId}, ${t.id})">✏️</button>
            <button class="btn-icon" onclick="confirmDeleteTask(${kegiatanId}, ${t.id}, '${esc(t.nama_task)}')">🗑️</button>
          </div>
        ` : ''}
      </div>
      ${subtaskHtml}
      <div class="subtask-footer">${addSubtaskBtn}</div>
    </div>
  `;
}

function subtaskItem(s, kegiatanId) {
  const done        = s.status === 'selesai';
  const statusClass = { selesai: 'task-selesai', sedang_berjalan: 'task-berjalan', belum_dimulai: 'task-belum' };
  const lampiran    = s.lampiran || [];
  const hasLampiran = lampiran.length > 0
  // Terkunci hanya saat akan menandai SELESAI tanpa lampiran
  const checkLocked = (!hasLampiran && s.status === 'sedang_berjalan')
  const sBtnLabel = s.status === 'selesai' ? '✓ Selesai'
                  : s.status === 'sedang_berjalan' ? '▶ Berjalan' : '▷ Mulai';
  const sBtnCls   = s.status === 'selesai' ? 'btn-task-status btn-task-status-sm btn-task-selesai'
                  : s.status === 'sedang_berjalan' ? 'btn-task-status btn-task-status-sm btn-task-berjalan'
                  : 'btn-task-status btn-task-status-sm btn-task-belum';
  return `
    <div class="subtask-item status-${s.status || 'belum_dimulai'}" id="task-${s.id}">
      <button class="${sBtnCls} ${checkLocked ? 'btn-task-locked' : ''}"
              title="${checkLocked ? 'Tambah link bukti dukung terlebih dahulu' : ''}"
              onclick="toggleTaskStatus(${kegiatanId}, ${s.id}, '${s.status}', ${hasLampiran})">
        ${checkLocked ? '🔒' : sBtnLabel}
      </button>
      <div style="flex:1;min-width:0">
        <div class="task-name ${done ? 'done' : ''}" style="font-size:13px">${esc(s.nama_task)}</div>
        <div style="display:flex;gap:10px;margin-top:2px;flex-wrap:wrap">
          ${s.pic_nama ? `<span class="task-pic" style="font-size:11px">👤 ${esc(s.pic_nama)}</span>` : ''}
          ${s.target_date ? `<span class="task-meta" style="font-size:11px">📅 ${fmtDate(s.target_date)}</span>` : ''}
          ${lampiran.length > 0 ? `<span class="task-meta" style="color:#16a34a;font-size:11px">🔗 ${lampiran.length} bukti dukung</span>` : ''}
        </div>
        ${_taskLampiranHtml(lampiran, kegiatanId, s.id, true)}
      </div>
      <span class="task-status-badge ${statusClass[s.status] || 'task-belum'}" style="font-size:10px">${taskStatusLabel(s.status)}</span>
      ${hasPermission('task.edit') ? `
        <div style="display:flex;gap:3px">
          <button class="btn-icon" style="padding:4px;font-size:12px" onclick="showEditSubtask(${kegiatanId}, ${s.id})">✏️</button>
          <button class="btn-icon" style="padding:4px;font-size:12px" onclick="confirmDeleteTask(${kegiatanId}, ${s.id}, '${esc(s.nama_task)}')">🗑️</button>
        </div>
      ` : ''}
    </div>
  `;
}

// Modal input link bukti dukung
function _showAddLinkModal(kegiatanId, taskId) {
  openModal('+ Tambah Link Bukti Dukung', `
    <div style="font-size:12px;color:#6b7280;margin-bottom:14px">
      Masukkan link dokumen bukti dukung (Google Drive, OneDrive, SharePoint, atau URL lainnya).
    </div>
    <div class="form-group" style="margin-bottom:10px">
      <label class="form-label" style="font-size:12px;font-weight:600">
        Nama / Keterangan <span class="required">*</span>
      </label>
      <input type="text" class="form-control" id="attNama"
             placeholder="Cth: Notulen Rapat, Foto Kegiatan, SK Penetapan…"
             style="font-size:13px">
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label" style="font-size:12px;font-weight:600">
        URL / Link <span class="required">*</span>
      </label>
      <input type="url" class="form-control" id="attUrl"
             placeholder="https://drive.google.com/… atau https://…"
             style="font-size:13px">
    </div>`, [
    { label: 'Batal',    cls: 'btn-secondary', action: closeModal },
    { label: '+ Simpan', cls: 'btn-primary',   action: async () => {
      const nama = document.getElementById('attNama')?.value.trim()
      const url  = document.getElementById('attUrl')?.value.trim()
      if (!nama) { showToast('Nama / keterangan wajib diisi.', 'error'); return }
      if (!url)  { showToast('URL wajib diisi.', 'error'); return }
      if (!/^https?:\/\/.+/.test(url)) { showToast('URL tidak valid. Harus diawali https://', 'error'); return }
      try {
        await apiPost(`kegiatan/${kegiatanId}/tasks/${taskId}/attachments`,
                      { nama_file: nama, file_url: url })
        showToast('Bukti dukung berhasil ditambahkan.', 'success')
        currentModalKegId = kegiatanId;
        closeModal()
        if (currentModalTipe === 'off_budget') showOffBudgetDetail(kegiatanId);
        else showKegiatanDetail(kegiatanId);
      } catch(e) { showToast('Gagal: ' + e.message, 'error') }
    }},
  ])
  // Auto-focus nama setelah modal terbuka
  setTimeout(() => document.getElementById('attNama')?.focus(), 100)
}

// Hapus lampiran
async function _deleteAttachment(kegiatanId, taskId, attId, btnEl) {
  if (!confirm('Hapus lampiran ini?')) return
  try {
    const res = await apiDelete(`kegiatan/${kegiatanId}/tasks/${taskId}/attachments/${attId}`)
    if (!res.status) throw new Error(res.message)
    showToast('Lampiran dihapus', 'success')
    // Hapus chip dari DOM langsung (tanpa full refresh)
    btnEl.closest('.task-lampiran-chip')?.remove()
    // Update counter chip di task
    currentModalKegId = kegiatanId;
    if (currentModalTipe === 'off_budget') showOffBudgetDetail(kegiatanId);
    else showKegiatanDetail(kegiatanId);
  } catch(e) {
    showToast('Gagal hapus: ' + e.message, 'error')
  }
}

async function toggleTaskStatus(kegiatanId, taskId, currentStatus, hasLampiran = true) {
  const newStatus = currentStatus === 'selesai' ? 'belum_dimulai'
                  : currentStatus === 'belum_dimulai' ? 'sedang_berjalan' : 'selesai';

  // Jika ingin tandai selesai tapi belum ada lampiran → blok (kecuali task punya subtask)
  if (newStatus === 'selesai' && !hasLampiran) {
    const subtaskList = document.getElementById(`subtask-list-${taskId}`)
    const hasSubtasks = subtaskList && subtaskList.querySelectorAll('.subtask-item').length > 0
    if (!hasSubtasks) {
      showToast('Tambahkan link bukti dukung terlebih dahulu sebelum menandai selesai.', 'error')
      return
    }
  }

  // Cek apakah task ini punya subtask (lihat DOM — subtask-list berisi .subtask-item)
  const subtaskList = document.getElementById(`subtask-list-${taskId}`)
  const hasSubtasks = subtaskList && subtaskList.querySelectorAll('.subtask-item').length > 0

  // Jika menandai parent task SELESAI padahal ada subtask → konfirmasi
  if (newStatus === 'selesai' && hasSubtasks) {
    const subItems  = subtaskList.querySelectorAll('.subtask-item')
    const allDone   = Array.from(subItems).every(el => el.querySelector('.task-check.done'))
    if (!allDone) {
      openModal('Tandai Task Selesai?', `
        <div style="font-size:13px;color:#374151;margin-bottom:12px">
          Task ini memiliki subtask yang belum selesai semua.<br>
          Pilih tindakan:
        </div>
        <div style="display:flex;flex-direction:column;gap:8px">
          <button class="btn btn-primary" onclick="
            closeModal();
            _doToggleTask(${kegiatanId}, ${taskId}, 'selesai', true)
          " style="text-align:left;padding:10px 14px">
            ✅ Tandai task + semua subtask sebagai <strong>Selesai</strong>
          </button>
          <button class="btn btn-secondary" onclick="
            closeModal();
            _doToggleTask(${kegiatanId}, ${taskId}, 'selesai', false)
          " style="text-align:left;padding:10px 14px">
            ⚠️ Tandai task selesai saja (subtask tidak diubah)
          </button>
          <button class="btn btn-outline" onclick="closeModal()">Batal</button>
        </div>`, [])
      return
    }
  }

  await _doToggleTask(kegiatanId, taskId, newStatus, false)
}

// Eksekusi perubahan status — jika withSubtasks=true, tandai semua subtask selesai dulu
async function _doToggleTask(kegiatanId, taskId, newStatus, withSubtasks) {
  if (withSubtasks && newStatus === 'selesai') {
    // Tandai semua subtask selesai terlebih dahulu (satu per satu via API)
    const subtaskList = document.getElementById(`subtask-list-${taskId}`)
    if (subtaskList) {
      const subIds = Array.from(subtaskList.querySelectorAll('.subtask-item'))
        .map(el => {
          const m = el.id.match(/task-(\d+)/)
          return m ? parseInt(m[1]) : null
        }).filter(Boolean)

      for (const subId of subIds) {
        await apiPut(`kegiatan/${kegiatanId}/tasks/${subId}`, { status: 'selesai' })
      }
    }
  }

  const res = await apiPut(`kegiatan/${kegiatanId}/tasks/${taskId}`, { status: newStatus })
  if (res && res.status) {
    showToast('Status task diperbarui', 'success')
    currentModalKegId = kegiatanId;
    // Refresh modal (detail) DAN card di grid secara bersamaan
    refreshCardInGrid(kegiatanId);
    if (currentModalTipe === 'off_budget') showOffBudgetDetail(kegiatanId);
    else showKegiatanDetail(kegiatanId);
  } else {
    showToast(res?.message || 'Gagal update status', 'error')
  }
}

function showAddSubtask(event, kegiatanId, parentTaskId) {
  event.stopPropagation();
  const users = _getUsers(kegiatanId);
  const userOptions = users.map(u => `<option value="${u.id}">${esc(u.nama)}</option>`).join('');
  openModal('Tambah Komponen-Komponen', `
    <div class="form-group"><label>Nama Komponen *</label><input type="text" id="f_nama_task" placeholder="Nama komponen"></div>
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
  if (!data.nama_task) { showToast('Nama komponen wajib diisi', 'error'); return; }

  const res = await apiPost(`kegiatan/${kegiatanId}/tasks`, data);
  if (res && res.status) {
    showToast('Komponen berhasil dibuat', 'success');
    // Set currentModalKegId secara eksplisit sebelum refresh agar tidak bergantung pada state sebelumnya
    currentModalKegId = kegiatanId;
    closeModal();
    if (currentModalTipe === 'off_budget') showOffBudgetDetail(kegiatanId);
    else showKegiatanDetail(kegiatanId);
  } else {
    showToast(res?.message || 'Gagal menyimpan komponen', 'error');
  }
}

function showEditSubtask(kegiatanId, taskId) {
  // Gunakan form edit task biasa (subtask = task dengan parent)
  showEditTask(kegiatanId, taskId);
}

function showAddTask(kegiatanId) {
  const users = _getUsers(kegiatanId);
  const userOptions = users.map(u => `<option value="${u.id}">${esc(u.nama)}</option>`).join('');
  openModal('Tambah Sub-Kegiatan', `
    <div class="form-group"><label>Nama Sub-Kegiatan *</label><input type="text" id="f_nama_task" placeholder="Nama sub-kegiatan"></div>
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

function showEditTask(kegiatanId, taskId) {
  const users = _getUsers(kegiatanId);
  const task  = _getTask(taskId);
  if (!task) { showToast('Data task tidak ditemukan, muat ulang halaman.', 'error'); return; }
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
    // Set currentModalKegId secara eksplisit sebelum refresh
    currentModalKegId = kegiatanId;
    closeModal();
    if (currentModalTipe === 'off_budget') showOffBudgetDetail(kegiatanId);
    else showKegiatanDetail(kegiatanId);
  } else {
    showToast(res?.message || 'Gagal menyimpan task', 'error');
  }
}

async function confirmDeleteTask(kegiatanId, taskId, nama) {
  if (!confirm(`Hapus task "${nama}"?`)) return;
  const res = await apiDelete(`kegiatan/${kegiatanId}/tasks/${taskId}`);
  if (res && res.status) {
    showToast('Task berhasil dihapus', 'success');
    currentModalKegId = kegiatanId;
    if (currentModalTipe === 'off_budget') showOffBudgetDetail(kegiatanId);
    else showKegiatanDetail(kegiatanId);
  } else {
    showToast(res?.message || 'Gagal menghapus', 'error');
  }
}

// ---------------------------------------------------------------
// Kegiatan Form (Add/Edit)
// ---------------------------------------------------------------
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
    <div class="form-group"><label>Tim *</label><select id="fk_team"><option value="">— Pilih Tim —</option>${teamOptions}</select></div>
    ${isEdit ? `
    <div class="form-group">
      <label>Pagu / Alokasi Anggaran</label>
      <input type="number" id="fk_pagu" value="${kegiatan?.pagu_anggaran || ''}"
        ${!hasPermission('rincian.manage') ? 'disabled style="background:var(--gray-100,#f3f4f6);cursor:not-allowed"' : ''}>
      <div style="font-size:11.5px;color:var(--gray-400,#9ca3af);margin-top:3px">
        Pagu diatur oleh Perencana Anggaran melalui menu Rincian Anggaran.
      </div>
    </div>` : `
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px 14px;font-size:12px;color:#166534;margin-top:4px">
      💡 <strong>Pagu anggaran</strong> tidak perlu diisi saat membuat kegiatan. Perencana Anggaran akan mengatur alokasi melalui menu <em>Rincian Anggaran</em>.
    </div>`}
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
    team_id:         document.getElementById('fk_team')?.value,
    status:          document.getElementById('fk_status')?.value,
  };
  if (tipe) data.tipe = tipe;

  // Pagu hanya dikirim saat edit dan diisi oleh direktur/perencana_anggaran
  const paguEl = document.getElementById('fk_pagu');
  if (id && paguEl && !paguEl.disabled && paguEl.value !== '') {
    data.pagu_anggaran = paguEl.value;
  }

  if (!data.nama_kegiatan || !data.tanggal_mulai || !data.tanggal_selesai || !data.team_id) {
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
// Realisasi Anggaran (On-Budget)
// ---------------------------------------------------------------
// ---------------------------------------------------------------
// Realisasi On-Budget — Form Input dengan Komponen Splits
// ---------------------------------------------------------------

/**
 * Buka form Input Realisasi Anggaran.
 * Jika kegiatan memiliki rincian anggaran, tampilkan selector komponen
 * dinamis yang memungkinkan split otomatis antar komponen jika volume habis.
 */
async function showAddRealisasi(kegiatanId) {
  // Muat sisa komponen sebelum buka modal
  const sisaRes = await apiGet(`kegiatan/${kegiatanId}/rincian/sisa`);
  // Tampilkan semua komponen yang masih punya sisa anggaran > 0
  const sisaRows = (sisaRes?.data?.rows || []).filter(r => parseFloat(r.nominal_sisa) > 0);
  // Simpan di registry agar tidak perlu JSON.stringify di inline onclick
  window._sisaRowsCache = sisaRows;

  const hasKomponen = sisaRows.length > 0;

  openModal('Input Realisasi Anggaran', `
    <div class="form-row form-row-2">
      <div class="form-group">
        <label>Tanggal *</label>
        <input type="date" id="fr_tanggal" value="${todayWIB()}">
      </div>
      <div class="form-group">
        <label>Nominal Total (Rp) *</label>
        <input type="number" id="fr_nominal" placeholder="0" oninput="_realisasiSyncNominal()">
      </div>
    </div>
    <div class="form-group">
      <label>Uraian *</label>
      <input type="text" id="fr_uraian" placeholder="Keterangan penggunaan anggaran">
    </div>
    <div class="form-group">
      <label>Bukti Transaksi (URL)</label>
      <input type="text" id="fr_bukti" placeholder="https://...">
    </div>

    ${hasKomponen ? `
    <div class="realisasi-komponen-wrap">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <label style="margin:0;font-weight:600;font-size:13px">Komponen Anggaran</label>
        <button type="button" class="btn btn-outline btn-sm"
          onclick="_tambahSplitBaris(window._sisaRowsCache)">
          + Tambah Komponen
        </button>
      </div>
      <div id="splitBarisList"></div>
      <div class="realisasi-komponen-note" id="splitNote"></div>
    </div>
    ` : `
    <div style="font-size:12px;color:#94a3b8;margin-top:4px">
      ℹ️ Belum ada komponen anggaran dengan sisa. Realisasi dicatat tanpa komponen.
    </div>
    `}
  `, [
    { label: 'Batal',  cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary', action: `saveRealisasi(${kegiatanId}, null)` },
  ]);

  window._realisasiSisaRows = sisaRows;

  if (hasKomponen) {
    _tambahSplitBaris(sisaRows);
  }
}

/**
 * Tambah satu baris split komponen.
 * Hanya ada dua field: pilih komponen + nominal yang dikeluarkan.
 * Label dropdown menampilkan sisa anggaran komponen (Rp).
 */
function _tambahSplitBaris(sisaRows) {
  const container = document.getElementById('splitBarisList');
  if (!container) return;

  const idx = container.children.length;
  const opts = sisaRows.map(r => {
    const sisaNom = parseFloat(r.nominal_sisa);
    return `<option value="${r.id}"
      data-sisa-nom="${r.nominal_sisa}"
      data-subtotal="${r.subtotal}"
      data-komponen="${esc(r.komponen)}">
      ${esc(r.komponen)} — sisa ${rupiah(sisaNom)}
    </option>`;
  }).join('');

  const rowHtml = `
    <div class="split-baris" id="split-baris-${idx}" style="display:grid;grid-template-columns:1fr 160px 32px;gap:8px;align-items:end;margin-bottom:8px">
      <div class="form-group" style="margin:0">
        <label style="font-size:11px;color:#64748b">Komponen *</label>
        <select id="split_rid_${idx}" onchange="_onSplitKomponenChange(${idx})">
          <option value="">— pilih komponen —</option>
          ${opts}
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label style="font-size:11px;color:#64748b">Nominal (Rp) *
          <span id="split_sisa_label_${idx}" style="font-weight:400;color:#94a3b8"></span>
        </label>
        <input type="number" id="split_nom_${idx}" min="0" step="any" placeholder="0"
          oninput="_realisasiUpdateNote()">
      </div>
      <button type="button" class="btn-icon" style="margin-bottom:4px"
        onclick="document.getElementById('split-baris-${idx}').remove(); _realisasiUpdateNote()">✕</button>
    </div>
  `;
  container.insertAdjacentHTML('beforeend', rowHtml);
  _realisasiUpdateNote();
}

/** Saat komponen dipilih: perbarui label sisa & set max pada input nominal */
function _onSplitKomponenChange(idx) {
  const sel = document.getElementById(`split_rid_${idx}`);
  if (!sel || !sel.value) return;
  const opt     = sel.options[sel.selectedIndex];
  const sisaNom = parseFloat(opt.dataset.sisaNom) || 0;

  // Hitung sisa dikurangi nominal baris lain yang memilih komponen yang sama
  let usedByOthers = 0;
  document.querySelectorAll('[id^="split_rid_"]').forEach(s => {
    if (s.id !== sel.id && s.value === sel.value) {
      const oi = s.id.replace('split_rid_', '');
      usedByOthers += parseFloat(document.getElementById(`split_nom_${oi}`)?.value || 0);
    }
  });
  const remainNom = Math.max(0, sisaNom - usedByOthers);

  // Update label & batas atas
  const labelEl = document.getElementById(`split_sisa_label_${idx}`);
  if (labelEl) labelEl.textContent = `(maks ${rupiah(remainNom)})`;

  const nomEl = document.getElementById(`split_nom_${idx}`);
  if (nomEl) {
    nomEl.max = remainNom;
    // Isi otomatis dengan sisa jika kosong
    if (!nomEl.value) nomEl.value = remainNom;
  }

  _realisasiUpdateNote();
}

/** Sinkronisasi: saat nominal total di atas berubah, update note saja */
function _realisasiSyncNominal() {
  _realisasiUpdateNote();
}

/** Tampilkan status total split vs nominal keseluruhan */
function _realisasiUpdateNote() {
  const noteEl = document.getElementById('splitNote');
  if (!noteEl) return;

  let totalSplit = 0;
  document.querySelectorAll('[id^="split_nom_"]').forEach(el => {
    totalSplit += parseFloat(el.value) || 0;
  });

  // Validasi per-baris: nominal tidak boleh melebihi sisa komponen
  let overLimit = false;
  document.querySelectorAll('[id^="split_rid_"]').forEach(sel => {
    if (!sel.value) return;
    const idxStr = sel.id.replace('split_rid_', '');
    const opt     = sel.options[sel.selectedIndex];
    const sisaNom = parseFloat(opt?.dataset?.sisaNom) || 0;
    const nom     = parseFloat(document.getElementById(`split_nom_${idxStr}`)?.value) || 0;
    if (nom > sisaNom + 1) overLimit = true;
  });

  const totalNom = parseFloat(document.getElementById('fr_nominal')?.value) || 0;

  if (totalSplit === 0 && totalNom === 0) { noteEl.textContent = ''; return; }

  // Auto-isi nominal total dari sum splits jika belum diisi
  if (totalNom === 0 && totalSplit > 0) {
    const nomEl = document.getElementById('fr_nominal');
    if (nomEl) nomEl.value = totalSplit;
  }

  if (overLimit) {
    noteEl.innerHTML = `<span style="color:#dc2626">⚠ Nominal melebihi sisa anggaran komponen</span>`;
  } else if (totalSplit > 0 && Math.abs(totalSplit - (totalNom || totalSplit)) > 1) {
    noteEl.innerHTML = `<span style="color:#dc2626">⚠ Total split <strong>${rupiah(totalSplit)}</strong> ≠ nominal <strong>${rupiah(totalNom)}</strong></span>`;
  } else if (totalSplit > 0) {
    noteEl.innerHTML = `<span style="color:#16a34a">✓ Total split: <strong>${rupiah(totalSplit)}</strong></span>`;
  } else {
    noteEl.textContent = '';
  }
}

async function saveRealisasi(kegiatanId, id) {
  const tanggal = document.getElementById('fr_tanggal')?.value;
  const uraian  = document.getElementById('fr_uraian')?.value?.trim();
  const nominal = parseFloat(document.getElementById('fr_nominal')?.value) || 0;
  const bukti   = document.getElementById('fr_bukti')?.value?.trim() || null;

  if (!tanggal || !uraian || !nominal) { showToast('Lengkapi field wajib', 'error'); return; }

  // Kumpulkan splits komponen (jika ada)
  const barisList = document.getElementById('splitBarisList');
  let komponen_splits = null;

  if (barisList && barisList.children.length > 0) {
    const splits = [];
    let totalSplit = 0;
    let valid = true;

    barisList.querySelectorAll('.split-baris').forEach(baris => {
      if (!valid) return;
      const idxMatch = baris.id.match(/split-baris-(\d+)/);
      if (!idxMatch) return;
      const i   = idxMatch[1];
      const rid = parseInt(document.getElementById(`split_rid_${i}`)?.value) || 0;
      const nom = parseFloat(document.getElementById(`split_nom_${i}`)?.value) || 0;

      if (!rid) { showToast('Pilih komponen untuk setiap baris split', 'error'); valid = false; return; }
      if (nom <= 0) { showToast('Nominal setiap split harus lebih dari 0', 'error'); valid = false; return; }

      // Validasi nominal tidak melebihi sisa komponen
      const sisaRows = window._realisasiSisaRows || [];
      const rincian  = sisaRows.find(r => parseInt(r.id) === rid);
      if (rincian && nom > parseFloat(rincian.nominal_sisa) + 1) {
        showToast(`Nominal melebihi sisa anggaran komponen "${rincian.komponen}" (${rupiah(rincian.nominal_sisa)})`, 'error');
        valid = false; return;
      }

      splits.push({ rincian_id: rid, nominal: nom });
      totalSplit += nom;
    });

    if (!valid) return;

    if (splits.length > 0) {
      if (Math.abs(totalSplit - nominal) > 1) {
        showToast(`Total split (${rupiah(totalSplit)}) ≠ nominal (${rupiah(nominal)}). Harap sesuaikan.`, 'error');
        return;
      }
      komponen_splits = splits;
    }
  }

  const data = {
    tanggal,
    uraian,
    nominal,
    bukti_transaksi: bukti,
    ...(komponen_splits ? { komponen_splits } : {}),
  };

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

  // Badge komponen anggaran (on-budget split)
  const komponenBadge = r.rincian_anggaran_id && r.komponen_nama
    ? `<div style="margin-top:3px">
         <span style="background:#eff6ff;color:#1e40af;padding:1px 7px;border-radius:8px;font-size:10px;font-weight:600">
           📋 ${esc(r.komponen_nama)}
         </span>
       </div>`
    : '';

  return `
    <tr style="${isMirror ? 'background:#faf5ff' : ''}">
      <td>${fmtDate(r.tanggal)}</td>
      <td>
        ${esc(r.uraian)}
        ${mirrorBadge}
        ${komponenBadge}
      </td>
      <td style="font-weight:600">${rupiah(r.nominal)}</td>
      <td>${esc(r.created_by_nama || '—')}</td>
      <td>
        ${!isMirror && hasPermission('anggaran.delete') ? `
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

// ---------------------------------------------------------------
// Dokumen (link OneDrive)
// ---------------------------------------------------------------
async function loadDokumen(kegiatanId) {
  const res  = await apiGet(`kegiatan/${kegiatanId}/dokumen`);
  const docs = res?.data || [];
  const wrap = document.getElementById(`dokumenTable-${kegiatanId}`);
  if (!wrap) return;

  const canEdit = hasPermission('kegiatan.edit');
  wrap.innerHTML = `
    ${canEdit ? `
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-bottom:14px">
      <div style="font-size:13px;font-weight:600;color:#1e293b;margin-bottom:10px">+ Tambah Dokumen</div>
      <div class="form-row form-row-2" style="margin-bottom:8px">
        <div class="form-group" style="margin:0">
          <label style="font-size:11px;font-weight:600;color:#64748b">Nama Dokumen *</label>
          <input type="text" id="dok_nama_${kegiatanId}" placeholder="Nama dokumen">
        </div>
        <div class="form-group" style="margin:0">
          <label style="font-size:11px;font-weight:600;color:#64748b">Link OneDrive *</label>
          <input type="url" id="dok_link_${kegiatanId}"
            placeholder="https://1drv.ms/... atau https://...sharepoint.com/...">
        </div>
      </div>
      <button class="btn btn-primary btn-sm" onclick="saveDokumen(${kegiatanId})">Simpan</button>
    </div>
    ` : ''}

    <table>
      <thead>
        <tr>
          <th>Nama Dokumen</th>
          <th>Link</th>
          <th>Ditambahkan Oleh</th>
          <th>Waktu</th>
          ${canEdit ? '<th style="width:40px"></th>' : ''}
        </tr>
      </thead>
      <tbody>
        ${docs.length === 0
          ? `<tr><td colspan="${canEdit ? 5 : 4}" style="text-align:center;padding:20px;color:#94a3b8">Belum ada dokumen</td></tr>`
          : docs.map(d => `
            <tr>
              <td style="font-weight:600">${esc(d.nama_file)}</td>
              <td>
                <a href="${d.file_url}" target="_blank" rel="noopener"
                  style="color:#1e40af;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:4px">
                  🔗 Link
                </a>
              </td>
              <td style="color:#64748b;font-size:12px">${esc(d.uploaded_by_nama || '—')}</td>
              <td style="color:#64748b;font-size:12px">${fmtDate(d.created_at)}</td>
              ${canEdit ? `<td><button class="btn-icon" onclick="deleteDokumen(${kegiatanId}, ${d.id})">🗑️</button></td>` : ''}
            </tr>
          `).join('')}
      </tbody>
    </table>
  `;
}

async function saveDokumen(kegiatanId) {
  const nama = document.getElementById(`dok_nama_${kegiatanId}`)?.value?.trim();
  const link = document.getElementById(`dok_link_${kegiatanId}`)?.value?.trim();

  if (!nama) { showToast('Nama dokumen wajib diisi', 'error'); return; }
  if (!link) { showToast('Link OneDrive wajib diisi', 'error'); return; }

  const res = await apiPost(`kegiatan/${kegiatanId}/dokumen`, { nama_file: nama, file_url: link });
  if (res && res.status) {
    showToast('Dokumen berhasil ditambahkan', 'success');
    loadDokumen(kegiatanId);
  } else {
    showToast(res?.message || 'Gagal menyimpan', 'error');
  }
}

async function deleteDokumen(kegiatanId, docId) {
  if (!confirm('Hapus dokumen ini?')) return;
  const res = await apiDelete(`kegiatan/${kegiatanId}/dokumen/${docId}`);
  if (res && res.status) { showToast('Dokumen dihapus', 'success'); loadDokumen(kegiatanId); }
  else showToast(res?.message || 'Gagal', 'error');
}

// ---------------------------------------------------------------
// Tab Info — Tabel Ringkasan Task & Subtask
// ---------------------------------------------------------------
function _kegiatanTaskTableRows(tasks) {
  if (!tasks || tasks.length === 0) {
    return `<tr><td colspan="8" style="text-align:center;padding:20px;color:var(--gray-400)">Belum ada task</td></tr>`;
  }

  let rows = '';
  let no   = 1;

  tasks.forEach(t => {
    rows += _taskInfoRow(t, no++, false);
    (t.subtasks || []).forEach(s => {
      rows += _taskInfoRow(s, null, true);
    });
  });

  return rows;
}

function _taskInfoRow(t, no, isSub) {
  const statusColors = {
    selesai:         { bg: '#f0fdf4', color: '#16a34a', label: 'Selesai' },
    sedang_berjalan: { bg: '#eff6ff', color: '#1e40af', label: 'Berjalan' },
    belum_dimulai:   { bg: '#f8fafc', color: '#94a3b8', label: 'Belum' },
  };
  const sc = statusColors[t.status] || statusColors.belum_dimulai;

  // Tepat waktu: selesai sebelum atau sama dengan deadline
  let tepatWaktu = '—';
  if (t.status === 'selesai' && t.target_date && t.completed_date) {
    tepatWaktu = t.completed_date <= t.target_date
      ? `<span style="color:#16a34a;font-weight:700">✓ Ya</span>`
      : `<span style="color:#dc2626;font-weight:700">✗ Terlambat</span>`;
  } else if (t.status !== 'selesai' && t.target_date && t.target_date < new Date().toISOString().slice(0,10)) {
    tepatWaktu = `<span style="color:#dc2626;font-size:11px">Melewati deadline</span>`;
  }

  const indent = isSub
    ? `<td style="color:var(--gray-400);font-size:11px;text-align:center">↳</td>`
    : `<td style="color:var(--gray-500);font-size:12px;text-align:center">${no}</td>`;

  const nameStyle = isSub
    ? 'padding-left:12px;font-size:12px;color:var(--gray-600)'
    : 'font-weight:600;font-size:12.5px';

  return `
    <tr style="border-bottom:1px solid var(--gray-100)${isSub ? ';background:#fafafa' : ''}">
      ${indent}
      <td style="${nameStyle}">${isSub ? '↳ ' : ''}${esc(t.nama_task)}</td>
      <td style="font-size:11.5px;color:var(--gray-600)">${esc(t.pic_nama || '—')}</td>
      <td style="text-align:center">
        <span style="background:${sc.bg};color:${sc.color};border-radius:20px;padding:2px 8px;font-size:11px;font-weight:600">${sc.label}</span>
      </td>
      <td style="text-align:center;font-size:11.5px;color:var(--gray-600)">${t.started_at ? fmtDate(t.started_at.slice(0,10)) : '—'}</td>
      <td style="text-align:center;font-size:11.5px;color:var(--gray-600)">${t.completed_date ? fmtDate(t.completed_date) : '—'}</td>
      <td style="text-align:center;font-size:11.5px${!t.target_date ? '' : t.target_date < new Date().toISOString().slice(0,10) && t.status !== 'selesai' ? ';color:#dc2626;font-weight:600' : ''}">${t.target_date ? fmtDate(t.target_date) : '—'}</td>
      <td style="text-align:center;font-size:11.5px">${tepatWaktu}</td>
    </tr>`;
}

// ---------------------------------------------------------------
// Download Laporan Kegiatan (print window → PDF via browser)
// ---------------------------------------------------------------
async function downloadLaporanKegiatan(kegiatanId) {
  const [res, rincianRes] = await Promise.all([
    apiGet(`kegiatan/${kegiatanId}`),
    apiGet(`kegiatan/${kegiatanId}/rincian/sisa`),
  ]);
  if (!res || !res.status) { showError('Gagal memuat data laporan'); return; }
  const k           = res.data;
  const rincianRows = rincianRes?.data?.rows || [];

  const now    = new Date().toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' });
  const appName = document.querySelector('.sidebar-title')?.textContent || 'MKA System';

  // Build rincian rows for print
  let rincianPrintRows = '';
  if (rincianRows.length) {
    const totalSubtotal = rincianRows.reduce((s, r) => s + parseFloat(r.subtotal || 0), 0);
    const totalTerpakai = rincianRows.reduce((s, r) => s + parseFloat(r.nominal_terpakai || 0), 0);
    rincianRows.forEach((r, i) => {
      const subtotal = parseFloat(r.subtotal || 0);
      const terpakai = parseFloat(r.nominal_terpakai || 0);
      // Actual percentage: if alokasi fully consumed, show 100%; cap display at 100 but flag over-budget
      const rawPct   = subtotal > 0 ? Math.round(terpakai / subtotal * 100) : 0;
      const dispPct  = Math.min(100, rawPct);
      const pctColor = rawPct > 100 ? '#dc2626'      // over-budget: merah
                     : rawPct === 100 ? '#16a34a'    // terpenuhi penuh: hijau
                     : rawPct >= 50   ? '#d97706'    // sebagian: kuning
                     :                  '#94a3b8';   // rendah: abu
      rincianPrintRows += `
        <tr>
          <td style="text-align:center">${i + 1}</td>
          <td><strong>${esc(r.komponen)}</strong>${r.uraian ? `<br><span style="color:#64748b;font-weight:400">${esc(r.uraian)}</span>` : ''}</td>
          <td style="text-align:right">${formatAngka(r.qty)}</td>
          <td>${esc(r.satuan_qty || '—')}</td>
          <td style="text-align:right">${formatAngka(r.volume)}</td>
          <td>${esc(r.satuan_vol || '—')}</td>
          <td style="text-align:right">${rupiah(r.harga_satuan)}</td>
          <td style="text-align:right;font-weight:700;color:#1e40af">${rupiah(subtotal)}</td>
          <td style="text-align:right;color:${pctColor}">${rupiah(terpakai)}<br><span style="font-size:10px;color:#999">${dispPct}%${rawPct > 100 ? ' ⚠' : ''}</span></td>
        </tr>`;
    });
    rincianPrintRows += `
        <tr style="background:#f0f7ff;font-weight:700">
          <td colspan="7" style="text-align:right">Total</td>
          <td style="text-align:right;color:#1e40af">${rupiah(totalSubtotal)}</td>
          <td style="text-align:right;color:#16a34a">${rupiah(totalTerpakai)}</td>
        </tr>`;
  } else {
    rincianPrintRows = `<tr><td colspan="9" style="text-align:center;color:#999;padding:12px">Belum ada rincian anggaran</td></tr>`;
  }

  // Build task rows for print
  let taskRows = '';
  let no = 1;
  (k.tasks || []).forEach(t => {
    const subs = t.subtasks || [];
    const tepatWaktu = t.status === 'selesai' && t.target_date && t.completed_date
      ? (t.completed_date <= t.target_date ? 'Ya' : 'Terlambat') : '—';

    taskRows += `
      <tr>
        <td style="text-align:center">${no++}</td>
        <td><strong>${esc(t.nama_task)}</strong></td>
        <td style="text-align:center">${esc(t.pic_nama || '—')}</td>
        <td style="text-align:center">${taskStatusLabel(t.status)}</td>
        <td style="text-align:center">${t.started_at ? fmtDate(t.started_at.slice(0,10)) : '—'}</td>
        <td style="text-align:center">${t.completed_date ? fmtDate(t.completed_date) : '—'}</td>
        <td style="text-align:center">${t.target_date ? fmtDate(t.target_date) : '—'}</td>
        <td style="text-align:center">${tepatWaktu}</td>
      </tr>`;

    subs.forEach(s => {
      const st = s.status === 'selesai' && s.target_date && s.completed_date
        ? (s.completed_date <= s.target_date ? 'Ya' : 'Terlambat') : '—';
      taskRows += `
        <tr style="background:#f9fafb">
          <td style="text-align:center;color:#999">↳</td>
          <td style="padding-left:20px;color:#555">↳ ${esc(s.nama_task)}</td>
          <td style="text-align:center">${esc(s.pic_nama || '—')}</td>
          <td style="text-align:center">${taskStatusLabel(s.status)}</td>
          <td style="text-align:center">${s.started_at ? fmtDate(s.started_at.slice(0,10)) : '—'}</td>
          <td style="text-align:center">${s.completed_date ? fmtDate(s.completed_date) : '—'}</td>
          <td style="text-align:center">${s.target_date ? fmtDate(s.target_date) : '—'}</td>
          <td style="text-align:center">${st}</td>
        </tr>`;
    });
  });

  if (!taskRows) taskRows = `<tr><td colspan="8" style="text-align:center;color:#999;padding:16px">Belum ada task</td></tr>`;

  const anggotaList = (k.anggota || []).map(a => esc(a.nama)).join(', ') || '—';
  const serapan = k.pagu_anggaran > 0 ? ((k.total_realisasi / k.pagu_anggaran) * 100).toFixed(2) : '0.00';

  const html = `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Kegiatan — ${esc(k.nama_kegiatan)}</title>
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:"Segoe UI",system-ui,sans-serif; font-size:12px; color:#1f2328; background:#fff; padding:32px; }
  h1  { font-size:18px; font-weight:700; color:#1e3a8a; margin-bottom:4px; }
  h2  { font-size:13px; font-weight:700; color:#1e3a8a; margin:20px 0 8px; border-bottom:2px solid #1e3a8a; padding-bottom:4px; }
  .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px 24px; margin:12px 0; }
  .meta-item label { font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; display:block; }
  .meta-item span  { font-weight:600; }
  table { width:100%; border-collapse:collapse; font-size:11px; margin-bottom:8px; }
  th    { background:#1e3a8a; color:#fff; padding:7px 8px; text-align:left; font-weight:600; }
  td    { padding:6px 8px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
  tr:nth-child(even) td { background:#f8fafc; }
  .badge { display:inline-block; padding:1px 7px; border-radius:12px; font-size:10px; font-weight:700; }
  .badge-selesai  { background:#dcfce7; color:#16a34a; }
  .badge-berjalan { background:#dbeafe; color:#1e40af; }
  .badge-belum    { background:#f1f5f9; color:#64748b; }
  .footer { margin-top:32px; text-align:right; color:#94a3b8; font-size:10px; }
  .header-row { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #1e3a8a; padding-bottom:12px; margin-bottom:16px; }
  .org { font-size:10px; color:#64748b; margin-top:4px; }
  @media print {
    body { padding:16px; }
    @page { margin:16mm; size:A4 landscape; }
  }
</style>
</head>
<body>

<div class="header-row">
  <div>
    <h1>Laporan Kegiatan</h1>
    <div class="org">${appName} — Dicetak: ${now}</div>
  </div>
  <div style="text-align:right;font-size:11px;color:#64748b">
    <div>Progress: <strong>${k.progress}%</strong></div>
    <div>${k.tasks_selesai}/${k.total_tasks} task selesai</div>
  </div>
</div>

<h2>Informasi Kegiatan</h2>
<div class="meta-grid">
  <div class="meta-item"><label>Nama Kegiatan</label><span>${esc(k.nama_kegiatan)}</span></div>
  <div class="meta-item"><label>Tim</label><span>${esc(k.nama_tim || '—')}</span></div>
  <div class="meta-item"><label>Ketua Tim</label><span>${esc(k.ketua_tim_nama || '—')}</span></div>
  <div class="meta-item"><label>Status</label><span>${statusLabel(k.status)}</span></div>
  <div class="meta-item"><label>Tanggal Mulai</label><span>${fmtDate(k.tanggal_mulai)}</span></div>
  <div class="meta-item"><label>Deadline</label><span>${fmtDate(k.tanggal_selesai)}</span></div>
  <div class="meta-item"><label>Pagu Anggaran</label><span>${rupiah(k.pagu_anggaran)}</span></div>
  <div class="meta-item"><label>Total Realisasi</label><span>${rupiah(k.total_realisasi)}</span></div>
  <div class="meta-item"><label>Sisa Anggaran</label><span>${rupiah(k.sisa_anggaran)}</span></div>
  <div class="meta-item"><label>Serapan Anggaran</label><span>${serapan}%</span></div>
  <div class="meta-item" style="grid-column:1/-1"><label>Anggota Tim</label><span>${anggotaList}</span></div>
  ${k.deskripsi ? `<div class="meta-item" style="grid-column:1/-1"><label>Deskripsi</label><span style="font-weight:400">${esc(k.deskripsi)}</span></div>` : ''}
</div>

<h2>Rincian Anggaran (${rincianRows.length} Komponen)</h2>
<table>
  <thead>
    <tr>
      <th style="width:28px">#</th>
      <th>Komponen / Uraian</th>
      <th style="width:52px;text-align:right">Qty</th>
      <th style="width:52px">Satuan</th>
      <th style="width:52px;text-align:right">Volume</th>
      <th style="width:52px">Satuan</th>
      <th style="width:110px;text-align:right">Harga Satuan</th>
      <th style="width:110px;text-align:right">Subtotal</th>
      <th style="width:100px;text-align:right">Realisasi</th>
    </tr>
  </thead>
  <tbody>${rincianPrintRows}</tbody>
</table>

<h2>Ringkasan Task &amp; Subtask</h2>
<table>
  <thead>
    <tr>
      <th style="width:30px">#</th>
      <th>Task / Subtask</th>
      <th style="width:110px">PIC</th>
      <th style="width:90px;text-align:center">Status</th>
      <th style="width:100px;text-align:center">Tgl Berjalan</th>
      <th style="width:100px;text-align:center">Tgl Selesai</th>
      <th style="width:100px;text-align:center">Deadline</th>
      <th style="width:80px;text-align:center">Tepat Waktu</th>
    </tr>
  </thead>
  <tbody>
    ${taskRows}
  </tbody>
</table>

<div class="footer">Laporan ini digenerate otomatis dari ${appName} pada ${now}</div>

</body>
</html>`;

  const win = window.open('', '_blank', 'width=1100,height=750');
  win.document.write(html);
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 600);
}
