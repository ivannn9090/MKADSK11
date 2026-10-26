/* ============================================================
   off-budget.js — Off Budget page, Sumber Manager,
                   Realisasi (Off-Budget)
   ============================================================ */
'use strict';

// ---------------------------------------------------------------
// Off Budget Page
// ---------------------------------------------------------------
async function renderOffBudget() {
  const res = await apiGet('off-budget');
  if (!res || !res.status) { showError('Gagal memuat data off-budget'); return; }

  const rows   = res.data || [];
  const canAdd = hasPermission('kegiatan.create');

  const totalPagu      = rows.reduce((s, k) => s + parseFloat(k.pagu_anggaran || 0), 0);
  const totalRealisasi = rows.reduce((s, k) => s + parseFloat(k.total_realisasi || 0), 0);
  const totalSumber    = rows.reduce((s, k) => s + parseInt(k.jumlah_sumber || 0), 0);

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

  // Simpan ke registry agar taskItem/subtaskItem dapat mengakses data tanpa JSON.stringify
  _kegReg[k.id]   = k;
  _usersReg[k.id] = users;
  (k.tasks || []).forEach(t => {
    _taskReg[t.id] = t;
    (t.subtasks || []).forEach(s => { _taskReg[s.id] = s; });
  });

  const sr      = k.sumber_ringkasan || {};
  const sumberList = sr.sumber_list || [];
  const canManageSumber = hasPermission('kegiatan.edit');

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
        ${(k.tasks || []).map(t => taskItem(t, k.id)).join('') || emptyState('Belum ada task')}
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
      ${hasPermission('anggaran.input') ? `
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

      <!-- Info dasar kegiatan -->
      <div class="form-row form-row-2" style="margin-bottom:14px">
        <div><div class="ang-summary-label">Nama Kegiatan</div><div style="font-weight:600">${esc(k.nama_kegiatan)}</div></div>
        <div><div class="ang-summary-label">Tipe</div><div><span style="background:#ede9fe;color:#7c3aed;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:700">Off Budget</span></div></div>
        <div><div class="ang-summary-label">Total Sumber</div><div style="color:#7c3aed;font-weight:600">${sumberList.length} sumber anggaran</div></div>
        <div><div class="ang-summary-label">Tim</div><div>${esc(k.nama_tim || '—')}</div></div>
        <div><div class="ang-summary-label">Ketua Tim</div><div>${esc(k.ketua_tim_nama || '—')}</div></div>
        <div><div class="ang-summary-label">Status</div><div><span class="status-badge status-${k.status}">${statusLabel(k.status)}</span></div></div>
        <div><div class="ang-summary-label">Tanggal Mulai</div><div>${fmtDate(k.tanggal_mulai)}</div></div>
        <div><div class="ang-summary-label">Deadline Kegiatan</div><div>${fmtDate(k.tanggal_selesai)}</div></div>
        <div><div class="ang-summary-label">Alokasi Anggaran</div><div>${rupiah(k.pagu_anggaran)}</div></div>
        <div><div class="ang-summary-label">Total Realisasi</div><div style="color:#16a34a">${rupiah(k.total_realisasi)}</div></div>
      </div>
      <div style="margin-bottom:12px"><div class="ang-summary-label">Deskripsi</div><div style="margin-top:4px;color:#374151">${esc(k.deskripsi || '—')}</div></div>

      <!-- Anggota Tim -->
      <div style="margin-bottom:16px">
        <div class="ang-summary-label" style="margin-bottom:6px">Anggota Tim</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          ${(k.anggota || []).map(a => `<span style="background:#f5f3ff;color:#7c3aed;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600">${esc(a.nama)}</span>`).join('') || '<span style="color:#94a3b8;font-size:12px">—</span>'}
        </div>
      </div>

      <!-- Tabel Task & Subtask -->
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div style="font-weight:700;font-size:13.5px;color:var(--gray-900)">
          Ringkasan Task &amp; Subtask
          <span style="font-size:12px;font-weight:400;color:var(--gray-500);margin-left:6px">(${k.tasks_selesai}/${k.total_tasks} selesai · ${k.progress}%)</span>
        </div>
        <button class="btn btn-sm" onclick="downloadLaporanOffBudget(${k.id})"
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

      <!-- Tombol edit/hapus/konversi -->
      ${hasPermission('kegiatan.edit') ? `
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn btn-outline btn-sm" onclick="showEditKegiatan(_getKeg(${k.id}))">Edit</button>
          ${canManageSumber ? `<button class="btn btn-sm" style="background:#7c3aed;color:#fff" onclick="showSumberManager(${k.id})">⚙ Kelola Sumber</button>` : ''}
          ${hasPermission('kegiatan.delete') ? `<button class="btn btn-sm" style="background:#16a34a;color:#fff" onclick="confirmConvertToOnBudget(${k.id},'${esc(k.nama_kegiatan)}')">🔄 Ubah ke On Budget</button>` : ''}
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

  initTabs(k.id);

  // Lazy-load tab rincian saat pertama kali diklik (off-budget)
  const _obModalBody = document.getElementById('modalBody');
  if (_obModalBody) {
    _obModalBody.querySelectorAll('.tabs .tab').forEach(tab => {
      tab.addEventListener('click', () => {
        if (tab.dataset.tab === 'rincian') loadRincianAnggaran(k.id);
      });
    });
  }

  loadDokumen(k.id);
}

// ---------------------------------------------------------------
// Download Laporan Off Budget (print window → PDF via browser)
// ---------------------------------------------------------------
async function downloadLaporanOffBudget(kegiatanId) {
  const [res, sumberRes, rincianRes] = await Promise.all([
    apiGet(`off-budget/${kegiatanId}`),
    apiGet(`off-budget/${kegiatanId}/sumber`),
    apiGet(`off-budget/${kegiatanId}/rincian`),
  ]);
  if (!res || !res.status) { showError('Gagal memuat data laporan'); return; }
  const k           = res.data;
  const sumberList  = sumberRes?.data || k.sumber_ringkasan?.sumber_list || [];
  const rincianRows = rincianRes?.data?.rows || [];

  const now     = new Date().toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' });
  const appName = document.querySelector('.sidebar-title')?.textContent || 'MKA System';
  const serapan = k.pagu_anggaran > 0 ? ((k.total_realisasi / k.pagu_anggaran) * 100).toFixed(2) : '0.00';
  const anggotaList = (k.anggota || []).map(a => esc(a.nama)).join(', ') || '—';

  // Build rincian rows
  let rincianPrintRows = '';
  if (rincianRows.length) {
    const totalSubtotal = rincianRows.reduce((s, r) => s + parseFloat(r.subtotal || 0), 0);
    const totalTerpakai = rincianRows.reduce((s, r) => s + parseFloat(r.nominal_terpakai || 0), 0);
    rincianRows.forEach((r, i) => {
      const subtotal   = parseFloat(r.subtotal || 0);
      const terpakai   = parseFloat(r.nominal_terpakai || 0);
      const rawPct     = subtotal > 0 ? Math.round(terpakai / subtotal * 100) : 0;
      const dispPct    = Math.min(100, rawPct);
      const pctColor   = rawPct > 100 ? '#dc2626'
                       : rawPct === 100 ? '#16a34a'
                       : rawPct >= 50   ? '#d97706'
                       :                  '#94a3b8';
      const isObSumber = !r.rincian_id; // sumber-only: no component detail
      rincianPrintRows += `
        <tr>
          <td style="text-align:center">${i + 1}</td>
          <td>
            <strong>${esc(r.komponen)}</strong>
            ${r.uraian ? `<br><span style="color:#64748b;font-weight:400">${esc(r.uraian)}</span>` : ''}
            ${isObSumber ? `<br><span style="font-size:10px;color:#7c3aed">Alokasi langsung dari sumber</span>` : ''}
          </td>
          ${isObSumber
            ? `<td colspan="5" style="text-align:center;color:#94a3b8;font-style:italic;font-size:11px">— alokasi sumber —</td>`
            : `<td style="text-align:right">${formatAngka(r.qty)}</td>
               <td>${esc(r.satuan_qty || '—')}</td>
               <td style="text-align:right">${formatAngka(r.volume)}</td>
               <td>${esc(r.satuan_vol || '—')}</td>
               <td style="text-align:right">${rupiah(r.harga_satuan)}</td>`
          }
          <td style="text-align:right;font-weight:700;color:#1e40af">${rupiah(subtotal)}</td>
          <td style="text-align:right;color:${pctColor}">${rupiah(terpakai)}<br><span style="font-size:10px;color:#999">${dispPct}%${rawPct > 100 ? ' ⚠' : ''}</span></td>
        </tr>`;
    });
    rincianPrintRows += `
        <tr style="background:#f5f3ff;font-weight:700">
          <td colspan="7" style="text-align:right">Total</td>
          <td style="text-align:right;color:#1e40af">${rupiah(totalSubtotal)}</td>
          <td style="text-align:right;color:#16a34a">${rupiah(totalTerpakai)}</td>
        </tr>`;
  } else {
    rincianPrintRows = `<tr><td colspan="9" style="text-align:center;color:#999;padding:12px">Belum ada rincian anggaran</td></tr>`;
  }

  // Build sumber rows
  let sumberRows = '';
  if (sumberList.length) {
    sumberList.forEach((s, i) => {
      const sisaSumber = parseFloat(s.sumber_pagu || 0)
        - parseFloat(s.sumber_total_realisasi || 0)
        - parseFloat(s.sumber_total_alokasi_ob || 0);
      sumberRows += `
        <tr>
          <td style="text-align:center">${i + 1}</td>
          <td><strong>${esc(s.sumber_nama)}</strong></td>
          <td style="text-align:right">${rupiahSingkat(s.alokasi)}</td>
          <td style="text-align:right">${rupiahSingkat(s.sumber_pagu)}</td>
          <td style="text-align:right;color:${sisaSumber >= 0 ? '#16a34a' : '#dc2626'}">${rupiahSingkat(sisaSumber)}</td>
          <td>${esc(s.keterangan || '—')}</td>
        </tr>`;
    });
  } else {
    sumberRows = `<tr><td colspan="6" style="text-align:center;color:#999;padding:12px">Belum ada sumber anggaran</td></tr>`;
  }

  // Build realisasi rows
  let realisasiRows = '';
  if ((k.realisasi || []).length) {
    k.realisasi.forEach((r, i) => {
      realisasiRows += `
        <tr>
          <td style="text-align:center">${i + 1}</td>
          <td>${r.tanggal ? fmtDate(r.tanggal) : '—'}</td>
          <td>${esc(r.uraian || '—')}</td>
          <td style="color:#7c3aed;font-weight:600">${esc(r.sumber_nama_kegiatan || r.sumber_nama || '—')}</td>
          <td style="text-align:right">${rupiahSingkat(r.nominal || r.jumlah || 0)}</td>
          <td>${esc(r.created_by_nama || r.nama_user || '—')}</td>
        </tr>`;
    });
  } else {
    realisasiRows = `<tr><td colspan="6" style="text-align:center;color:#999;padding:12px">Belum ada realisasi</td></tr>`;
  }

  // Build task rows
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

  const html = `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Off Budget — ${esc(k.nama_kegiatan)}</title>
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:"Segoe UI",system-ui,sans-serif; font-size:12px; color:#1f2328; background:#fff; padding:32px; }
  h1  { font-size:18px; font-weight:700; color:#6d28d9; margin-bottom:4px; }
  h2  { font-size:13px; font-weight:700; color:#6d28d9; margin:20px 0 8px; border-bottom:2px solid #6d28d9; padding-bottom:4px; }
  .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px 24px; margin:12px 0; }
  .meta-item label { font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; display:block; }
  .meta-item span  { font-weight:600; }
  table { width:100%; border-collapse:collapse; font-size:11px; margin-bottom:8px; }
  th    { background:#6d28d9; color:#fff; padding:7px 8px; text-align:left; font-weight:600; }
  td    { padding:6px 8px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
  tr:nth-child(even) td { background:#f8fafc; }
  .badge { display:inline-block; padding:1px 7px; border-radius:12px; font-size:10px; font-weight:700; }
  .badge-selesai  { background:#dcfce7; color:#16a34a; }
  .badge-berjalan { background:#dbeafe; color:#1e40af; }
  .badge-belum    { background:#f1f5f9; color:#64748b; }
  .badge-ob       { background:#ede9fe; color:#7c3aed; }
  .summary-box { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin:14px 0; }
  .summary-item { background:#f5f3ff; border:1px solid #c4b5fd; border-radius:8px; padding:10px 12px; }
  .summary-item label { font-size:10px; color:#7c3aed; display:block; margin-bottom:3px; text-transform:uppercase; letter-spacing:.04em; }
  .summary-item span  { font-size:14px; font-weight:800; color:#4c1d95; }
  .footer { margin-top:32px; text-align:right; color:#94a3b8; font-size:10px; }
  .header-row { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #6d28d9; padding-bottom:12px; margin-bottom:16px; }
  .ob-tag { display:inline-block; background:#ede9fe; color:#7c3aed; padding:2px 10px; border-radius:10px; font-size:11px; font-weight:700; }
  @media print {
    body { padding:16px; }
    @page { margin:16mm; size:A4 landscape; }
  }
</style>
</head>
<body>

<div class="header-row">
  <div>
    <h1>Laporan Kegiatan Off Budget</h1>
    <div style="font-size:11px;color:#64748b;margin-top:3px">${appName} — Dicetak: ${now}</div>
    <div style="margin-top:6px"><span class="ob-tag">Off Budget</span></div>
  </div>
  <div style="text-align:right;font-size:11px;color:#64748b">
    <div>Progress: <strong>${k.progress}%</strong></div>
    <div>${k.tasks_selesai}/${k.total_tasks} task selesai</div>
    <div style="margin-top:4px">Serapan: <strong>${serapan}%</strong></div>
  </div>
</div>

<h2>Informasi Kegiatan</h2>
<div class="meta-grid">
  <div class="meta-item"><label>Nama Kegiatan</label><span>${esc(k.nama_kegiatan)}</span></div>
  <div class="meta-item"><label>Tipe</label><span>Off Budget</span></div>
  <div class="meta-item"><label>Tim</label><span>${esc(k.nama_tim || '—')}</span></div>
  <div class="meta-item"><label>Ketua Tim</label><span>${esc(k.ketua_tim_nama || '—')}</span></div>
  <div class="meta-item"><label>Status</label><span>${statusLabel(k.status)}</span></div>
  <div class="meta-item"><label>Tanggal Mulai</label><span>${fmtDate(k.tanggal_mulai)}</span></div>
  <div class="meta-item"><label>Deadline Kegiatan</label><span>${fmtDate(k.tanggal_selesai)}</span></div>
  <div class="meta-item"><label>Jumlah Sumber Anggaran</label><span>${sumberList.length} sumber</span></div>
  <div class="meta-item" style="grid-column:1/-1"><label>Anggota Tim</label><span>${anggotaList}</span></div>
  ${k.deskripsi ? `<div class="meta-item" style="grid-column:1/-1"><label>Deskripsi</label><span style="font-weight:400">${esc(k.deskripsi)}</span></div>` : ''}
</div>

<h2>Ringkasan Anggaran</h2>
<div class="summary-box">
  <div class="summary-item"><label>Alokasi Anggaran</label><span>${rupiahSingkat(k.pagu_anggaran)}</span></div>
  <div class="summary-item"><label>Total Realisasi</label><span style="color:#16a34a">${rupiahSingkat(k.total_realisasi)}</span></div>
  <div class="summary-item"><label>Sisa Anggaran</label><span style="color:#d97706">${rupiahSingkat(k.sisa_anggaran)}</span></div>
  <div class="summary-item"><label>Serapan</label><span>${serapan}%</span></div>
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

<h2>Sumber Anggaran (${sumberList.length} Sumber)</h2>
<table>
  <thead>
    <tr>
      <th style="width:30px">#</th>
      <th>Kegiatan Sumber</th>
      <th style="width:110px;text-align:right">Alokasi OB</th>
      <th style="width:110px;text-align:right">Pagu Sumber</th>
      <th style="width:110px;text-align:right">Sisa Sumber</th>
      <th>Keterangan</th>
    </tr>
  </thead>
  <tbody>${sumberRows}</tbody>
</table>

<h2>Riwayat Realisasi</h2>
<table>
  <thead>
    <tr>
      <th style="width:30px">#</th>
      <th style="width:100px">Tanggal</th>
      <th>Uraian</th>
      <th style="width:120px">Sumber</th>
      <th style="width:110px;text-align:right">Nominal</th>
      <th style="width:110px">Oleh</th>
    </tr>
  </thead>
  <tbody>${realisasiRows}</tbody>
  <tfoot>
    <tr>
      <td colspan="4" style="font-weight:700;text-align:right">Total Realisasi</td>
      <td style="font-weight:700;text-align:right;color:#16a34a">${rupiahSingkat(k.total_realisasi)}</td>
      <td></td>
    </tr>
  </tfoot>
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
  <tbody>${taskRows}</tbody>
</table>

<div class="footer">Laporan Off Budget ini digenerate otomatis dari ${appName} pada ${now}</div>

</body>
</html>`;

  const win = window.open('', '_blank', 'width=1100,height=750');
  win.document.write(html);
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 600);
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

  const rows     = sumberRes?.data || [];
  const onBudget = (onBudgetRes?.data || []).filter(k => k.id != offBudgetId);

  document.getElementById('modalTitle').textContent = '⚙ Kelola Sumber Anggaran Off Budget';

  const sumberOptions = onBudget.map(k =>
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
                  <th>Komponen</th>
                  <th>Alokasi</th>
                  <th>Sisa Komponen</th>
                  <th>Keterangan</th>
                  <th style="width:80px">Aksi</th>
                </tr>
              </thead>
              <tbody>
                ${rows.map(s => {
                  // Sisa sumber keseluruhan (fallback jika tidak ada komponen)
                  const sisaSumber = parseFloat(s.sumber_pagu || 0)
                    - parseFloat(s.sumber_total_realisasi || 0)
                    - parseFloat(s.sumber_total_alokasi_ob || 0);
                  // Sisa komponen = subtotal komponen - alokasi OB komponen ini - realisasi OB yg terkait
                  // Tampilkan info komponen jika ada
                  const komponenLabel = s.rincian_komponen
                    ? `<span style="background:#eff6ff;color:#1e40af;padding:1px 7px;border-radius:6px;font-size:11px;font-weight:600">
                         📋 ${esc(s.rincian_komponen)}
                       </span>
                       <div style="font-size:10px;color:#64748b;margin-top:2px">
                         Anggaran: ${rupiah(s.rincian_subtotal)}
                       </div>`
                    : `<span style="color:#94a3b8;font-size:11px">— seluruh kegiatan —</span>`;
                  return `
                  <tr id="sumber-row-${s.id}">
                    <td style="font-weight:600">${esc(s.sumber_nama)}</td>
                    <td>${komponenLabel}</td>
                    <td>
                      <input type="number" id="sm_alokasi_${s.id}"
                        value="${s.alokasi}"
                        style="width:120px;padding:4px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px">
                    </td>
                    <td style="color:${sisaSumber >= 0 ? '#16a34a' : '#dc2626'};font-size:12px">
                      ${rupiah(sisaSumber)}
                    </td>
                    <td>
                      <input type="text" id="sm_ket_${s.id}"
                        value="${esc(s.keterangan || '')}"
                        placeholder="Keterangan..."
                        style="width:120px;padding:4px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px">
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
      ${onBudget.length === 0
        ? `<div style="color:#9ca3af;font-size:12px;font-style:italic">Tidak ada kegiatan on-budget tersedia.</div>`
        : `<div class="form-row form-row-2">
            <div class="form-group">
              <label>Kegiatan Sumber *</label>
              <select id="sm_new_sumber" onchange="_smLoadKomponen(${offBudgetId})">
                <option value="">— Pilih Kegiatan —</option>
                ${sumberOptions}
              </select>
            </div>
            <div class="form-group">
              <label>Komponen Biaya
                <span style="font-weight:400;color:#94a3b8;font-size:11px">(opsional)</span>
              </label>
              <select id="sm_new_rincian" disabled>
                <option value="">— pilih kegiatan dulu —</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Alokasi (Rp) *
              <span id="sm_new_sisa_label" style="font-weight:400;color:#94a3b8;font-size:11px"></span>
            </label>
            <input type="number" id="sm_new_alokasi" placeholder="0">
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

/** Load komponen rincian saat kegiatan sumber dipilih */
async function _smLoadKomponen(offBudgetId) {
  const sumberId = document.getElementById('sm_new_sumber')?.value;
  const rincianSel  = document.getElementById('sm_new_rincian');
  const alokasiEl   = document.getElementById('sm_new_alokasi');
  const sisaLabelEl = document.getElementById('sm_new_sisa_label');

  if (!sumberId) {
    rincianSel.innerHTML = '<option value="">— pilih kegiatan dulu —</option>';
    rincianSel.disabled = true;
    if (sisaLabelEl) sisaLabelEl.textContent = '';
    return;
  }

  rincianSel.innerHTML = '<option value="">Memuat...</option>';
  rincianSel.disabled = true;

  const res = await apiGet(`kegiatan/${sumberId}/rincian/sisa`);
  const komponenRows = (res?.data?.rows || []).filter(r => parseFloat(r.nominal_sisa) > 0);

  if (komponenRows.length === 0) {
    rincianSel.innerHTML = '<option value="">— tidak ada komponen tersedia —</option>';
    rincianSel.disabled = true;
    if (sisaLabelEl) sisaLabelEl.textContent = '';
    return;
  }

  rincianSel.innerHTML = `
    <option value="">— semua komponen (tanpa pilih) —</option>
    ${komponenRows.map(r =>
      `<option value="${r.id}"
        data-sisa="${r.nominal_sisa}"
        data-subtotal="${r.subtotal}">
        ${esc(r.komponen)} — sisa ${rupiah(parseFloat(r.nominal_sisa))}
      </option>`
    ).join('')}
  `;
  rincianSel.disabled = false;

  // Update label sisa & isi alokasi otomatis saat komponen berubah
  rincianSel.onchange = () => {
    const opt = rincianSel.options[rincianSel.selectedIndex];
    const sisa = opt?.dataset?.sisa ? parseFloat(opt.dataset.sisa) : null;
    if (sisaLabelEl) {
      sisaLabelEl.textContent = sisa != null ? `(maks ${rupiah(sisa)})` : '';
    }
    if (alokasiEl && sisa != null && !alokasiEl.value) {
      alokasiEl.value = sisa;
    }
    if (alokasiEl && sisa != null) alokasiEl.max = sisa;
  };

  // Trigger untuk komponen pertama
  rincianSel.dispatchEvent(new Event('change'));
}

async function addSumber(offBudgetId) {
  const sumber_id         = document.getElementById('sm_new_sumber')?.value;
  const rincian_anggaran_id = document.getElementById('sm_new_rincian')?.value || null;
  const alokasi           = document.getElementById('sm_new_alokasi')?.value;
  const keterangan        = document.getElementById('sm_new_ket')?.value?.trim() || null;

  if (!sumber_id) { showToast('Pilih kegiatan sumber', 'error'); return; }
  if (!alokasi || parseFloat(alokasi) <= 0) { showToast('Masukkan alokasi yang valid', 'error'); return; }

  const res = await apiPost(`off-budget/${offBudgetId}/sumber`, {
    sumber_id,
    rincian_anggaran_id: rincian_anggaran_id ? parseInt(rincian_anggaran_id) : null,
    alokasi,
    keterangan,
  });
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

// ---------------------------------------------------------------
// Realisasi Anggaran (Off-Budget) — dengan pilihan sumber
// ---------------------------------------------------------------

// Cache sumber list untuk off-budget realisasi form (hindari passing via onclick attribute)
let _obSumberCache = [];

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

  const totalSisa = sumberList.reduce((s, x) => s + getSisaAlokasi(x), 0);

  openModal('Input Realisasi Anggaran (Off Budget)', `
    <div class="form-row form-row-2">
      <div class="form-group">
        <label>Tanggal *</label>
        <input type="date" id="frob_tanggal" value="${todayWIB()}">
      </div>
      <div class="form-group">
        <label>Total Nominal (Rp)</label>
        <input type="number" id="frob_nominal_total" placeholder="0" readonly
          style="font-weight:700;font-size:15px;background:#f1f5f9;color:#1e40af;cursor:default">
        <div style="font-size:11px;color:#64748b;margin-top:3px">
          Terisi otomatis dari distribusi di bawah
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
              // Label sumber: nama kegiatan + nama komponen jika ada
              const komponenBadge = s.rincian_komponen
                ? `<span style="display:inline-block;background:#eff6ff;color:#1e40af;
                     padding:1px 6px;border-radius:5px;font-size:10px;font-weight:600;margin-top:2px">
                     📋 ${esc(s.rincian_komponen)}
                   </span>`
                : '';
              return `
              <tr style="border-top:1px solid #e2e8f0">
                <td style="padding:8px 10px">
                  <div style="font-weight:600">${esc(s.sumber_nama)}</div>
                  ${komponenBadge}
                  <div style="color:#9ca3af;font-size:10px;margin-top:2px">
                    Alokasi: ${rupiahSingkat(s.alokasi)} &nbsp;|&nbsp; Sisa: ${rupiahSingkat(sisa)}
                  </div>
                </td>
                <td style="padding:8px 10px;text-align:right;color:${sisa > 0 ? '#16a34a' : '#dc2626'};font-weight:600">
                  ${rupiah(sisa)}
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

/**
 * Hitung sisa alokasi sumber ini.
 * sisa = alokasi (yang dialokasikan ke OB ini) - realisasi_OB yang sudah terpakai dari sumber ini.
 * Field sumber_total_realisasi = total realisasi kegiatan sumber (on-budget), bukan yang kita butuhkan.
 * Yang benar: sisa = alokasi - nominal realisasi OB yang memakai obs.id ini.
 * Karena API mengembalikan sumber_total_realisasi (total realisasi kegiatan sumber keseluruhan),
 * kita pakai field yang tersedia: alokasi - terpakai_di_ob_ini.
 * Jika backend mengembalikan field 'terpakai' per sumber, gunakan itu; fallback ke alokasi saja.
 */
function getSisaAlokasi(s) {
  // terpakai = nominal realisasi OB yang sudah menggunakan sumber (obs.id) ini
  const terpakai = parseFloat(s.terpakai || 0);
  return Math.max(0, parseFloat(s.alokasi || 0) - terpakai);
}

/** Auto-distribusi: isi setiap sumber dengan maks sisanya */
function autoDistribusiOB() {
  _obSumberCache.forEach(s => {
    const sisa  = getSisaAlokasi(s);
    const input = document.getElementById(`frob_split_${s.id}`);
    if (input) input.value = sisa > 0 ? sisa : 0;
  });
  updateSplitTotal();
}

/** Update total split → sync ke field nominal total (readonly) */
function updateSplitTotal() {
  const totalEl  = document.getElementById('frob_split_total');
  const nominalEl = document.getElementById('frob_nominal_total');
  if (!totalEl) return;

  let total = 0;
  _obSumberCache.forEach(s => {
    total += parseFloat(document.getElementById(`frob_split_${s.id}`)?.value) || 0;
  });

  totalEl.textContent = rupiah(total);
  // Sync ke field nominal total (readonly — otomatis dari split)
  if (nominalEl) nominalEl.value = total > 0 ? total : '';
}

async function saveRealisasiOB(kegiatanId) {
  const tanggal = document.getElementById('frob_tanggal')?.value;
  const uraian  = document.getElementById('frob_uraian')?.value?.trim();
  const bukti   = document.getElementById('frob_bukti')?.value?.trim() || null;

  if (!tanggal) { showToast('Tanggal wajib diisi', 'error'); return; }
  if (!uraian)  { showToast('Uraian wajib diisi', 'error'); return; }

  // Kumpulkan splits — nominal total dihitung dari splits (field nominal_total = readonly)
  const splits = [];
  let nominal = 0;
  _obSumberCache.forEach(s => {
    const v = parseFloat(document.getElementById(`frob_split_${s.id}`)?.value) || 0;
    if (v > 0) {
      splits.push({ sumber_ob_id: parseInt(s.id), nominal: v });
      nominal += v;
    }
  });

  if (splits.length === 0) {
    showToast('Masukkan nominal pada minimal satu sumber anggaran', 'error'); return;
  }
  if (nominal <= 0) { showToast('Total nominal harus lebih dari 0', 'error'); return; }

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
        ${hasPermission('anggaran.delete') ? `
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

async function confirmConvertToOnBudget(id, nama) {
  if (!confirm(
    `Ubah kegiatan "${nama}" menjadi On Budget?\n\n` +
    `Seluruh data sumber anggaran off-budget yang terhubung tidak akan ikut terhapus, ` +
    `namun kegiatan ini tidak lagi dapat diakses dari halaman Off Budget.\n\n` +
    `Lanjutkan?`
  )) return;

  const res = await apiPatch(`kegiatan/${id}/convert-to-on-budget`, {});
  if (res && res.status) {
    showToast('Kegiatan berhasil diubah menjadi On Budget', 'success');
    closeModal();
    renderOffBudget();
  } else {
    showToast(res?.message || 'Gagal mengubah tipe kegiatan', 'error');
  }
}
