/* ============================================================
   ro.js — Rumpun Output (RO) — pengelompokan on-budget kegiatan
   Dimuat SEBELUM kegiatan.js
   ============================================================ */
'use strict';

// State untuk assign modal — menghindari passing array besar via onclick attribute
let _roAssignState = { roId: null, allIds: [] };

// ---------------------------------------------------------------
// Patch RO header progress bars secara real-time tanpa re-render
// Dipanggil dari refreshCardInGrid() setiap kali task/subtask berubah
// ---------------------------------------------------------------
async function refreshRoStats(roId) {
  const sectionEl = document.getElementById(`ro-section-${roId}`);
  if (!sectionEl) return; // RO section tidak ada di DOM (halaman lain)

  const res = await apiGet(`ro/${roId}`);
  if (!res || !res.status) return;
  const ro = res.data;

  const progressPct   = Math.min(parseFloat(ro.progress             ?? 0), 100);
  const realisasiPct  = Math.min(parseFloat(ro.persentase_realisasi ?? 0), 100);
  const progressColor  = progressPct  > 80 ? 'hijau' : progressPct  >= 50 ? 'kuning' : 'merah';
  const realisasiColor = realisasiPct > 80 ? 'hijau' : realisasiPct >= 50 ? 'kuning' : 'merah';

  // Ambil semua ro-stat-row di dalam section ini
  const statRows = sectionEl.querySelectorAll('.ro-stat-row');

  // Row 0 = Progress, Row 1 = Serapan, Row 2 = Anggaran (teks)
  if (statRows[0]) {
    const fill  = statRows[0].querySelector('.progress-fill');
    const label = statRows[0].querySelector('.ro-stat-value');
    if (fill) {
      fill.style.width = `${progressPct}%`;
      fill.className   = `progress-fill progress-${progressColor}`;
    }
    if (label) label.textContent = `${parseFloat(ro.progress ?? 0).toFixed(1)}%`;
  }

  if (statRows[1]) {
    const fill  = statRows[1].querySelector('.progress-fill');
    const label = statRows[1].querySelector('.ro-stat-value');
    if (fill) {
      fill.style.width = `${realisasiPct}%`;
      fill.className   = `progress-fill progress-${realisasiColor}`;
    }
    if (label) label.textContent = `${parseFloat(ro.persentase_realisasi ?? 0).toFixed(1)}%`;
  }
}

// ---------------------------------------------------------------
// Render satu seksi RO (header + progress bars + grid kegiatan)
// ---------------------------------------------------------------
function renderRoSection(ro, kegiatanCards) {
  const canEdit   = hasPermission('ro.edit');
  const canDelete = hasPermission('ro.delete');
  const canAssign = hasPermission('ro.assign');

  const editBtn = canEdit
    ? `<button class="ro-action-btn" title="Edit RO"
         onclick="showRoForm(${JSON.stringify(ro).replace(/"/g,'&quot;')});event.stopPropagation()">
         <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 1.83H5v-.75l9.06-9.06.75.75-9.89 9.06zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
         Edit
       </button>` : '';

  const delBtn = canDelete
    ? `<button class="ro-action-btn ro-action-danger" title="Hapus RO"
         onclick="confirmDeleteRo(${ro.id},'${esc(ro.nama_ro)}');event.stopPropagation()">
         <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zm3-9h2v7H9v-7zm4 0h2v7h-2v-7zM15.5 4l-1-1h-5l-1 1H5v2h14V4z"/></svg>
         Hapus
       </button>` : '';

  const assignBtn = canAssign
    ? `<button class="ro-action-btn" title="Assign Kegiatan"
         onclick="showAssignRoModal(${ro.id});event.stopPropagation()">
         <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
         Assign
       </button>` : '';

  const progressPct   = parseFloat(ro.progress              ?? 0);
  const realisasiPct  = parseFloat(ro.persentase_realisasi   ?? 0);
  const progressColor  = progressPct  > 80 ? 'hijau' : progressPct  >= 50 ? 'kuning' : 'merah';
  const realisasiColor = realisasiPct > 80 ? 'hijau' : realisasiPct >= 50 ? 'kuning' : 'merah';

  return `
    <div class="ro-section" id="ro-section-${ro.id}">
      <div class="ro-header">
        <div class="ro-header-top">
          <div class="ro-title">
            ${ro.kode_ro ? `<span class="ro-kode">${esc(ro.kode_ro)}</span>` : ''}
            <span class="ro-nama">${esc(ro.nama_ro)}</span>
            <span class="ro-badge">${ro.jumlah_kegiatan} kegiatan</span>
            ${ro.tahun ? `<span class="ro-badge ro-badge-year">${esc(String(ro.tahun))}</span>` : ''}
          </div>
          <div class="ro-actions">
            ${assignBtn}${editBtn}${delBtn}
          </div>
        </div>
        <div class="ro-stats">
          <div class="ro-stat-row">
            <span class="ro-stat-label">Progress</span>
            <div class="ro-progress-bar-wrap">
              <div class="progress-bar ro-progress-bar">
                <div class="progress-fill progress-${progressColor}" style="width:${Math.min(progressPct,100)}%"></div>
              </div>
            </div>
            <span class="ro-stat-value">${progressPct.toFixed(1)}%</span>
          </div>
          <div class="ro-stat-row">
            <span class="ro-stat-label">Serapan</span>
            <div class="ro-progress-bar-wrap">
              <div class="progress-bar ro-progress-bar">
                <div class="progress-fill progress-${realisasiColor}" style="width:${Math.min(realisasiPct,100)}%"></div>
              </div>
            </div>
            <span class="ro-stat-value">${realisasiPct.toFixed(1)}%</span>
          </div>
          <div class="ro-stat-row ro-stat-anggaran">
            <span class="ro-stat-label" style="min-width:64px">Total Pagu</span>
            <span class="ro-stat-value" style="font-size:13px;min-width:unset">${rupiahSingkat(ro.total_pagu)}</span>
            <span style="opacity:.6;font-size:11px;margin:0 6px">/</span>
            <span class="ro-stat-label" style="min-width:unset">Realisasi</span>
            <span class="ro-stat-value" style="font-size:13px;min-width:unset;color:#86efac">${rupiahSingkat(ro.total_realisasi)}</span>
          </div>
        </div>
      </div>
      <div class="ro-body">
        <div class="kegiatan-grid ro-kegiatan-grid">
          ${kegiatanCards || `<div class="ro-empty">Belum ada kegiatan dalam RO ini. Klik <strong>Assign</strong> untuk menambahkan.</div>`}
        </div>
      </div>
    </div>
  `;
}

// ---------------------------------------------------------------
// Modal Form — Tambah / Edit RO
// ---------------------------------------------------------------
function showRoForm(ro = null) {
  const isEdit = ro && ro.id;
  const title  = isEdit ? 'Edit RO' : 'Tambah RO';

  const body = `
    <div class="form-group">
      <label>Nama RO <span style="color:#dc2626">*</span></label>
      <input type="text" id="ro_nama" class="form-control"
        value="${isEdit ? esc(ro.nama_ro) : ''}"
        placeholder="Contoh: Rekomendasi Kebijakan">
    </div>
    <div class="form-row form-row-2" style="margin-top:12px">
      <div class="form-group">
        <label>Kode RO</label>
        <input type="text" id="ro_kode" class="form-control"
          value="${isEdit ? esc(ro.kode_ro || '') : ''}"
          placeholder="Contoh: RK, IKK">
      </div>
      <div class="form-group">
        <label>Tahun</label>
        <input type="number" id="ro_tahun" class="form-control"
          value="${isEdit && ro.tahun ? ro.tahun : new Date().getFullYear()}"
          min="2000" max="2100">
      </div>
    </div>
    <div class="form-group" style="margin-top:12px">
      <label>Deskripsi</label>
      <textarea id="ro_deskripsi" class="form-control" rows="3"
        placeholder="Deskripsi singkat RO...">${isEdit ? esc(ro.deskripsi || '') : ''}</textarea>
    </div>
  `;

  openModal(title, body, [
    { label: 'Batal',  cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary',  action: `saveRo(${isEdit ? ro.id : 'null'})` },
  ]);
}

// ---------------------------------------------------------------
// Save RO (POST / PUT)
// ---------------------------------------------------------------
async function saveRo(id) {
  const nama = document.getElementById('ro_nama')?.value?.trim();
  if (!nama) { showToast('Nama RO wajib diisi.', 'warning'); return; }

  const payload = {
    nama_ro:   nama,
    kode_ro:   document.getElementById('ro_kode')?.value?.trim()     || null,
    deskripsi: document.getElementById('ro_deskripsi')?.value?.trim() || null,
    tahun:     document.getElementById('ro_tahun')?.value             || null,
  };

  const res = id
    ? await apiPut(`ro/${id}`, payload)
    : await apiPost('ro', payload);

  if (!res || !res.status) {
    showToast(res?.message || 'Gagal menyimpan RO.', 'error');
    return;
  }

  closeModal();
  showToast(res.message || 'RO berhasil disimpan.', 'success');
  renderKegiatan();
}

// ---------------------------------------------------------------
// Delete RO
// ---------------------------------------------------------------
function confirmDeleteRo(id, nama) {
  openModal(
    'Hapus RO',
    `<p>Yakin ingin menghapus RO <strong>${esc(nama)}</strong>?</p>
     <p style="font-size:12.5px;color:#64748b;margin-top:6px">
       Semua kegiatan dalam RO ini akan menjadi standalone (tidak terhapus).
     </p>`,
    [
      { label: 'Batal', cls: 'btn-outline', action: 'closeModal()' },
      { label: 'Hapus', cls: 'btn-danger',  action: `_doDeleteRo(${id})` },
    ]
  );
}

async function _doDeleteRo(id) {
  const res = await apiDelete(`ro/${id}`);
  closeModal();
  if (!res || !res.status) { showToast(res?.message || 'Gagal menghapus RO.', 'error'); return; }
  showToast(res.message || 'RO dihapus.', 'success');
  renderKegiatan();
}

// ---------------------------------------------------------------
// Assign Kegiatan ke RO — tampilkan checklist kegiatan on-budget
// ---------------------------------------------------------------
async function showAssignRoModal(roId) {
  openModal('Memuat...', '<div style="padding:20px;text-align:center"><div class="spinner-lg"></div></div>', []);

  const [kegRes, roDetailRes] = await Promise.all([
    apiGet('kegiatan'),
    apiGet(`ro/${roId}`),
  ]);

  if (!kegRes?.status || !roDetailRes?.status) {
    closeModal();
    showToast('Gagal memuat data.', 'error');
    return;
  }

  const allKegiatan = (kegRes.data || []).filter(k => k.tipe === 'on_budget');
  const ro          = roDetailRes.data;

  // Bisa di-assign: yang belum punya RO lain, atau sudah di RO ini
  const assignable = allKegiatan.filter(k => !k.ro_id || parseInt(k.ro_id) === roId);

  const rows = assignable.map(k => {
    const checked = parseInt(k.ro_id) === roId ? 'checked' : '';
    return `
      <label style="display:flex;align-items:center;gap:10px;padding:8px 0;
                    border-bottom:1px solid #f1f5f9;cursor:pointer">
        <input type="checkbox" value="${k.id}" id="assign-keg-${k.id}" ${checked}
               style="width:16px;height:16px;flex-shrink:0">
        <span>
          <span style="font-weight:600;font-size:13px">${esc(k.nama_kegiatan)}</span>
          <span style="font-size:11px;color:#94a3b8;margin-left:6px">${esc(k.nama_tim || '—')}</span>
        </span>
      </label>`;
  }).join('');

  // Simpan state ke variabel modul agar tidak perlu encode ke onclick attribute
  _roAssignState = { roId, allIds: assignable.map(k => k.id) };

  openModal(
    `Assign Kegiatan → ${esc(ro.kode_ro || ro.nama_ro)}`,
    rows
      ? `<div style="max-height:360px;overflow-y:auto;padding:0 2px">${rows}</div>`
      : '<p style="color:#94a3b8;font-size:13px">Tidak ada kegiatan yang dapat di-assign.</p>',
    [
      { label: 'Batal',  cls: 'btn-outline', action: 'closeModal()' },
      { label: 'Simpan', cls: 'btn-primary',  action: '_saveAssignRo()' },
    ]
  );
}

async function _saveAssignRo() {
  const { roId, allIds } = _roAssignState;
  if (!roId || !allIds.length) {
    closeModal();
    return;
  }

  const checked   = allIds.filter(id =>  !!document.getElementById(`assign-keg-${id}`)?.checked);
  const unchecked = allIds.filter(id => !document.getElementById(`assign-keg-${id}`)?.checked);

  const results = await Promise.all([
    ...checked.map(id   => apiPut(`kegiatan/${id}`, { ro_id: roId })),
    ...unchecked.map(id => apiPut(`kegiatan/${id}`, { ro_id: null })),
  ]);

  const anyFailed = results.some(r => !r || !r.status);

  closeModal();
  if (anyFailed) {
    showToast('Sebagian assignment gagal disimpan.', 'error');
  } else {
    showToast('Assignment kegiatan berhasil disimpan.', 'success');
  }
  renderKegiatan();
}
