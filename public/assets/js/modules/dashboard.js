/* ============================================================
   dashboard.js — Dashboard page & charts
   ============================================================ */
'use strict';

// Cache progress_list untuk dipakai oleh filter dropdown
let _dashProgressList = [];

async function renderDashboard() {
  const res = await apiGet('dashboard');
  if (!res || !res.status) { showError('Gagal memuat dashboard'); return; }

  const { kegiatan, anggaran, progress_list, serapan_bulanan, ranking_tim, rpd_dashboard } = res.data;
  _dashProgressList = progress_list || [];

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

    <!-- Chart: Status Kegiatan (Donat) full width -->
    <div class="chart-card" style="margin-bottom:24px">
      <div class="chart-title">Status Kegiatan</div>
      <div class="chart-donut-row">
        <div class="chart-donut-wrap">
          <canvas id="chartStatus"></canvas>
          <div class="chart-donut-center" id="chartStatusCenter">
            <div class="donut-total">${kegiatan.total || 0}</div>
            <div class="donut-label">Total</div>
          </div>
        </div>
        <div class="chart-legend-col" id="chartStatusLegend"></div>
      </div>
    </div>

    <!-- Progress Per Kegiatan -->
    <div class="section-header">
      <div class="section-title">Progress Kegiatan</div>
      <div class="progress-filter-wrap">
        <select id="progressFilter" class="progress-filter-select" onchange="filterProgressKegiatan()">
          <option value="all">Semua Tipe</option>
          <option value="on_budget">On Budget</option>
          <option value="off_budget">Off Budget</option>
        </select>
      </div>
    </div>
    <div id="progressList" style="margin-bottom:24px">
      ${renderProgressItems(_dashProgressList)}
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
      ${hasPermission('rpd.edit') ? `
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

  // Draw chart setelah DOM selesai di-render
  drawDonutChart(kegiatan);
}

// ---------------------------------------------------------------
// Render progress items (reusable untuk filter)
// ---------------------------------------------------------------
function renderProgressItems(list) {
  if (!list || list.length === 0) return emptyState('Belum ada kegiatan');
  return list.map(k => {
    const isOB = k.tipe === 'off_budget';
    return `
      <div style="background:#fff;border-radius:10px;border:1px solid #e2e8f0;padding:14px 16px;margin-bottom:10px;cursor:pointer"
           onclick="navigateTo('${isOB ? 'off_budget' : 'kegiatan'}')">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0">
            ${isOB ? '<span style="background:#ede9fe;color:#7c3aed;font-size:10px;font-weight:700;padding:2px 7px;border-radius:8px;white-space:nowrap">Off Budget</span>' : '<span style="background:#eff6ff;color:#1e40af;font-size:10px;font-weight:700;padding:2px 7px;border-radius:8px;white-space:nowrap">On Budget</span>'}
            <span style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(k.nama_kegiatan)}</span>
          </div>
          <span class="status-badge status-${k.status}" style="flex-shrink:0;margin-left:8px">${statusLabel(k.status)}</span>
        </div>
        <div class="progress-wrap">
          <div class="progress-header">
            <span style="color:#64748b;font-size:12px">👤 ${esc(k.ketua_tim_nama || '—')}</span>
            <span style="font-weight:600">${k.progress}%</span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill progress-${k.warna_status}" style="width:${k.progress}%"></div>
          </div>
        </div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:#64748b;margin-top:6px">
          <span>${isOB ? 'Alokasi' : 'Pagu'}: <strong style="color:#1e40af">${rupiah(k.pagu_anggaran)}</strong></span>
          <span>Realisasi: <strong style="color:#16a34a">${rupiah(k.total_realisasi)}</strong></span>
          <span>Sisa: <strong style="color:${parseFloat(k.sisa_anggaran) < 0 ? '#dc2626' : '#d97706'}">${rupiah(k.sisa_anggaran)}</strong></span>
          <span>Serapan: <strong>${k.persentase_serapan}%</strong></span>
          <span>Deadline: <strong>${fmtDate(k.tanggal_selesai)}</strong></span>
        </div>
      </div>
    `;
  }).join('');
}

// Filter progress list berdasarkan dropdown
function filterProgressKegiatan() {
  const filter = document.getElementById('progressFilter')?.value || 'all';
  const filtered = filter === 'all'
    ? _dashProgressList
    : _dashProgressList.filter(k => k.tipe === filter);
  const el = document.getElementById('progressList');
  if (el) el.innerHTML = renderProgressItems(filtered);
}

// ---------------------------------------------------------------
// Donat Chart — Status Kegiatan (semua 4 status: draft, berjalan, terlambat, selesai)
// ---------------------------------------------------------------
function drawDonutChart(kegiatan) {
  const canvas = document.getElementById('chartStatus');
  if (!canvas) return;

  // Semua 4 status ditampilkan sebagai slice
  const sliceData   = [
    +(kegiatan.draft     || 0),
    +(kegiatan.berjalan  || 0),
    +(kegiatan.terlambat || 0),
    +(kegiatan.selesai   || 0),
  ];
  const sliceLabels = ['Draft', 'Berjalan', 'Terlambat', 'Selesai'];
  const sliceColors = ['#94a3b8', '#3b82f6', '#dc2626', '#16a34a'];

  const grandTotal = sliceData.reduce((a, b) => a + b, 0);
  const sliceTotal = grandTotal || 1;

  // Ukuran canvas mengikuti wrapper
  const wrap = canvas.parentElement;
  const size = Math.min(wrap.offsetWidth || 220, 220);
  canvas.width  = size;
  canvas.height = size;

  const ctx    = canvas.getContext('2d');
  const cx     = size / 2;
  const cy     = size / 2;
  const rOuter = Math.min(cx, cy) - 6;
  const rInner = rOuter * 0.58;
  let   angle  = -Math.PI / 2;
  const gap    = 0.02;

  ctx.clearRect(0, 0, size, size);

  // Jika semua 0 — gambar lingkaran kosong
  if (grandTotal === 0) {
    ctx.beginPath();
    ctx.arc(cx, cy, rOuter, 0, Math.PI * 2);
    ctx.arc(cx, cy, rInner, Math.PI * 2, 0, true);
    ctx.closePath();
    ctx.fillStyle = '#e2e8f0';
    ctx.fill();
  }

  // Gambar setiap slice
  sliceData.forEach((val, i) => {
    if (val === 0) return;
    const slice = (val / sliceTotal) * Math.PI * 2;
    ctx.beginPath();
    ctx.arc(cx, cy, rOuter, angle + gap / 2, angle + slice - gap / 2);
    ctx.arc(cx, cy, rInner, angle + slice - gap / 2, angle + gap / 2, true);
    ctx.closePath();
    ctx.fillStyle = sliceColors[i];
    ctx.fill();
    angle += slice;
  });

  // Angka tengah = grand total
  const centerEl = document.getElementById('chartStatusCenter');
  if (centerEl) {
    centerEl.querySelector('.donut-total').textContent = grandTotal;
  }

  // Legend: tampilkan semua 4 status (termasuk yang bernilai 0)
  const legendEl = document.getElementById('chartStatusLegend');
  if (legendEl) {
    legendEl.innerHTML = sliceData.map((val, i) => `
      <div class="legend-col-item">
        <div class="legend-col-dot" style="background:${sliceColors[i]}"></div>
        <div class="legend-col-info">
          <div class="legend-col-label">${sliceLabels[i]}</div>
          <div class="legend-col-nums">
            <span class="legend-col-val">${val}</span>
            <span class="legend-col-pct">${grandTotal > 0 ? Math.round((val / grandTotal) * 100) : 0}%</span>
          </div>
        </div>
      </div>
    `).join('');
  }
}
