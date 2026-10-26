/* ============================================================
   laporan.js — Laporan page (Progress & Anggaran)
   ============================================================ */
'use strict';

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
