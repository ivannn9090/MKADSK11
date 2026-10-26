// ============================================================
// ltk.js — Layanan Telaah Konsep Frontend Module
// Semua fungsi renderLtk* dipanggil dari loadPage() di app.js
// ============================================================

// ──────────────────────────────────────────────────────────────
// Status helpers
// ──────────────────────────────────────────────────────────────
const LTK_STATUS_LABEL = {
  draft:                              'Draft',
  menunggu_srikandi:                  'Menunggu Pengiriman SRIKANDI',
  menunggu_verifikasi_tu:             'Menunggu Verifikasi TU',
  terverifikasi:                      'Terverifikasi',
  masuk_antrian:                      'Masuk Antrian',
  ditugaskan_pic:                     'Ditugaskan PIC',
  diterima_pic:                       'Diterima PIC',
  asesmen_awal:                       'Asesmen Awal',
  tidak_perlu_tindak_lanjut:          'Tidak Perlu Tindak Lanjut',
  penyusunan_policy_brief:            'Penyusunan Policy Brief',
  penyusunan_nota_dinas_tanggapan:    'Nota Dinas Tanggapan',
  perlu_tindak_lanjut:                'Perlu Tindak Lanjut',
  nota_dinas_permintaan_penyesuaian:  'Permintaan Penyesuaian',
  kajian_lanjutan:                    'Kajian Lanjutan',
  penyusunan_policy_paper:            'Penyusunan Policy Paper',
  nota_dinas_hasil_telaah_akhir:      'Nota Dinas Hasil Akhir',
  selesai:                            'Selesai',
  ditutup:                            'Ditutup',
}

const LTK_STATUS_CLASS = {
  draft:                             'status-draft',
  menunggu_srikandi:                 'status-draft',
  menunggu_verifikasi_tu:            'status-terlambat',
  terverifikasi:                     'status-berjalan',
  masuk_antrian:                     'status-berjalan',
  ditugaskan_pic:                    'status-berjalan',
  diterima_pic:                      'status-berjalan',
  asesmen_awal:                      'status-berjalan',
  tidak_perlu_tindak_lanjut:         'status-berjalan',
  penyusunan_policy_brief:           'status-berjalan',
  penyusunan_nota_dinas_tanggapan:   'status-berjalan',
  perlu_tindak_lanjut:               'status-berjalan',
  nota_dinas_permintaan_penyesuaian: 'status-berjalan',
  kajian_lanjutan:                   'status-berjalan',
  penyusunan_policy_paper:           'status-berjalan',
  nota_dinas_hasil_telaah_akhir:     'status-berjalan',
  selesai:                           'status-selesai',
  ditutup:                           'status-selesai',
}

function ltkStatusBadge(status) {
  const label = LTK_STATUS_LABEL[status] || status
  const cls   = LTK_STATUS_CLASS[status] || 'status-draft'
  return `<span class="status-badge ${cls}">${label}</span>`
}

function ltkSlaChip(slaInfo) {
  if (!slaInfo) return ''
  const map = { success: 'status-selesai', warning: 'status-terlambat', danger: 'status-terlambat' }
  return `<span class="status-badge ${map[slaInfo.level] || 'status-draft'}" style="font-size:10px">${esc(slaInfo.label)}</span>`
}

// ──────────────────────────────────────────────────────────────
// DASHBOARD TU / Admin
// ──────────────────────────────────────────────────────────────
async function renderLtkDashboardTu() {
  const content = document.getElementById('mainContent')
  try {
    const res = await apiGet('ltk/dashboard/tu')
    const d   = res.data
    content.innerHTML = `
      <div class="page-header" style="margin-bottom:20px">
        <h2 style="font-size:18px;font-weight:700;color:var(--gray-800)">Dashboard Layanan Telaah Konsep</h2>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:24px">
        ${ltkStatCard('Total Permohonan', d.total, '#3b82f6', '📋')}
        ${ltkStatCard('Input SRIKANDI', d.tu_input ?? 0, '#1d4ed8', '📥')}
        ${ltkStatCard('Dalam Antrian', d.dalam_antrian, '#06b6d4', '⏳')}
        ${ltkStatCard('Sedang Ditelaah', d.sedang_ditelaah, '#6366f1', '🔍')}
        ${ltkStatCard('Selesai', d.selesai, '#10b981', '✅')}
        ${ltkStatCard('Mendekati SLA', d.mendekati_sla, '#f59e0b', '⚠️')}
        ${ltkStatCard('Terlambat SLA', d.terlambat_sla, '#ef4444', '🚨')}
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="card">
          <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-weight:700">Permohonan Terbaru</span>
            <button class="btn btn-sm btn-primary" onclick="navigateTo('ltk_permohonan')">Lihat Semua</button>
          </div>
          <div id="ltkRecentList"><div class="spinner-sm"></div></div>
        </div>
        <div class="card">
          <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-weight:700">Antrian Aktif</span>
            <button class="btn btn-sm btn-primary" onclick="navigateTo('ltk_antrian')">Kelola Antrian</button>
          </div>
          <div id="ltkAntrianPreview"><div class="spinner-sm"></div></div>
        </div>
      </div>
    `
    // Load tabel terbaru
    ltkLoadRecentPermohonan()
    ltkLoadAntrianPreview()
  } catch(e) {
    content.innerHTML = `<div class="alert alert-danger">Gagal memuat dashboard: ${e.message}</div>`
  }
}

function ltkStatCard(label, value, color, icon) {
  return `
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 14px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.05)">
      <div style="font-size:22px;margin-bottom:4px">${icon}</div>
      <div style="font-size:24px;font-weight:700;color:${color}">${value ?? 0}</div>
      <div style="font-size:11px;color:#6b7280;margin-top:2px">${label}</div>
    </div>`
}

async function ltkLoadRecentPermohonan() {
  try {
    const res  = await apiGet('ltk/permohonan')
    const rows = (res.data?.items || []).slice(0, 5)
    const el   = document.getElementById('ltkRecentList')
    if (!el) return
    if (!rows.length) { el.innerHTML = emptyState('Belum ada permohonan.'); return }
    el.innerHTML = rows.map(p => `
      <div style="padding:10px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;gap:8px">
        <div>
          <div style="font-weight:600;font-size:13px">${esc(p.kode)}
            ${p.sumber_permohonan === 'tu_input'
              ? `<span style="font-size:9px;background:#dbeafe;color:#1d4ed8;padding:1px 5px;border-radius:6px;font-weight:700;margin-left:4px">SRIKANDI</span>`
              : ''}
          </div>
          <div style="font-size:11px;color:#6b7280">${esc(p.pengusul_nama || p.klien_nama || '-')} · ${esc(p.unit_kerja || '-')}</div>
        </div>
        ${ltkStatusBadge(p.status)}
      </div>`).join('')
  } catch(e) {}
}

async function ltkLoadAntrianPreview() {
  try {
    const res  = await apiGet('ltk/antrian')
    const rows = (res.data?.items || []).slice(0, 5)
    const el   = document.getElementById('ltkAntrianPreview')
    if (!el) return
    if (!rows.length) { el.innerHTML = emptyState('Antrian kosong.'); return }
    el.innerHTML = rows.map(p => `
      <div style="padding:10px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;gap:8px">
        <div>
          <div style="font-weight:600;font-size:13px">${esc(p.kode)}</div>
          <div style="font-size:11px;color:#6b7280">${esc(p.klien_nama || '-')}</div>
        </div>
        <span class="status-badge ${p.jenis_antrian === 'prioritas' ? 'status-terlambat' : 'status-draft'}">${p.jenis_antrian}</span>
      </div>`).join('')
  } catch(e) {}
}

// ──────────────────────────────────────────────────────────────
// DASHBOARD PIC
// ──────────────────────────────────────────────────────────────
async function renderLtkDashboardPic() {
  const content = document.getElementById('mainContent')
  try {
    const res = await apiGet('ltk/dashboard/pic')
    const d   = res.data
    content.innerHTML = `
      <div class="page-header" style="margin-bottom:20px">
        <h2 style="font-size:18px;font-weight:700;color:var(--gray-800)">Dashboard Telaah — PIC</h2>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:24px">
        ${ltkStatCard('Tugas Aktif',     d.tugas_aktif,   '#3b82f6', '📋')}
        ${ltkStatCard('Regular',         d.regular,       '#6366f1', '📄')}
        ${ltkStatCard('Prioritas',       d.prioritas,     '#f59e0b', '⭐')}
        ${ltkStatCard('Policy Brief',    d.policy_brief,  '#06b6d4', '📝')}
        ${ltkStatCard('Policy Paper',    d.policy_paper,  '#8b5cf6', '📰')}
        ${ltkStatCard('Mendekati SLA',   d.mendekati_sla, '#f59e0b', '⚠️')}
        ${ltkStatCard('Terlambat SLA',   d.terlambat_sla, '#ef4444', '🚨')}
      </div>
      <div class="card">
        <div class="card-header" style="font-weight:700">Tugas Aktif Saya</div>
        <div id="ltkPicTugasList"><div class="spinner-sm"></div></div>
      </div>
    `
    ltkLoadPicTugas()
  } catch(e) {
    content.innerHTML = `<div class="alert alert-danger">Gagal memuat dashboard: ${e.message}</div>`
  }
}

async function ltkLoadPicTugas() {
  try {
    const res  = await apiGet('ltk/pic/tugas')
    const rows = res.data?.items || []
    const el   = document.getElementById('ltkPicTugasList')
    if (!el) return
    if (!rows.length) { el.innerHTML = emptyState('Tidak ada tugas aktif.'); return }
    el.innerHTML = `<table class="table table-hover" style="font-size:13px">
      <thead><tr><th>Kode</th><th>Judul</th><th>Prioritas</th><th>SLA</th><th>Status</th><th></th></tr></thead>
      <tbody>${rows.map(p => `
        <tr>
          <td><strong>${esc(p.kode)}</strong></td>
          <td>${esc(p.judul)}</td>
          <td><span class="status-badge ${p.jenis_antrian==='prioritas'?'status-terlambat':'status-draft'}">${p.jenis_antrian}</span></td>
          <td>${p.sla_info ? ltkSlaChip(p.sla_info) : '-'}</td>
          <td>${ltkStatusBadge(p.status)}</td>
          <td><button class="btn btn-sm btn-primary" onclick="ltkShowTimeline(${p.id})">Timeline</button></td>
        </tr>`).join('')}
      </tbody>
    </table>`
  } catch(e) {}
}

// ──────────────────────────────────────────────────────────────
// DASHBOARD KLIEN — halaman tunggal: stat cards + tombol ajukan + tabel pengajuan
// ──────────────────────────────────────────────────────────────
async function renderLtkDashboardKlien() {
  const content = document.getElementById('mainContent')
  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px">
      <div>
        <h2 style="font-size:18px;font-weight:700;color:var(--gray-800);margin:0 0 4px">Layanan Telaah Konsep</h2>
        <div style="font-size:13px;color:#6b7280">Pantau status pengajuan telaah Anda secara realtime.</div>
      </div>
      <button class="btn btn-primary" onclick="ltkShowFormPengajuan()">
        <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        Ajukan Telaah Baru
      </button>
    </div>
    <div id="ltkKlienStats" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:20px">
      <div class="spinner-sm" style="grid-column:1/-1;margin:0 auto"></div>
    </div>
    <div class="card">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-weight:700">Pengajuan Saya</span>
        <button class="btn btn-sm btn-secondary" onclick="ltkLoadKlienPage()">↻ Refresh</button>
      </div>
      <div id="ltkKlienList"><div class="spinner-sm" style="margin:16px auto"></div></div>
    </div>`
  ltkLoadKlienPage()
}

// Muat stat + tabel pengajuan klien sekaligus
async function ltkLoadKlienPage() {
  try {
    // Muat stats
    const dashRes = await apiGet('ltk/dashboard/klien')
    const d = dashRes.data || {}
    const statsEl = document.getElementById('ltkKlienStats')
    if (statsEl) {
      statsEl.innerHTML = `
        ${ltkStatCard('Total Pengajuan',     d.total             ?? 0, '#3b82f6', '📋')}
        ${ltkStatCard('Menunggu Verifikasi', d.menunggu_verifikasi ?? 0, '#f59e0b', '⏳')}
        ${ltkStatCard('Dalam Antrian',       d.dalam_antrian     ?? 0, '#06b6d4', '📥')}
        ${ltkStatCard('Sedang Diproses',     d.sedang_diproses   ?? 0, '#6366f1', '🔍')}
        ${ltkStatCard('Selesai',             d.selesai           ?? 0, '#10b981', '✅')}`
    }

    // Muat daftar permohonan
    const listRes = await apiGet('ltk/permohonan/saya')
    const rows    = listRes.data?.items || []
    const el      = document.getElementById('ltkKlienList')
    if (!el) return
    if (!rows.length) {
      el.innerHTML = `
        <div style="text-align:center;padding:32px 16px">
          <div style="font-size:36px;margin-bottom:10px">📭</div>
          <div style="font-size:13px;color:#6b7280;margin-bottom:12px">Belum ada pengajuan.</div>
          <button class="btn btn-primary btn-sm" onclick="ltkShowFormPengajuan()">
            Ajukan Telaah Pertama Anda
          </button>
        </div>`
      return
    }
    el.innerHTML = `
      <div style="overflow-x:auto">
        <table class="table table-hover" style="font-size:13px">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Judul</th>
              <th>Status</th>
              <th>Deadline</th>
              <th>Tanggal Ajuan</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            ${rows.map(p => `
              <tr>
                <td><strong>${esc(p.kode)}</strong></td>
                <td style="max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(p.judul)}</td>
                <td>${ltkStatusBadge(p.status)}</td>
                <td>${p.tgl_deadline
                  ? `<span style="font-size:12px">${p.tgl_deadline}</span>${p.is_sla_breach ? '<span class="status-badge status-terlambat" style="font-size:9px;margin-left:4px">BREACH</span>' : ''}`
                  : '<span style="color:#9ca3af">—</span>'}</td>
                <td><small style="color:#6b7280">${fmtDate(p.created_at)}</small></td>
                <td>
                  <div style="display:flex;gap:4px">
                    <button class="btn btn-sm btn-primary" onclick="ltkShowTimeline(${p.id})">📋 Timeline</button>
                    ${p.status === 'menunggu_srikandi'
                      ? `<button class="btn btn-sm btn-success" onclick="ltkKonfirmasiSrikandi(${p.id},'${esc(p.kode)}')">Konfirmasi SRIKANDI</button>`
                      : ''}
                  </div>
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>`
  } catch(e) {
    const el = document.getElementById('ltkKlienList')
    if (el) el.innerHTML = `<div class="alert alert-danger">Gagal memuat: ${e.message}</div>`
  }
}

// ──────────────────────────────────────────────────────────────
// PERMOHONAN (TU/Admin)
// ──────────────────────────────────────────────────────────────
async function renderLtkPermohonan() {
  const content = document.getElementById('mainContent')
  content.innerHTML = `
    <div class="page-header" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="text" id="ltkSearchQ" class="form-control" placeholder="Cari kode / judul / pengusul..." style="width:220px;font-size:13px" oninput="ltkLoadPermohonanList()">
        <select id="ltkFilterStatus" class="form-control" style="width:200px;font-size:13px" onchange="ltkLoadPermohonanList()">
          <option value="">Semua Status</option>
          ${Object.entries(LTK_STATUS_LABEL).map(([v,l]) => `<option value="${v}">${l}</option>`).join('')}
        </select>
      </div>
      <button class="btn btn-primary" onclick="ltkShowTuInputForm()">
        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        + Input dari SRIKANDI
      </button>
    </div>
    <div id="ltkPermohonanTable"><div class="loading-page"><div class="spinner-lg"></div></div></div>
  `
  ltkLoadPermohonanList()
}

async function ltkLoadPermohonanList() {
  const q      = document.getElementById('ltkSearchQ')?.value || ''
  const status = document.getElementById('ltkFilterStatus')?.value || ''
  const el     = document.getElementById('ltkPermohonanTable')
  if (!el) return

  let url = 'ltk/permohonan?'
  if (q)      url += `q=${encodeURIComponent(q)}&`
  if (status) url += `status=${status}&`

  try {
    const res  = await apiGet(url)
    const rows = res.data?.items || []
    if (!rows.length) { el.innerHTML = emptyState('Tidak ada permohonan ditemukan.'); return }
    el.innerHTML = `<table class="table table-hover" style="font-size:13px">
      <thead><tr><th>Kode</th><th>Judul</th><th>Pengusul / Unit</th><th>Antrian</th><th>PIC</th><th>Status</th><th>SLA</th><th>Tanggal</th><th></th></tr></thead>
      <tbody>${rows.map(p => `
        <tr>
          <td>
            <strong>${esc(p.kode)}</strong><br>
            ${p.sumber_permohonan === 'tu_input'
              ? `<span style="font-size:10px;background:#dbeafe;color:#1d4ed8;padding:1px 6px;border-radius:8px;font-weight:600">SRIKANDI</span>`
              : `<span style="font-size:10px;background:#f0fdf4;color:#15803d;padding:1px 6px;border-radius:8px;font-weight:600">Klien</span>`}
          </td>
          <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(p.judul)}</td>
          <td>
            ${esc(p.pengusul_nama || p.klien_nama || '-')}
            ${p.nodin_srikandi ? `<br><small style="color:#3b82f6;font-weight:600">📄 ${esc(p.nodin_srikandi)}</small>` : ''}
            <br><small style="color:#6b7280">${esc(p.unit_kerja||'-')}</small>
          </td>
          <td><span class="status-badge ${p.jenis_antrian==='prioritas'?'status-terlambat':'status-draft'}">${p.jenis_antrian||'-'}</span></td>
          <td>${esc(p.pic_nama||'—')}</td>
          <td>${ltkStatusBadge(p.status)}</td>
          <td>${p.tgl_deadline ? `<small>${p.tgl_deadline}</small>${p.is_sla_breach?'<span class="status-badge status-terlambat" style="font-size:9px;margin-left:4px">BREACH</span>':''}` : '—'}</td>
          <td><small>${fmtDate(p.created_at)}</small></td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn btn-sm btn-primary" onclick="ltkShowTimeline(${p.id})" title="Timeline">📋</button>
              ${p.status==='menunggu_verifikasi_tu' ? `<button class="btn btn-sm btn-success" onclick="ltkShowVerifikasi(${p.id},'${esc(p.kode)}')">Verifikasi</button>` : ''}
              ${p.status==='masuk_antrian' ? `<button class="btn btn-sm btn-warning" onclick="ltkShowTugaskan(${p.id},'${esc(p.kode)}')">Tugaskan</button>` : ''}
            </div>
          </td>
        </tr>`).join('')}
      </tbody>
    </table>`
  } catch(e) {
    el.innerHTML = `<div class="alert alert-danger">${e.message}</div>`
  }
}

// ──────────────────────────────────────────────────────────────
// PERMOHONAN KLIEN — alias ke halaman tunggal ltk_dashboard_klien
// (ltk_permohonan_klien dihapus dari sidebar, tapi route tetap ada
//  sebagai fallback agar tidak ada halaman kosong)
// ──────────────────────────────────────────────────────────────
function renderLtkPermohonanKlien() {
  // Arahkan ke halaman tunggal klien (dashboard + daftar pengajuan)
  navigateTo('ltk_dashboard_klien')
}

// ──────────────────────────────────────────────────────────────
// ANTRIAN (TU) — dual-column: Regular (FIFO kiri) | Prioritas (kanan)
// ──────────────────────────────────────────────────────────────
async function renderLtkAntrian() {
  const content = document.getElementById('mainContent')
  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
      <div style="font-size:13px;color:#6b7280">Antrian dikelola berdasarkan jenis — Regular (FIFO) dan Prioritas.</div>
      <button class="btn btn-sm btn-secondary" onclick="renderLtkAntrian()">↻ Refresh</button>
    </div>
    <div id="ltkAntrianDual" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="loading-page"><div class="spinner-lg"></div></div>
    </div>`

  try {
    const res  = await apiGet('ltk/antrian')
    const rows = res.data?.items || []
    const el   = document.getElementById('ltkAntrianDual')

    const regular   = rows.filter(r => r.jenis_antrian !== 'prioritas')
    const prioritas = rows.filter(r => r.jenis_antrian === 'prioritas')

    el.innerHTML = `
      <!-- Kolom Kiri: Regular FIFO -->
      <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
          <div style="width:10px;height:10px;border-radius:50%;background:#6366f1;flex-shrink:0"></div>
          <span style="font-weight:700;font-size:14px;color:#374151">Regular</span>
          <span style="background:#ede9fe;color:#6d28d9;font-size:11px;font-weight:700;border-radius:99px;padding:2px 8px">${regular.length} antrian</span>
          <span style="font-size:11px;color:#9ca3af;margin-left:auto">First In First Out</span>
        </div>
        ${ltkRenderAntrianKolom(regular, 'regular')}
      </div>

      <!-- Kolom Kanan: Prioritas -->
      <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
          <div style="width:10px;height:10px;border-radius:50%;background:#f59e0b;flex-shrink:0"></div>
          <span style="font-weight:700;font-size:14px;color:#374151">Prioritas</span>
          <span style="background:#fef3c7;color:#b45309;font-size:11px;font-weight:700;border-radius:99px;padding:2px 8px">${prioritas.length} antrian</span>
          <span style="font-size:11px;color:#9ca3af;margin-left:auto">Didahulukan</span>
        </div>
        ${ltkRenderAntrianKolom(prioritas, 'prioritas')}
      </div>`

  } catch(e) {
    document.getElementById('ltkAntrianDual').innerHTML = `<div class="alert alert-danger">${e.message}</div>`
  }
}

function ltkRenderAntrianKolom(rows, jenis) {
  const borderColor = jenis === 'prioritas' ? '#fde68a' : '#e0e7ff'
  const badgeCls    = jenis === 'prioritas' ? 'status-terlambat' : 'status-draft'

  if (!rows.length) {
    return `<div style="border:2px dashed ${borderColor};border-radius:10px;padding:24px;text-align:center;color:#9ca3af;font-size:13px">
      Tidak ada permohonan dalam antrian ${jenis}.
    </div>`
  }

  return `<div style="display:grid;gap:10px">
    ${rows.map((p, i) => `
      <div style="border:1.5px solid ${borderColor};border-radius:10px;padding:14px 16px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.04);position:relative">
        <!-- Nomor urut antrian -->
        <div style="position:absolute;top:-10px;left:14px;background:${jenis==='prioritas'?'#f59e0b':'#6366f1'};color:#fff;font-size:10px;font-weight:700;border-radius:99px;padding:2px 8px">#${i+1}</div>

        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-top:4px">
          <div style="flex:1;min-width:0">
            <div style="font-weight:700;font-size:13px;color:#1f2937">${esc(p.kode)}</div>
            <div style="font-size:12px;color:#374151;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(p.judul)}</div>
            <div style="font-size:11px;color:#6b7280;margin-top:3px">
              👤 ${esc(p.klien_nama||'—')} &nbsp;·&nbsp; 📅 ${fmtDate(p.created_at)}
            </div>
          </div>
          <!-- Toggle ke kolom lain -->
          <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end">
            <select class="form-control" style="width:105px;font-size:11px;padding:3px 6px" onchange="ltkSetPrioritas(${p.id},this.value)">
              <option value="regular"   ${p.jenis_antrian!=='prioritas'?'selected':''}>Regular</option>
              <option value="prioritas" ${p.jenis_antrian==='prioritas'?'selected':''}>Prioritas</option>
            </select>
            <button class="btn btn-sm btn-primary" style="font-size:11px;padding:3px 10px;white-space:nowrap" onclick="ltkShowTugaskan(${p.id},'${esc(p.kode)}','${p.jenis_antrian||'regular'}')">Tugaskan PIC</button>
          </div>
        </div>
      </div>`).join('')}
  </div>`
}

// ──────────────────────────────────────────────────────────────
// TUGAS PIC
// ──────────────────────────────────────────────────────────────
async function renderLtkTugasPic() {
  const content = document.getElementById('mainContent')
  content.innerHTML = `<div id="ltkTugasTable"><div class="loading-page"><div class="spinner-lg"></div></div></div>`
  try {
    const res  = await apiGet('ltk/pic/tugas')
    const rows = res.data?.items || []
    const el   = document.getElementById('ltkTugasTable')
    if (!rows.length) { el.innerHTML = emptyState('Tidak ada tugas aktif.'); return }
    el.innerHTML = `<table class="table table-hover" style="font-size:13px">
      <thead><tr><th>Kode</th><th>Judul</th><th>Prioritas</th><th>Status</th><th>SLA</th><th>Deadline</th><th>Aksi</th></tr></thead>
      <tbody>${rows.map(p => `
        <tr>
          <td><strong>${esc(p.kode)}</strong></td>
          <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(p.judul)}</td>
          <td><span class="status-badge ${p.jenis_antrian==='prioritas'?'status-terlambat':'status-draft'}">${p.jenis_antrian}</span></td>
          <td>${ltkStatusBadge(p.status)}</td>
          <td>${p.sla_info ? ltkSlaChip(p.sla_info) : '—'}</td>
          <td>${p.tgl_deadline || '—'}</td>
          <td>
            <div style="display:flex;gap:4px;flex-wrap:wrap">
              <button class="btn btn-sm btn-primary" onclick="ltkShowTimeline(${p.id})" title="Timeline">📋</button>
              <button class="btn btn-sm btn-secondary" onclick="ltkShowDokumen(${p.id},'${esc(p.kode)}')" title="Dokumen Output">📁</button>
              ${p.status==='ditugaskan_pic'
                ? `<button class="btn btn-sm btn-success" onclick="ltkTerimaTugas(${p.id},'${esc(p.kode)}')">Terima</button>`
                : ''}
              ${p.status==='diterima_pic'
                ? `<button class="btn btn-sm btn-warning" onclick="ltkMulaiAsesmen(${p.id})">Mulai Asesmen</button>`
                : ''}
              ${p.status==='asesmen_awal'
                ? `<button class="btn btn-sm btn-warning" onclick="ltkShowAsesmen(${p.id},'${esc(p.kode)}')">Asesmen</button>`
                : ''}
              ${['tidak_perlu_tindak_lanjut','perlu_tindak_lanjut'].includes(p.status)
                ? `<button class="btn btn-sm btn-primary" onclick="ltkShowSubmitPolicyBrief(${p.id},'${esc(p.kode)}')">Policy Brief</button>`
                : ''}
              ${p.status==='kajian_lanjutan'
                ? `<button class="btn btn-sm btn-success" onclick="ltkShowSubmitPolicyPaper(${p.id},'${esc(p.kode)}')">Policy Paper</button>`
                : ''}
              ${['penyusunan_policy_brief','penyusunan_policy_paper','kajian_lanjutan'].includes(p.status)
                ? `<button class="btn btn-sm btn-warning" onclick="ltkShowGenerateNotaDinas(${p.id},'${esc(p.kode)}','${p.status}')">Generate ND</button>`
                : ''}
              ${['penyusunan_nota_dinas_tanggapan','nota_dinas_hasil_telaah_akhir'].includes(p.status)
                ? `<button class="btn btn-sm btn-success" onclick="ltkSelesaikanPermohonan(${p.id},'${esc(p.kode)}')">✓ Selesaikan</button>`
                : ''}
            </div>
          </td>
        </tr>`).join('')}
      </tbody>
    </table>`
  } catch(e) {
    document.getElementById('ltkTugasTable').innerHTML = `<div class="alert alert-danger">${e.message}</div>`
  }
}

// ──────────────────────────────────────────────────────────────
// FORM BUILDER (TU)
// ──────────────────────────────────────────────────────────────
async function renderLtkFormBuilder() {
  const content = document.getElementById('mainContent')
  content.innerHTML = `
    <div class="page-header" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center">
      <span style="font-size:13px;color:#6b7280">Kelola template formulir pengajuan telaah</span>
      <button class="btn btn-primary" onclick="ltkShowFormTemplateModal()">+ Template Baru</button>
    </div>
    <div id="ltkFormBuilderList"><div class="loading-page"><div class="spinner-lg"></div></div></div>
  `
  try {
    const res  = await apiGet('ltk/form-template')
    const rows = res.data?.items || []
    const el   = document.getElementById('ltkFormBuilderList')
    if (!rows.length) { el.innerHTML = emptyState('Belum ada template form.'); return }
    el.innerHTML = `<div style="display:grid;gap:12px">${rows.map(t => `
      <div class="card" style="padding:16px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
          <div>
            <div style="font-weight:700;font-size:14px">${esc(t.nama)}</div>
            <div style="font-size:12px;color:#6b7280;margin-top:4px">${esc(t.deskripsi||'—')} · ${t.jumlah_field||0} field</div>
          </div>
          <div style="display:flex;gap:6px">
            <span class="status-badge ${t.is_aktif?'status-selesai':'status-draft'}">${t.is_aktif?'Aktif':'Nonaktif'}</span>
            <button class="btn btn-sm btn-primary" onclick="ltkShowFieldEditor(${t.id},'${esc(t.nama)}')">Edit Field</button>
          </div>
        </div>
      </div>`).join('')}</div>`
  } catch(e) {
    document.getElementById('ltkFormBuilderList').innerHTML = `<div class="alert alert-danger">${e.message}</div>`
  }
}

// ──────────────────────────────────────────────────────────────
// LAPORAN (TU/Admin) — tabel pilihan
// ──────────────────────────────────────────────────────────────
async function renderLtkLaporan() {
  const content = document.getElementById('mainContent')
  const thn     = new Date().getFullYear()
  content.innerHTML = `
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap">
      <label style="font-size:13px;font-weight:600">Periode:</label>
      <input type="date" id="ltkLapDari"   class="form-control" style="width:150px;font-size:13px" value="${thn}-01-01">
      <input type="date" id="ltkLapSampai" class="form-control" style="width:150px;font-size:13px" value="${thn}-12-31">
      <select id="ltkLapJenis" class="form-control" style="width:220px;font-size:13px">
        <option value="permohonan">Semua Permohonan</option>
        <option value="per-unit">Per Unit Kerja</option>
        <option value="per-pic">Per PIC</option>
        <option value="sla">Laporan SLA</option>
        <option value="prioritas">Prioritas Antrian</option>
        <option value="output-jenis">Jenis Output</option>
        <option value="statistik-policy-brief">Policy Brief</option>
        <option value="statistik-policy-paper">Policy Paper</option>
        <option value="kinerja-pic">Kinerja PIC</option>
        <option value="rata-waktu">Rata-rata Waktu</option>
      </select>
      <button class="btn btn-primary" onclick="ltkLoadLaporan()">🔍 Tampilkan</button>
      <button class="btn btn-secondary" onclick="ltkExportCsv()" title="Export ke CSV">⬇ CSV</button>
      <button class="btn btn-secondary" onclick="ltkPrintLaporan()" title="Print">🖨 Print</button>
    </div>
    <div id="ltkLaporanResult"></div>
  `
  ltkLoadLaporan()
}

// ──────────────────────────────────────────────────────────────
// LAPORAN — Load, Export CSV & Print
// _ltkLaporanCache: cache data terakhir untuk CSV/Print export
// ──────────────────────────────────────────────────────────────
let _ltkLaporanCache = null

async function ltkLoadLaporan() {
  _ltkLaporanCache = null
  const dari   = document.getElementById('ltkLapDari')?.value   || ''
  const sampai = document.getElementById('ltkLapSampai')?.value || ''
  const jenis  = document.getElementById('ltkLapJenis')?.value  || 'permohonan'
  const el     = document.getElementById('ltkLaporanResult')
  if (!el) return

  el.innerHTML = '<div class="loading-page"><div class="spinner-lg"></div></div>'

  try {
    const res  = await apiGet(`ltk/laporan/${jenis}?dari=${dari}&sampai=${sampai}`)
    const data = res.data
    _ltkLaporanCache = { data, jenis, dari, sampai }
    const rows = data.items || []

    if (jenis === 'rata-waktu') {
      el.innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px">
          ${ltkStatCard('Total Selesai',   data.total_selesai,        '#3b82f6','✅')}
          ${ltkStatCard('Rata-rata (Hari)',data.rata_hari_kalender,   '#6366f1','📅')}
          ${ltkStatCard('Skenario A',      data.total_skenario_a,     '#06b6d4','🔵')}
          ${ltkStatCard('Skenario B',      data.total_skenario_b,     '#8b5cf6','🟣')}
          ${ltkStatCard('Rata-rata A (HK)',data.rata_hari_skenario_a, '#10b981','📊')}
          ${ltkStatCard('Rata-rata B (HK)',data.rata_hari_skenario_b, '#f59e0b','📊')}
        </div>`
      return
    }

    if (!rows.length) { el.innerHTML = emptyState('Tidak ada data untuk periode ini.'); return }

    const keys   = Object.keys(rows[0])
    el.innerHTML = `
      <div style="margin-bottom:8px;font-size:12px;color:#6b7280">${(data.total || rows.length)} data ditemukan</div>
      <div style="overflow-x:auto">
        <table class="table" style="font-size:12px" id="ltkLaporanTable">
          <thead><tr>${keys.map(k => `<th>${k.replace(/_/g,' ')}</th>`).join('')}</tr></thead>
          <tbody>${rows.map(r => `<tr>${keys.map(k => `<td>${r[k] ?? '—'}</td>`).join('')}</tr>`).join('')}</tbody>
        </table>
      </div>`
  } catch(e) {
    el.innerHTML = `<div class="alert alert-danger">${e.message}</div>`
  }
}

function ltkExportCsv() {
  if (!_ltkLaporanCache) { showToast('Tampilkan laporan terlebih dahulu.', 'error'); return }
  const { data, jenis, dari, sampai } = _ltkLaporanCache
  const rows = data.items || []
  if (!rows.length) { showToast('Tidak ada data untuk diekspor.', 'error'); return }

  const keys = Object.keys(rows[0])
  const csvRows = [
    keys.join(','),  // header
    ...rows.map(r => keys.map(k => {
      const val = r[k] ?? ''
      // Escape nilai yang mengandung koma, kutip, atau newline
      return /[,"\n\r]/.test(String(val))
        ? `"${String(val).replace(/"/g, '""')}"`
        : String(val)
    }).join(','))
  ]

  const csvStr = '\uFEFF' + csvRows.join('\n') // BOM untuk Excel
  const blob   = new Blob([csvStr], { type: 'text/csv;charset=utf-8' })
  const url    = URL.createObjectURL(blob)
  const a      = document.createElement('a')
  a.href       = url
  a.download   = `ltk_laporan_${jenis}_${dari}_${sampai}.csv`
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
  showToast('File CSV berhasil diunduh.')
}

function ltkPrintLaporan() {
  const table = document.getElementById('ltkLaporanTable')
  if (!table) { showToast('Tampilkan laporan terlebih dahulu.', 'error'); return }

  const dari   = document.getElementById('ltkLapDari')?.value   || ''
  const sampai = document.getElementById('ltkLapSampai')?.value || ''
  const jenis  = (document.getElementById('ltkLapJenis')?.selectedOptions[0]?.text) || ''

  const win = window.open('', '_blank', 'width=900,height=700')
  win.document.write(`<!DOCTYPE html><html><head>
    <meta charset="utf-8">
    <title>Laporan Telaah — ${jenis}</title>
    <style>
      body { font-family: Arial, sans-serif; font-size: 12px; color: #1f2328; margin: 20px }
      h2   { font-size: 15px; margin-bottom: 4px }
      p    { margin: 0 0 14px; color: #6b7280; font-size: 11px }
      table { border-collapse: collapse; width: 100% }
      th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left }
      th { background: #f3f4f6; font-weight: 700 }
      tr:nth-child(even) { background: #f9fafb }
    </style>
  </head><body>
    <h2>Laporan Telaah: ${jenis}</h2>
    <p>Periode: ${dari} s/d ${sampai} &nbsp;·&nbsp; Dicetak: ${new Date().toLocaleString('id-ID')}</p>
    ${table.outerHTML}
  </body></html>`)
  win.document.close()
  win.focus()
  setTimeout(() => { win.print(); win.close() }, 500)
}

// ──────────────────────────────────────────────────────────────
// TIMELINE Modal
// ──────────────────────────────────────────────────────────────
async function ltkShowTimeline(id) {
  openModal('Timeline Permohonan', '<div class="spinner-sm"></div>')
  try {
    const res      = await apiGet(`ltk/permohonan/${id}/timeline`)
    const d        = res.data
    const timeline = d.timeline || []
    const body = `
      <div style="margin-bottom:12px">
        <span style="font-weight:700">${esc(d.kode)}</span>
        &nbsp;${ltkStatusBadge(d.status)}
      </div>
      <div style="position:relative;padding-left:24px">
        ${timeline.map((t, i) => `
          <div style="position:relative;margin-bottom:16px">
            <div style="position:absolute;left:-20px;top:4px;width:10px;height:10px;border-radius:50%;background:${i===timeline.length-1?'#3b82f6':'#10b981'}"></div>
            ${i < timeline.length-1 ? '<div style="position:absolute;left:-16px;top:14px;bottom:-16px;width:2px;background:#e5e7eb"></div>' : ''}
            <div style="font-weight:600;font-size:13px">${esc(t.label_aktivitas)}</div>
            <div style="font-size:11px;color:#6b7280;margin-top:2px">
              ${t.created_at} &nbsp;·&nbsp; ${esc(t.nama_user || 'Sistem')}
            </div>
            ${t.catatan ? `<div style="font-size:12px;color:#374151;margin-top:4px;background:#f9fafb;padding:6px 10px;border-radius:6px;border-left:3px solid #e5e7eb">${esc(t.catatan)}</div>` : ''}
          </div>`).join('')}
        ${!timeline.length ? '<div style="color:#6b7280;font-size:13px">Belum ada aktivitas.</div>' : ''}
      </div>`
    openModal(`Timeline — ${esc(d.kode)}`, body)
  } catch(e) {
    openModal('Error', `<div class="alert alert-danger">${e.message}</div>`)
  }
}

// ──────────────────────────────────────────────────────────────
// VERIFIKASI (TU)
// ──────────────────────────────────────────────────────────────
function ltkShowVerifikasi(id, kode) {
  const body = `
    <div style="font-size:13px;margin-bottom:14px">Permohonan: <strong>${esc(kode)}</strong></div>
    <div class="form-group">
      <label class="form-label">Nomor Nota Dinas Masuk <span class="required">*</span></label>
      <input type="text" class="form-control" id="vNomor" placeholder="Contoh: B/001/ANRI/2025" required>
    </div>
    <div class="form-group">
      <label class="form-label">Tanggal Nota Dinas <span class="required">*</span></label>
      <input type="date" class="form-control" id="vTanggal" required>
    </div>
    <div class="form-group">
      <label class="form-label">Catatan TU</label>
      <textarea class="form-control" id="vCatatan" rows="3" placeholder="Opsional"></textarea>
    </div>`
  openModal('Verifikasi Administrasi', body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Verifikasi', cls: 'btn-success', action: async () => {
      const nomor   = document.getElementById('vNomor').value.trim()
      const tanggal = document.getElementById('vTanggal').value
      const catatan = document.getElementById('vCatatan').value.trim()
      if (!nomor || !tanggal) { showToast('Nomor dan Tanggal ND wajib diisi.', 'error'); return }
      try {
        await apiPost(`ltk/permohonan/${id}/verifikasi`, {
          nomor_nota_dinas_masuk: nomor,
          tgl_nota_dinas_masuk:   tanggal,
          catatan_tu:             catatan,
        })
        closeModal()
        showToast('Permohonan berhasil diverifikasi dan masuk antrian.')
        ltkLoadPermohonanList()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ──────────────────────────────────────────────────────────────
// INPUT DARI SRIKANDI (TU) — input permohonan langsung berdasarkan
// NODIN yang masuk melalui SRIKANDI, tanpa alur klien
// ──────────────────────────────────────────────────────────────
function ltkShowTuInputForm() {
  const today = new Date().toISOString().split('T')[0]
  openModal('Input Permohonan dari SRIKANDI', `
    <div style="background:#dbeafe;border:1px solid #93c5fd;border-radius:8px;padding:10px 14px;font-size:12.5px;color:#1e3a8a;margin-bottom:16px">
      📥 Permohonan ini diinput langsung oleh TU berdasarkan NODIN yang masuk melalui <strong>SRIKANDI</strong>.
      Akan langsung masuk antrian telaah.
    </div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Nomor NODIN SRIKANDI <span class="required">*</span></label>
        <input type="text" class="form-control" id="tiNodin" placeholder="Contoh: B/001/ANRI/2025">
      </div>
      <div class="form-group">
        <label class="form-label">Tanggal NODIN <span class="required">*</span></label>
        <input type="date" class="form-control" id="tiTanggal" value="${today}">
      </div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Unit Kerja Pengusul <span class="required">*</span></label>
        <input type="text" class="form-control" id="tiUnit" placeholder="Contoh: Direktorat Kearsipan Pusat">
      </div>
      <div class="form-group">
        <label class="form-label">Nama Pejabat Pengusul <span class="required">*</span></label>
        <input type="text" class="form-control" id="tiNama" placeholder="Contoh: Drs. Ahmad Fauzi, M.Si">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Perihal / Judul Telaah <span class="required">*</span></label>
      <input type="text" class="form-control" id="tiJudul" placeholder="Perihal permohonan telaah">
    </div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Jenis Antrian</label>
        <select class="form-control" id="tiJenis">
          <option value="regular">Regular</option>
          <option value="prioritas">Prioritas</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Catatan TU</label>
        <input type="text" class="form-control" id="tiCatatan" placeholder="Opsional">
      </div>
    </div>
  `, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: '📥 Simpan & Masuk Antrian', cls: 'btn-primary', action: async () => {
      const nodin   = document.getElementById('tiNodin').value.trim()
      const tanggal = document.getElementById('tiTanggal').value
      const unit    = document.getElementById('tiUnit').value.trim()
      const nama    = document.getElementById('tiNama').value.trim()
      const judul   = document.getElementById('tiJudul').value.trim()
      const jenis   = document.getElementById('tiJenis').value
      const catatan = document.getElementById('tiCatatan').value.trim()

      if (!nodin || !tanggal || !unit || !nama || !judul) {
        showToast('Semua field bertanda * wajib diisi.', 'error'); return
      }
      try {
        const res = await apiPost('ltk/permohonan/tu-input', {
          nodin_srikandi:       nodin,
          tgl_nota_dinas_masuk: tanggal,
          unit_kerja:           unit,
          nama_pengusul:        nama,
          judul,
          jenis_antrian:        jenis,
          catatan_tu:           catatan || null,
        })
        closeModal()
        showToast(`Permohonan ${res.data?.kode || ''} berhasil diinput dan masuk antrian.`, 'success')
        ltkLoadPermohonanList()
      } catch(e) { showToast(e.message || 'Gagal menyimpan.', 'error') }
    }},
  ])
}

// ──────────────────────────────────────────────────────────────
// TUGASKAN PIC (TU) — dropdown menampilkan semua role yang bisa jadi PIC
// ──────────────────────────────────────────────────────────────
const LTK_ROLE_LABEL = {
  pic_telaah:        'PIC Telaah',
  direktur:          'Direktur',
  ketua_tim:         'Ketua Tim',
  anggota_tim:       'Anggota Tim',
  perencana_anggaran:'Perencana Anggaran',
}

async function ltkShowTugaskan(id, kode, currentJenis = 'regular') {
  openModal('Tugaskan PIC', '<div class="spinner-sm"></div>')
  try {
    const picRes = await apiGet('ltk/pic/list')
    const pics   = picRes.data?.items || []

    // Kelompokkan per role untuk optgroup
    const grouped = {}
    for (const p of pics) {
      const grp = LTK_ROLE_LABEL[p.role] || p.role
      if (!grouped[grp]) grouped[grp] = []
      grouped[grp].push(p)
    }

    const optgroupHtml = Object.entries(grouped).map(([grp, list]) =>
      `<optgroup label="${esc(grp)}">${list.map(p =>
        `<option value="${p.id}">${esc(p.nama)}${p.jabatan ? ' — ' + esc(p.jabatan) : ''}</option>`
      ).join('')}</optgroup>`
    ).join('')

    const body = `
      <div style="font-size:13px;margin-bottom:14px">Permohonan: <strong>${esc(kode)}</strong></div>
      <div class="form-group">
        <label class="form-label">PIC Telaah <span class="required">*</span></label>
        <select class="form-control" id="tugaskanPic">
          <option value="">— Pilih PIC —</option>
          ${optgroupHtml}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Jenis Antrian</label>
        <select class="form-control" id="tugaskanPrioritas">
          <option value="regular"   ${currentJenis !== 'prioritas' ? 'selected' : ''}>Regular</option>
          <option value="prioritas" ${currentJenis === 'prioritas' ? 'selected' : ''}>Prioritas</option>
        </select>
      </div>`
    openModal(`Tugaskan PIC — ${esc(kode)}`, body, [
      { label: 'Batal', cls: 'btn-secondary', action: closeModal },
      { label: 'Tugaskan', cls: 'btn-primary', action: async () => {
        const picId = document.getElementById('tugaskanPic').value
        const prio  = document.getElementById('tugaskanPrioritas').value
        if (!picId) { showToast('Pilih PIC terlebih dahulu.', 'error'); return }
        try {
          await apiPost(`ltk/antrian/${id}/tugaskan`, { pic_user_id: parseInt(picId), jenis_antrian: prio })
          closeModal()
          showToast('PIC berhasil ditugaskan.')
          if (currentPage === 'ltk_antrian') renderLtkAntrian()
          else ltkLoadPermohonanList()
        } catch(e) { showToast(e.message, 'error') }
      }},
    ])
  } catch(e) {
    openModal('Error', `<div class="alert alert-danger">${e.message}</div>`)
  }
}

// ──────────────────────────────────────────────────────────────
// SET PRIORITAS (TU — inline dari dropdown antrian)
// Setelah berhasil, refresh tampilan dual-column
// ──────────────────────────────────────────────────────────────
async function ltkSetPrioritas(id, jenis) {
  try {
    await apiPut(`ltk/antrian/${id}/prioritas`, { jenis_antrian: jenis })
    showToast(`Prioritas diubah ke "${jenis}".`)
    // Refresh kolom setelah ubah prioritas
    if (currentPage === 'ltk_antrian') renderLtkAntrian()
  } catch(e) { showToast(e.message, 'error') }
}

// ──────────────────────────────────────────────────────────────
// TERIMA TUGAS (PIC)
// ──────────────────────────────────────────────────────────────
async function ltkTerimaTugas(id, kode) {
  openModal('Terima Penugasan', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">📋</div>
      <div style="font-size:14px;font-weight:700;color:#1f2328;margin-bottom:6px">Terima Penugasan?</div>
      <div style="font-size:13px;color:#6b7280">
        Terima penugasan untuk permohonan <strong>${esc(kode)}</strong>?<br>
        <span style="color:#f59e0b;font-weight:600">SLA akan mulai dihitung sejak saat ini.</span>
      </div>
    </div>`, [
    { label: 'Batal',         cls: 'btn-secondary', action: closeModal },
    { label: '✓ Terima Tugas', cls: 'btn-success',  action: async () => {
      try {
        const res = await apiPost(`ltk/pic/tugas/${id}/terima`, {})
        closeModal()
        showToast(`Penugasan diterima. Deadline: ${res.data?.tgl_deadline || '—'}`)
        renderLtkTugasPic()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ──────────────────────────────────────────────────────────────
// MULAI ASESMEN (PIC)
// ──────────────────────────────────────────────────────────────
async function ltkMulaiAsesmen(id) {
  try {
    await apiPost(`ltk/pic/tugas/${id}/mulai-asesmen`, {})
    showToast('Asesmen awal dimulai.')
    renderLtkTugasPic()
  } catch(e) { showToast(e.message, 'error') }
}

// ──────────────────────────────────────────────────────────────
// FORM ASESMEN (PIC)
// ──────────────────────────────────────────────────────────────
function ltkShowAsesmen(id, kode) {
  const body = `
    <div style="font-size:13px;margin-bottom:14px">Permohonan: <strong>${esc(kode)}</strong></div>
    <div class="form-group">
      <label class="form-label">Hasil Asesmen <span class="required">*</span></label>
      <div style="display:flex;gap:16px;margin-top:6px">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
          <input type="radio" name="skenario" value="A"> <strong>Skenario A</strong> — Tidak perlu tindak lanjut (SLA 6 HK)
        </label>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
          <input type="radio" name="skenario" value="B"> <strong>Skenario B</strong> — Perlu tindak lanjut (SLA 28 HK)
        </label>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Catatan Asesmen</label>
      <textarea class="form-control" id="asesCatatan" rows="4" placeholder="Uraikan hasil asesmen awal..."></textarea>
    </div>`
  openModal(`Asesmen Awal — ${esc(kode)}`, body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Simpan Asesmen', cls: 'btn-primary', action: async () => {
      const skenario = document.querySelector('input[name="skenario"]:checked')?.value
      const catatan  = document.getElementById('asesCatatan').value.trim()
      if (!skenario) { showToast('Pilih skenario terlebih dahulu.', 'error'); return }
      try {
        await apiPost('ltk/asesmen', { permohonan_id: id, skenario, catatan_asesmen: catatan })
        closeModal()
        showToast(`Asesmen Skenario ${skenario} berhasil disimpan.`)
        renderLtkTugasPic()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ──────────────────────────────────────────────────────────────
// KONFIRMASI SRIKANDI (Klien)
// ──────────────────────────────────────────────────────────────
async function ltkKonfirmasiSrikandi(id, kode) {
  openModal('Konfirmasi Pengiriman SRIKANDI', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">📨</div>
      <div style="font-size:14px;font-weight:700;color:#1f2328;margin-bottom:6px">Konfirmasi Pengiriman SRIKANDI</div>
      <div style="font-size:13px;color:#6b7280">
        Konfirmasi bahwa Nota Dinas <strong>${esc(kode)}</strong><br>
        sudah dikirimkan melalui aplikasi <strong>SRIKANDI</strong>?<br><br>
        Setelah dikonfirmasi, permohonan akan masuk ke<br>antrian verifikasi TU.
      </div>
    </div>`, [
    { label: 'Batal',       cls: 'btn-secondary', action: closeModal },
    { label: '✓ Konfirmasi', cls: 'btn-success',  action: async () => {
      try {
        await apiPost(`ltk/permohonan/${id}/konfirmasi-srikandi`, {})
        closeModal()
        showToast('Konfirmasi berhasil. Permohonan masuk antrian verifikasi TU.')
        ltkLoadKlienPage()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ──────────────────────────────────────────────────────────────
// FORM PENGAJUAN BARU (Klien) — placeholder, form template dinamis
// ──────────────────────────────────────────────────────────────
async function ltkShowFormPengajuan() {
  openModal('Ajukan Telaah Baru', '<div class="spinner-sm"></div>')
  try {
    const res       = await apiGet('ltk/form-template/aktif')
    const templates = res.data?.items || []
    if (!templates.length) {
      openModal('Info', '<p style="padding:8px">Belum ada template form aktif. Hubungi TU untuk mengkonfigurasi form.</p>')
      return
    }
    // Jika hanya satu template, langsung buka form-nya
    if (templates.length === 1) {
      ltkShowFormIsian(templates[0].id, templates[0].nama)
    } else {
      const body = `
        <div style="font-size:13px;margin-bottom:14px">Pilih jenis pengajuan:</div>
        ${templates.map(t => `
          <div class="card" style="padding:14px;cursor:pointer;margin-bottom:8px" onclick="closeModal();ltkShowFormIsian(${t.id},'${esc(t.nama)}')">
            <div style="font-weight:700">${esc(t.nama)}</div>
            <div style="font-size:12px;color:#6b7280;margin-top:4px">${esc(t.deskripsi||'')}</div>
          </div>`).join('')}`
      openModal('Pilih Jenis Telaah', body)
    }
  } catch(e) { openModal('Error', `<div class="alert alert-danger">${e.message}</div>`) }
}

async function ltkShowFormIsian(templateId, templateNama) {
  openModal(`Pengajuan Telaah — ${esc(templateNama)}`, '<div class="spinner-sm"></div>')
  try {
    const res    = await apiGet(`ltk/form-template/${templateId}`)
    const fields = res.data?.fields || []
    const TIPE_LABEL = { text:'Teks', textarea:'Teks Panjang', number:'Angka', date:'Tanggal', dropdown:'Pilihan', radio:'Radio', checkbox:'Checkbox', link_cloud:'Link Cloud (OneDrive/Google Drive)' }
    const body = `
      <div class="form-group">
        <label class="form-label">Judul / Perihal Telaah <span class="required">*</span></label>
        <input type="text" class="form-control" id="ltkJudul" placeholder="Masukkan judul telaah" required>
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi Singkat</label>
        <textarea class="form-control" id="ltkDeskripsi" rows="2" placeholder="Opsional"></textarea>
      </div>
      <hr style="margin:14px 0">
      <div id="ltkDynamicFields">
        ${fields.map(f => ltkRenderField(f)).join('')}
      </div>`
    openModal(`Pengajuan — ${esc(templateNama)}`, body, [
      { label: 'Batal', cls: 'btn-secondary', action: closeModal },
      { label: 'Submit Permohonan', cls: 'btn-primary', action: async () => {
        const judul = document.getElementById('ltkJudul')?.value.trim()
        if (!judul) { showToast('Judul wajib diisi.', 'error'); return }
        const jawaban = {}
        for (const f of fields) {
          const el = document.getElementById(`ltkField_${f.id}`)
          if (!el) continue
          if (f.tipe === 'checkbox') {
            const checked = [...document.querySelectorAll(`input[name="ltkField_${f.id}"]:checked`)].map(c => c.value)
            jawaban[f.id] = checked
          } else {
            jawaban[f.id] = el.value
          }
        }
        try {
          const res = await apiPost('ltk/permohonan', { judul, deskripsi: document.getElementById('ltkDeskripsi')?.value || '', form_template_id: templateId, jawaban })
          closeModal()
          const nd = res.data?.nd_warning ? ` (${res.data.nd_warning})` : ''
          showToast(`Permohonan ${res.data?.kode} berhasil disubmit.${nd}`)
          // Kembali ke halaman klien dan muat ulang data
          if (currentPage === 'ltk_dashboard_klien') ltkLoadKlienPage()
          else navigateTo('ltk_dashboard_klien')
        } catch(e) { showToast(e.message, 'error') }
      }},
    ])
  } catch(e) { openModal('Error', `<div class="alert alert-danger">${e.message}</div>`) }
}

function ltkRenderField(f) {
  const req = f.is_required ? '<span class="required">*</span>' : ''

  // Badge hint untuk field yang otomatis mengisi variabel Nota Dinas
  const ND_HINT = {
    nomor_nd_pengusul:      '📄 otomatis → <code>${nomor_naskah}</code> di Nota Dinas',
    jabatan_pejabat_pengusul: '📄 otomatis → <code>${jabatan_pengirim}</code> di Nota Dinas',
    nama_pejabat_pengusul:  '📄 otomatis → <code>${nama_pengirim}</code> di Nota Dinas',
  }
  const ndHint = ND_HINT[f.field_name]
    ? `<span style="font-size:10.5px;color:#1d4ed8;background:#eff6ff;border:1px solid #bfdbfe;
         border-radius:4px;padding:1px 7px;margin-left:6px;font-weight:400">${ND_HINT[f.field_name]}</span>`
    : ''

  let input = ''
  const opsi = f.opsi_pilihan ? (typeof f.opsi_pilihan === 'string' ? JSON.parse(f.opsi_pilihan) : f.opsi_pilihan) : []
  if (f.tipe === 'text')
    input = `<input type="text" class="form-control" id="ltkField_${f.id}" placeholder="${esc(f.placeholder||'')}">`
  else if (f.tipe === 'textarea')
    input = `<textarea class="form-control" id="ltkField_${f.id}" rows="3" placeholder="${esc(f.placeholder||'')}"></textarea>`
  else if (f.tipe === 'number')
    input = `<input type="number" class="form-control" id="ltkField_${f.id}" placeholder="${esc(f.placeholder||'')}">`
  else if (f.tipe === 'date')
    input = `<input type="date" class="form-control" id="ltkField_${f.id}">`
  else if (f.tipe === 'link_cloud')
    input = `<input type="url" class="form-control" id="ltkField_${f.id}"
      placeholder="https://drive.google.com/... atau https://onedrive.live.com/...">`
  else if (f.tipe === 'dropdown')
    input = `<select class="form-control" id="ltkField_${f.id}">
      <option value="">— Pilih —</option>
      ${opsi.map(o=>`<option value="${esc(o)}">${esc(o)}</option>`).join('')}
    </select>`
  else if (f.tipe === 'radio')
    input = opsi.map(o=>`
      <label style="display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:13px;cursor:pointer">
        <input type="radio" name="ltkField_${f.id}" id="ltkField_${f.id}" value="${esc(o)}"> ${esc(o)}
      </label>`).join('')
  else if (f.tipe === 'checkbox')
    input = opsi.map(o=>`
      <label style="display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:13px;cursor:pointer">
        <input type="checkbox" name="ltkField_${f.id}" value="${esc(o)}"> ${esc(o)}
      </label>`).join('')

  return `
    <div class="form-group">
      <label class="form-label">${esc(f.label)} ${req}${ndHint}</label>
      ${input}
    </div>`
}

// ──────────────────────────────────────────────────────────────
// FORM TEMPLATE MODAL (TU — buat template baru)
// ──────────────────────────────────────────────────────────────
function ltkShowFormTemplateModal() {
  const body = `
    <div class="form-group">
      <label class="form-label">Nama Template <span class="required">*</span></label>
      <input type="text" class="form-control" id="tmplNama" placeholder="Contoh: Formulir Telaah Kebijakan">
    </div>
    <div class="form-group">
      <label class="form-label">Deskripsi</label>
      <textarea class="form-control" id="tmplDeskripsi" rows="2" placeholder="Opsional"></textarea>
    </div>`
  openModal('Template Form Baru', body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Simpan', cls: 'btn-primary', action: async () => {
      const nama = document.getElementById('tmplNama').value.trim()
      if (!nama) { showToast('Nama template wajib diisi.', 'error'); return }
      try {
        await apiPost('ltk/form-template', { nama, deskripsi: document.getElementById('tmplDeskripsi').value || '' })
        closeModal()
        showToast('Template berhasil dibuat. Sekarang tambahkan field.')
        renderLtkFormBuilder()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ──────────────────────────────────────────────────────────────
// FIELD EDITOR (TU — tambah/lihat field dalam template)
// ──────────────────────────────────────────────────────────────
async function ltkShowFieldEditor(templateId, templateNama) {
  openModal(`Field Editor — ${esc(templateNama)}`, '<div class="spinner-sm"></div>')
  try {
    const res    = await apiGet(`ltk/form-template/${templateId}/fields`)
    const fields = res.data?.items || []
    const TIPE_OPTIONS = ['text','textarea','number','date','dropdown','radio','checkbox','link_cloud']
    const body = `
      <div style="margin-bottom:14px;display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:#6b7280">${fields.length} field · Drag untuk ubah urutan</span>
        <button class="btn btn-sm btn-primary" onclick="ltkShowAddFieldModal(${templateId})">+ Field Baru</button>
      </div>
      <div style="display:grid;gap:8px">
        ${fields.map(f => `
          <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;display:flex;justify-content:space-between;align-items:center">
            <div>
              <span style="font-weight:600;font-size:13px">${esc(f.label)}</span>
              <span style="font-size:11px;color:#6b7280;margin-left:8px">${f.tipe}</span>
              ${f.is_required ? '<span style="font-size:10px;background:#fee2e2;color:#b91c1c;border-radius:4px;padding:1px 5px;margin-left:4px">Wajib</span>' : ''}
            </div>
            <span style="font-size:11px;color:#9ca3af">Urutan: ${f.urutan}</span>
          </div>`).join('')}
        ${!fields.length ? '<div style="color:#6b7280;font-size:13px;text-align:center;padding:20px">Belum ada field. Klik "+ Field Baru".</div>' : ''}
      </div>`
    openModal(`Field Editor — ${esc(templateNama)}`, body, [{ label: 'Tutup', cls: 'btn-secondary', action: closeModal }])
  } catch(e) { openModal('Error', `<div class="alert alert-danger">${e.message}</div>`) }
}

function ltkShowAddFieldModal(templateId) {
  const TIPE_OPTIONS = ['text','textarea','number','date','dropdown','radio','checkbox','link_cloud']
  const body = `
    <div class="form-group">
      <label class="form-label">Label Field <span class="required">*</span></label>
      <input type="text" class="form-control" id="fldLabel" placeholder="Contoh: Nama Pengusul">
    </div>
    <div class="form-group">
      <label class="form-label">Nama Field (snake_case) <span class="required">*</span></label>
      <input type="text" class="form-control" id="fldName" placeholder="nama_pengusul">
    </div>
    <div class="form-group">
      <label class="form-label">Tipe <span class="required">*</span></label>
      <select class="form-control" id="fldTipe">
        ${TIPE_OPTIONS.map(t => `<option value="${t}">${t}</option>`).join('')}
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Placeholder</label>
      <input type="text" class="form-control" id="fldPlaceholder">
    </div>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
        <input type="checkbox" id="fldRequired"> Wajib diisi
      </label>
    </div>`
  openModal('Tambah Field Baru', body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Simpan Field', cls: 'btn-primary', action: async () => {
      const label = document.getElementById('fldLabel').value.trim()
      const name  = document.getElementById('fldName').value.trim()
      const tipe  = document.getElementById('fldTipe').value
      if (!label || !name) { showToast('Label dan nama field wajib diisi.', 'error'); return }
      try {
        await apiPost(`ltk/form-template/${templateId}/fields`, {
          label, field_name: name, tipe,
          placeholder: document.getElementById('fldPlaceholder').value || null,
          is_required:  document.getElementById('fldRequired').checked ? 1 : 0,
        })
        closeModal()
        showToast('Field berhasil ditambahkan.')
        renderLtkFormBuilder()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ══════════════════════════════════════════════════════════════
// PIC — SUBMIT POLICY BRIEF (link cloud)
// ══════════════════════════════════════════════════════════════
function ltkShowSubmitPolicyBrief(permohonanId, kode) {
  const body = `
    <div style="font-size:13px;margin-bottom:14px">Permohonan: <strong>${esc(kode)}</strong></div>
    <div class="alert" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;font-size:12px;color:#1e40af;margin-bottom:14px">
      Tempel link OneDrive / Google Drive ke dokumen <strong>Policy Brief</strong> yang telah Anda susun.
      Pastikan link dapat diakses oleh tim.
    </div>
    <div class="form-group">
      <label class="form-label">Link Cloud Policy Brief <span class="required">*</span></label>
      <input type="url" class="form-control" id="pbLink" placeholder="https://drive.google.com/... atau https://1drv.ms/...">
    </div>
    <div class="form-group">
      <label class="form-label">Keterangan (opsional)</label>
      <input type="text" class="form-control" id="pbKet" placeholder="Mis: Policy Brief versi final, rev. 2">
    </div>`
  openModal(`Submit Policy Brief — ${esc(kode)}`, body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Submit Policy Brief', cls: 'btn-primary', action: async () => {
      const link = document.getElementById('pbLink').value.trim()
      if (!link) { showToast('Link cloud wajib diisi.', 'error'); return }
      try {
        const res = await apiPost('ltk/dokumen/policy-brief', {
          permohonan_id:   permohonanId,
          link_cloud:      link,
          keterangan_link: document.getElementById('pbKet').value.trim() || null,
        })
        closeModal()
        showToast(res.message || 'Policy Brief berhasil disubmit.')
        renderLtkTugasPic()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ══════════════════════════════════════════════════════════════
// PIC — SUBMIT POLICY PAPER (link cloud)
// ══════════════════════════════════════════════════════════════
function ltkShowSubmitPolicyPaper(permohonanId, kode) {
  const body = `
    <div style="font-size:13px;margin-bottom:14px">Permohonan: <strong>${esc(kode)}</strong></div>
    <div class="alert" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;font-size:12px;color:#166534;margin-bottom:14px">
      Tempel link OneDrive / Google Drive ke dokumen <strong>Policy Paper</strong> (kajian lanjutan) yang telah selesai disusun.
    </div>
    <div class="form-group">
      <label class="form-label">Link Cloud Policy Paper <span class="required">*</span></label>
      <input type="url" class="form-control" id="ppLink" placeholder="https://drive.google.com/... atau https://1drv.ms/...">
    </div>
    <div class="form-group">
      <label class="form-label">Keterangan (opsional)</label>
      <input type="text" class="form-control" id="ppKet" placeholder="Mis: Policy Paper final setelah kajian lanjutan">
    </div>`
  openModal(`Submit Policy Paper — ${esc(kode)}`, body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Submit Policy Paper', cls: 'btn-success', action: async () => {
      const link = document.getElementById('ppLink').value.trim()
      if (!link) { showToast('Link cloud wajib diisi.', 'error'); return }
      try {
        const res = await apiPost('ltk/dokumen/policy-paper', {
          permohonan_id:   permohonanId,
          link_cloud:      link,
          keterangan_link: document.getElementById('ppKet').value.trim() || null,
        })
        closeModal()
        showToast(res.message || 'Policy Paper berhasil disubmit.')
        renderLtkTugasPic()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ══════════════════════════════════════════════════════════════
// PIC — GENERATE NOTA DINAS
// Variabel template: ${yth} ${dari} ${hal} ${tanggal} ${lampiran}
//                   ${jenis_dokumen} ${judul_dokumen} ${uraian_tambahan}
//                   ${jabatan_pengirim} ${nama_pengirim}
// Tambahan per tipe: ${batas_waktu} | ${rekomendasi}
// ══════════════════════════════════════════════════════════════
function ltkShowGenerateNotaDinas(permohonanId, kode, status) {
  const tipeMap = {
    penyusunan_policy_brief:           'nota_dinas_tanggapan',
    penyusunan_nota_dinas_tanggapan:   'nota_dinas_tanggapan',
    nota_dinas_permintaan_penyesuaian: 'nota_dinas_permintaan_penyesuaian',
    penyusunan_policy_paper:           'nota_dinas_hasil_telaah_akhir',
    nota_dinas_hasil_telaah_akhir:     'nota_dinas_hasil_telaah_akhir',
  }
  const defaultTipe = tipeMap[status] || 'nota_dinas_tanggapan'

  const tipeOptions = [
    { v: 'nota_dinas_tanggapan',              l: 'Nota Dinas Tanggapan (Skenario A)' },
    { v: 'nota_dinas_permintaan_penyesuaian', l: 'Nota Dinas Permintaan Penyesuaian (Skenario B Tahap 1)' },
    { v: 'nota_dinas_hasil_telaah_akhir',     l: 'Nota Dinas Hasil Telaah Akhir (Skenario B Tahap 2)' },
  ]

  // Tanggal hari ini dalam format Indonesia untuk default
  const hariIni = new Date()
  const bulanId = ['Januari','Februari','Maret','April','Mei','Juni',
                   'Juli','Agustus','September','Oktober','November','Desember']
  const tglDefault = `${hariIni.getDate()} ${bulanId[hariIni.getMonth()]} ${hariIni.getFullYear()}`

  const body = `
    <div style="font-size:13px;margin-bottom:12px">Permohonan: <strong>${esc(kode)}</strong></div>
    <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:9px 13px;font-size:12px;color:#854d0e;margin-bottom:14px">
      ⚠️ Pastikan template <strong>.docx</strong> sudah diupload oleh Admin di menu Pengaturan Telaah.
      Isi variabel sesuai isi dokumen yang akan digenerate.
    </div>

    <!-- Jenis ND -->
    <div class="form-group">
      <label class="form-label">Jenis Nota Dinas <span class="required">*</span></label>
      <select class="form-control" id="ndTipe" onchange="ltkToggleNdFields()">
        ${tipeOptions.map(o => `<option value="${o.v}" ${o.v === defaultTipe ? 'selected' : ''}>${o.l}</option>`).join('')}
      </select>
    </div>

    <hr style="margin:12px 0">
    <div style="font-size:12px;font-weight:700;color:#6b7280;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px">
      Header Surat
    </div>

    <!-- Kepada / Yth -->
    <div class="form-group">
      <label class="form-label">Kepada Yth. <span class="required">*</span>
        <span style="font-size:11px;color:#9ca3af;font-weight:400">— variabel: <code>\${yth}</code></span>
      </label>
      <input type="text" class="form-control" id="ndYth"
        placeholder="Mis: Direktur Sistem Kearsipan ANRI">
    </div>

    <!-- Dari -->
    <div class="form-group">
      <label class="form-label">Dari <span class="required">*</span>
        <span style="font-size:11px;color:#9ca3af;font-weight:400">— variabel: <code>\${dari}</code></span>
      </label>
      <input type="text" class="form-control" id="ndDari"
        placeholder="Mis: Kepala Subdirektorat Kajian Kearsipan">
    </div>

    <!-- Hal -->
    <div class="form-group">
      <label class="form-label">Hal / Perihal <span class="required">*</span>
        <span style="font-size:11px;color:#9ca3af;font-weight:400">— variabel: <code>\${hal}</code></span>
      </label>
      <input type="text" class="form-control" id="ndHal"
        placeholder="Mis: Tanggapan atas Permohonan Telaah Konsep">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <!-- Tanggal -->
      <div class="form-group">
        <label class="form-label">Tanggal
          <span style="font-size:11px;color:#9ca3af;font-weight:400">— <code>\${tanggal}</code></span>
        </label>
        <input type="text" class="form-control" id="ndTanggal"
          value="${tglDefault}" placeholder="01 Januari 2025">
      </div>

      <!-- Lampiran -->
      <div class="form-group">
        <label class="form-label">Lampiran
          <span style="font-size:11px;color:#9ca3af;font-weight:400">— <code>\${lampiran}</code></span>
        </label>
        <input type="text" class="form-control" id="ndLampiran"
          placeholder="Mis: 1 (satu) berkas / — (kosongkan jika tidak ada)">
      </div>
    </div>

    <hr style="margin:12px 0">
    <div style="font-size:12px;font-weight:700;color:#6b7280;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px">
      Isi Dokumen
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <!-- Jenis Dokumen -->
      <div class="form-group">
        <label class="form-label">Jenis Dokumen
          <span style="font-size:11px;color:#9ca3af;font-weight:400">— <code>\${jenis_dokumen}</code></span>
        </label>
        <input type="text" class="form-control" id="ndJenisDokumen"
          placeholder="Mis: Policy Brief / Nota Kajian">
      </div>

      <!-- Judul Dokumen -->
      <div class="form-group">
        <label class="form-label">Judul Dokumen
          <span style="font-size:11px;color:#9ca3af;font-weight:400">— <code>\${judul_dokumen}</code></span>
        </label>
        <input type="text" class="form-control" id="ndJudulDokumen"
          placeholder="Judul policy brief / paper yang dihasilkan">
      </div>
    </div>

    <!-- Uraian Tambahan -->
    <div class="form-group">
      <label class="form-label">Uraian Tambahan
        <span style="font-size:11px;color:#9ca3af;font-weight:400">— variabel: <code>\${uraian_tambahan}</code></span>
      </label>
      <textarea class="form-control" id="ndUraianTambahan" rows="3"
        placeholder="Uraian isi surat, poin penting, atau penjelasan tambahan yang akan muncul di badan surat"></textarea>
    </div>

    <hr style="margin:12px 0">
    <div style="font-size:12px;font-weight:700;color:#6b7280;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px">
      Penandatangan
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <!-- Jabatan Pengirim -->
      <div class="form-group">
        <label class="form-label">Jabatan Penandatangan <span class="required">*</span>
          <span style="font-size:11px;color:#9ca3af;font-weight:400">— <code>\${jabatan_pengirim}</code></span>
        </label>
        <input type="text" class="form-control" id="ndJabatanPengirim"
          placeholder="Mis: Direktur Sistem Kearsipan">
      </div>

      <!-- Nama Pengirim -->
      <div class="form-group">
        <label class="form-label">Nama Penandatangan <span class="required">*</span>
          <span style="font-size:11px;color:#9ca3af;font-weight:400">— <code>\${nama_pengirim}</code></span>
        </label>
        <input type="text" class="form-control" id="ndNamaPengirim"
          placeholder="Nama lengkap + gelar">
      </div>
    </div>

    <!-- Field tambahan per tipe (batas_waktu / rekomendasi) -->
    <div id="ndFieldExtra"></div>`

  openModal(`Generate Nota Dinas — ${esc(kode)}`, body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: '⬇ Generate & Unduh', cls: 'btn-primary', action: async () => {
      const tipe  = document.getElementById('ndTipe').value
      const yth   = document.getElementById('ndYth').value.trim()
      const dari  = document.getElementById('ndDari').value.trim()
      const hal   = document.getElementById('ndHal').value.trim()
      const jabatanPengirim = document.getElementById('ndJabatanPengirim').value.trim()
      const namaPengirim    = document.getElementById('ndNamaPengirim').value.trim()

      if (!yth || !dari || !hal) {
        showToast('Kepada Yth, Dari, dan Hal wajib diisi.', 'error'); return
      }
      if (!jabatanPengirim || !namaPengirim) {
        showToast('Jabatan dan Nama Penandatangan wajib diisi.', 'error'); return
      }

      const variabel = {
        yth,
        dari,
        hal,
        tanggal:          document.getElementById('ndTanggal').value.trim() || '',
        lampiran:         document.getElementById('ndLampiran').value.trim() || '—',
        jenis_dokumen:    document.getElementById('ndJenisDokumen').value.trim() || '',
        judul_dokumen:    document.getElementById('ndJudulDokumen').value.trim() || '',
        uraian_tambahan:  document.getElementById('ndUraianTambahan').value.trim() || '',
        jabatan_pengirim: jabatanPengirim,
        nama_pengirim:    namaPengirim,
      }

      // Field tambahan per tipe
      const elBatasWaktu  = document.getElementById('ndBatasWaktu')
      const elRekomendasi = document.getElementById('ndRekomendasi')
      if (elBatasWaktu)  variabel.batas_waktu  = elBatasWaktu.value.trim()
      if (elRekomendasi) variabel.rekomendasi   = elRekomendasi.value.trim()

      try {
        showToast('Sedang generate Nota Dinas…', 'info')
        const res = await apiPost('ltk/dokumen/generate-nota-dinas', {
          permohonan_id: permohonanId,
          tipe,
          nomor_nd: null,
          variabel,
        })
        closeModal()
        if (res?.status) {
          showToast('Nota Dinas berhasil digenerate!')
          const dokId = res.data?.dokumen_id
          openModal('Download Nota Dinas', `
            <p style="font-size:13px;margin-bottom:14px">
              Nota Dinas <strong>${esc(kode)}</strong> berhasil digenerate.
            </p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              ${res.data?.pdf_rel && dokId
                ? `<button class="btn btn-primary"  onclick="ltkDownloadWithAuth(event,'ltk/dokumen/download/${dokId}/pdf','nota_dinas.pdf')">📄 Download PDF</button>`
                : '<span style="font-size:12px;color:#6b7280">PDF tidak tersedia (library mPDF belum aktif).</span>'}
              ${res.data?.docx_rel && dokId
                ? `<button class="btn btn-secondary" onclick="ltkDownloadWithAuth(event,'ltk/dokumen/download/${dokId}/docx','nota_dinas.docx')">📝 Download DOCX</button>`
                : ''}
            </div>`, [{ label: 'Tutup', cls: 'btn-secondary', action: closeModal }])
          renderLtkTugasPic()
        } else {
          showToast(res?.message || 'Generate gagal.', 'error')
        }
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])

  // Render extra fields setelah modal terbuka
  setTimeout(ltkToggleNdFields, 80)
}

// Field tambahan berdasarkan tipe ND yang dipilih
function ltkToggleNdFields() {
  const tipe = document.getElementById('ndTipe')?.value
  const el   = document.getElementById('ndFieldExtra')
  if (!el) return

  if (tipe === 'nota_dinas_permintaan_penyesuaian') {
    el.innerHTML = `
      <hr style="margin:12px 0">
      <div style="font-size:12px;font-weight:700;color:#6b7280;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px">
        Tambahan — Permintaan Penyesuaian
      </div>
      <div class="form-group">
        <label class="form-label">Batas Waktu Penyesuaian
          <span style="font-size:11px;color:#9ca3af;font-weight:400">— <code>\${batas_waktu}</code></span>
        </label>
        <input type="text" class="form-control" id="ndBatasWaktu"
          placeholder="Mis: 14 hari kerja sejak diterimanya surat ini">
      </div>`
  } else if (tipe === 'nota_dinas_hasil_telaah_akhir') {
    el.innerHTML = `
      <hr style="margin:12px 0">
      <div style="font-size:12px;font-weight:700;color:#6b7280;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px">
        Tambahan — Hasil Telaah Akhir
      </div>
      <div class="form-group">
        <label class="form-label">Rekomendasi
          <span style="font-size:11px;color:#9ca3af;font-weight:400">— <code>\${rekomendasi}</code></span>
        </label>
        <textarea class="form-control" id="ndRekomendasi" rows="3"
          placeholder="Rekomendasi kebijakan berdasarkan hasil telaah akhir"></textarea>
      </div>`
  } else {
    el.innerHTML = ''
  }
}

// ══════════════════════════════════════════════════════════════
// PIC — SELESAIKAN PERMOHONAN
// ══════════════════════════════════════════════════════════════
async function ltkSelesaikanPermohonan(id, kode) {
  openModal('Selesaikan Permohonan', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">✅</div>
      <div style="font-size:14px;font-weight:700;color:#1f2328;margin-bottom:6px">Selesaikan & Tutup?</div>
      <div style="font-size:13px;color:#6b7280">
        Tandai permohonan <strong>${esc(kode)}</strong> sebagai <strong>Selesai</strong> dan <strong>Ditutup</strong>?<br>
        <span style="color:#ef4444">Tindakan ini tidak dapat dibatalkan.</span>
      </div>
    </div>`, [
    { label: 'Batal',        cls: 'btn-secondary', action: closeModal },
    { label: '✓ Selesaikan', cls: 'btn-success',   action: async () => {
      try {
        const res = await apiPost(`ltk/dokumen/${id}/selesai`, {})
        closeModal()
        showToast(res.message || 'Permohonan berhasil diselesaikan.')
        renderLtkTugasPic()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ══════════════════════════════════════════════════════════════
// PIC — DOKUMEN LIST (lihat seluruh output suatu permohonan)
// ══════════════════════════════════════════════════════════════
async function ltkShowDokumen(permohonanId, kode) {
  openModal(`Dokumen Output — ${esc(kode)}`, '<div class="spinner-sm"></div>')
  try {
    const res  = await apiGet(`ltk/dokumen/${permohonanId}`)
    const docs = res.data?.items || []
    const body = docs.length
      ? `<div style="display:grid;gap:10px">${docs.map(d => `
          <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <div>
              <div style="font-weight:600;font-size:13px">${d.tipe_dokumen.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</div>
              <div style="font-size:11px;color:#6b7280;margin-top:2px">${d.tgl_dokumen || '—'}</div>
              ${d.keterangan_link ? `<div style="font-size:11px;color:#374151;margin-top:2px">${esc(d.keterangan_link)}</div>` : ''}
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              ${d.link_cloud     ? `<a href="${esc(d.link_cloud)}" target="_blank" class="btn btn-sm btn-primary">☁ Buka Cloud</a>` : ''}
              ${d.file_pdf_path  ? `<a href="${BASE_URL}ltk/dokumen/download/${d.id}/pdf"  download class="btn btn-sm btn-secondary" onclick="ltkDownloadWithAuth(event,'ltk/dokumen/download/${d.id}/pdf',  '${esc(d.tipe_dokumen)}.pdf' )">📄 PDF</a>`  : ''}
              ${d.file_docx_path ? `<a href="${BASE_URL}ltk/dokumen/download/${d.id}/docx" download class="btn btn-sm btn-secondary" onclick="ltkDownloadWithAuth(event,'ltk/dokumen/download/${d.id}/docx','${esc(d.tipe_dokumen)}.docx')">📝 DOCX</a>` : ''}
            </div>
          </div>`).join('')}</div>`
      : '<div style="color:#6b7280;font-size:13px;text-align:center;padding:20px">Belum ada dokumen output.</div>'
    openModal(`Dokumen Output — ${esc(kode)}`, body, [{ label: 'Tutup', cls: 'btn-secondary', action: closeModal }])
  } catch(e) { openModal('Error', `<div class="alert alert-danger">${e.message}</div>`) }
}

// ══════════════════════════════════════════════════════════════
// ADMIN LTK — Halaman utama pengaturan LTK
// ══════════════════════════════════════════════════════════════
async function renderLtkAdmin() {
  const content = document.getElementById('mainContent')
  content.innerHTML = `
    <div class="page-header" style="margin-bottom:20px">
      <h2 style="font-size:18px;font-weight:700;color:var(--gray-800)">Pengaturan Layanan Telaah Konsep</h2>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

      <!-- SLA Config -->
      <div class="card" style="padding:20px">
        <div style="font-weight:700;font-size:14px;margin-bottom:14px">⏱ Konfigurasi SLA</div>
        <div id="ltkSlaConfigBody"><div class="spinner-sm"></div></div>
      </div>

      <!-- ND Template Status -->
      <div class="card" style="padding:20px">
        <div style="font-weight:700;font-size:14px;margin-bottom:14px">📄 Status Template Nota Dinas</div>
        <div id="ltkNdTemplateBody"><div class="spinner-sm"></div></div>
      </div>
    </div>

    <!-- Hari Libur -->
    <div class="card" style="padding:20px;margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div style="font-weight:700;font-size:14px">📅 Hari Libur Nasional</div>
        <div style="display:flex;gap:8px;align-items:center">
          <select id="ltkHlTahun" class="form-control" style="width:100px;font-size:13px" onchange="ltkLoadHariLibur()">
            ${Array.from({length:4}, (_,i) => new Date().getFullYear() - 1 + i).map(y => `<option value="${y}" ${y === new Date().getFullYear() ? 'selected' : ''}>${y}</option>`).join('')}
          </select>
          <button class="btn btn-sm btn-primary" onclick="ltkShowTambahHariLibur()">+ Tambah</button>
          <button class="btn btn-sm btn-secondary" onclick="ltkShowImportHariLibur()">📋 Import JSON</button>
        </div>
      </div>
      <div id="ltkHariLiburBody"><div class="spinner-sm"></div></div>
    </div>

    <!-- Verifikasi Akun Klien -->
    <div class="card" style="padding:20px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div style="font-weight:700;font-size:14px">👤 Verifikasi Akun Klien</div>
        <button class="btn btn-sm btn-secondary" onclick="ltkLoadKlienPending()">↻ Refresh</button>
      </div>
      <div id="ltkKlienPendingBody"><div class="spinner-sm"></div></div>
    </div>
  `
  ltkLoadSlaConfig()
  ltkLoadNdTemplateStatus()
  ltkLoadHariLibur()
  ltkLoadKlienPending()
}

// ── SLA Config ─────────────────────────────────────────────
async function ltkLoadSlaConfig() {
  const el = document.getElementById('ltkSlaConfigBody')
  if (!el) return
  try {
    const res  = await apiGet('ltk/admin/sla-config')
    const rows = res.data?.items || []
    el.innerHTML = `
      <form onsubmit="event.preventDefault();ltkSaveSlaConfig()">
        ${rows.map(r => `
          <div class="form-group">
            <label class="form-label">Skenario ${r.skenario} — SLA (Hari Kerja)</label>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="number" class="form-control" id="slaSkenario${r.skenario}" value="${r.hari_kerja}" min="1" max="90" style="width:100px">
              <span style="font-size:12px;color:#6b7280">HK</span>
            </div>
          </div>`).join('')}
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:6px">Simpan SLA</button>
      </form>`
  } catch(e) {
    el.innerHTML = `<div class="alert alert-danger">${e.message}</div>`
  }
}

async function ltkSaveSlaConfig() {
  const body = {}
  for (const sk of ['A', 'B']) {
    const el = document.getElementById(`slaSkenario${sk}`)
    if (el) body[sk] = parseInt(el.value)
  }
  try {
    const res = await apiPut('ltk/admin/sla-config', body)
    showToast(res.message || 'SLA berhasil disimpan.')
  } catch(e) { showToast(e.message, 'error') }
}

// ── ND Template Status ──────────────────────────────────────
async function ltkLoadNdTemplateStatus() {
  const el = document.getElementById('ltkNdTemplateBody')
  if (!el) return
  try {
    const res  = await apiGet('ltk/admin/nd-template-status')
    const rows = res.data?.items || []
    el.innerHTML = `
      <div style="display:grid;gap:8px">
        ${rows.map(r => `
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f1f5f9">
            <div>
              <div style="font-size:12px;font-weight:600">${r.tipe.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</div>
              <div style="font-size:10px;color:#6b7280;margin-top:2px">Var: ${r.variabel.slice(0,3).join(', ')}${r.variabel.length>3?' …':''}</div>
            </div>
            <div style="display:flex;gap:6px;align-items:center">
              <span class="status-badge ${r.tersedia ? 'status-selesai' : 'status-terlambat'}" style="font-size:10px">${r.tersedia ? 'Tersedia' : 'Belum Ada'}</span>
              <button class="btn btn-sm btn-secondary" style="font-size:11px;padding:3px 8px" onclick="ltkShowUploadNdTemplate('${r.tipe}')">Upload</button>
            </div>
          </div>`).join('')}
      </div>`
  } catch(e) {
    el.innerHTML = `<div style="font-size:12px;color:#6b7280">Gagal memuat status template.</div>`
  }
}

function ltkShowUploadNdTemplate(tipe) {
  const label = tipe.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())
  const body = `
    <div style="font-size:13px;margin-bottom:14px">Upload template untuk: <strong>${label}</strong></div>
    <div class="alert" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;font-size:12px;color:#1e40af;margin-bottom:14px">
      Template harus berformat <strong>.docx</strong> dengan placeholder <code>\${NAMA_VARIABEL}</code> sesuai Tata Naskah Dinas ANRI.
    </div>
    <div class="form-group">
      <label class="form-label">File Template (.docx) <span class="required">*</span></label>
      <input type="file" class="form-control" id="ndTmplFile" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
    </div>`
  openModal(`Upload Template — ${label}`, body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: '⬆ Upload', cls: 'btn-primary', action: async () => {
      const fileEl = document.getElementById('ndTmplFile')
      if (!fileEl?.files?.length) { showToast('Pilih file .docx terlebih dahulu.', 'error'); return }
      const formData = new FormData()
      formData.append('tipe', tipe)
      formData.append('file', fileEl.files[0])
      try {
        const res = await apiUpload('ltk/admin/nd-template/upload', formData)
        if (!res || !res.status) {
          showToast(res?.message || 'Upload gagal.', 'error'); return
        }
        closeModal()
        showToast(res.message || 'Template berhasil diupload.')
        ltkLoadNdTemplateStatus()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ── Hari Libur ──────────────────────────────────────────────
async function ltkLoadHariLibur() {
  const el    = document.getElementById('ltkHariLiburBody')
  const tahun = document.getElementById('ltkHlTahun')?.value || new Date().getFullYear()
  if (!el) return
  el.innerHTML = '<div class="spinner-sm"></div>'
  try {
    const res  = await apiGet(`ltk/admin/hari-libur?tahun=${tahun}`)
    const rows = res.data?.items || []
    if (!rows.length) {
      el.innerHTML = `<div style="font-size:13px;color:#6b7280;text-align:center;padding:16px">Belum ada hari libur untuk tahun ${tahun}.</div>`
      return
    }
    el.innerHTML = `
      <div style="overflow-x:auto">
        <table class="table" style="font-size:13px">
          <thead><tr><th>No</th><th>Tanggal</th><th>Hari</th><th>Keterangan</th><th></th></tr></thead>
          <tbody>${rows.map((r,i) => {
            const d     = new Date(r.tanggal + 'T00:00:00')
            const hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][d.getDay()]
            return `<tr>
              <td>${i+1}</td>
              <td>${r.tanggal}</td>
              <td style="color:${[0,6].includes(d.getDay())?'#ef4444':'inherit'}">${hari}</td>
              <td>${esc(r.keterangan||'—')}</td>
              <td><button class="btn btn-sm" style="background:#fee2e2;color:#b91c1c;padding:3px 8px;font-size:11px" onclick="ltkDeleteHariLibur(${r.id},'${r.tanggal}')">Hapus</button></td>
            </tr>`}).join('')}
          </tbody>
        </table>
      </div>`
  } catch(e) {
    el.innerHTML = `<div class="alert alert-danger">${e.message}</div>`
  }
}

function ltkShowTambahHariLibur() {
  const body = `
    <div class="form-group">
      <label class="form-label">Tanggal <span class="required">*</span></label>
      <input type="date" class="form-control" id="hlTanggal">
    </div>
    <div class="form-group">
      <label class="form-label">Keterangan</label>
      <input type="text" class="form-control" id="hlKet" placeholder="Mis: Hari Natal">
    </div>`
  openModal('Tambah Hari Libur', body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Simpan', cls: 'btn-primary', action: async () => {
      const tgl = document.getElementById('hlTanggal').value
      if (!tgl) { showToast('Tanggal wajib diisi.', 'error'); return }
      try {
        const res = await apiPost('ltk/admin/hari-libur', { tanggal: tgl, keterangan: document.getElementById('hlKet').value || null })
        closeModal()
        showToast(res.message || 'Hari libur berhasil ditambahkan.')
        ltkLoadHariLibur()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

function ltkShowImportHariLibur() {
  const contoh = JSON.stringify([
    { tanggal: '2025-01-01', keterangan: 'Tahun Baru Masehi' },
    { tanggal: '2025-03-31', keterangan: 'Hari Raya Idul Fitri' },
  ], null, 2)
  const body = `
    <div style="font-size:12px;color:#6b7280;margin-bottom:10px">Paste array JSON hari libur. Duplikat akan dilewati.</div>
    <div class="form-group">
      <label class="form-label">JSON Data <span class="required">*</span></label>
      <textarea class="form-control" id="hlJson" rows="8" style="font-family:monospace;font-size:12px" placeholder='${esc(contoh)}'></textarea>
    </div>`
  openModal('Import Hari Libur (JSON)', body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Import', cls: 'btn-primary', action: async () => {
      let items
      try { items = JSON.parse(document.getElementById('hlJson').value) }
      catch(e) { showToast('JSON tidak valid.', 'error'); return }
      if (!Array.isArray(items) || !items.length) { showToast('Data harus berupa array tidak kosong.', 'error'); return }
      try {
        const res = await apiPost('ltk/admin/hari-libur/batch', { items })
        closeModal()
        showToast(res.message || 'Import selesai.')
        ltkLoadHariLibur()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

async function ltkDeleteHariLibur(id, tanggal) {
  openModal('Hapus Hari Libur', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">📅</div>
      <div style="font-size:14px;font-weight:700;color:#1f2328;margin-bottom:6px">Hapus Hari Libur?</div>
      <div style="font-size:13px;color:#6b7280">
        Hapus data hari libur tanggal <strong>${tanggal}</strong>?
      </div>
    </div>`, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Hapus', cls: 'btn-danger',    action: async () => {
      try {
        const res = await apiDelete(`ltk/admin/hari-libur/${id}`)
        closeModal()
        showToast(res.message || 'Hari libur dihapus.')
        ltkLoadHariLibur()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ── Verifikasi Akun Klien ───────────────────────────────────
async function ltkLoadKlienPending() {
  const el = document.getElementById('ltkKlienPendingBody')
  if (!el) return
  el.innerHTML = '<div class="spinner-sm"></div>'
  try {
    const res  = await apiGet('ltk/klien/pending')
    const rows = res.data?.items || []
    if (!rows.length) {
      el.innerHTML = '<div style="font-size:13px;color:#6b7280;text-align:center;padding:16px">Tidak ada akun klien yang menunggu verifikasi.</div>'
      return
    }
    el.innerHTML = `
      <table class="table table-hover" style="font-size:13px">
        <thead><tr><th>Nama</th><th>Email</th><th>Unit Kerja</th><th>Jabatan</th><th>Telepon</th><th>Aksi</th></tr></thead>
        <tbody>${rows.map(r => `
          <tr>
            <td><strong>${esc(r.nama)}</strong></td>
            <td>${esc(r.email)}</td>
            <td>${esc(r.unit_kerja||'—')}</td>
            <td>${esc(r.jabatan||'—')}</td>
            <td>${esc(r.no_telepon||'—')}</td>
            <td>
              <button class="btn btn-sm btn-success" onclick="ltkVerifikasiKlien(${r.user_id},'${esc(r.nama)}')">✓ Verifikasi</button>
            </td>
          </tr>`).join('')}
        </tbody>
      </table>`
  } catch(e) {
    el.innerHTML = `<div class="alert alert-danger">${e.message}</div>`
  }
}

async function ltkVerifikasiKlien(userId, nama) {
  openModal('Verifikasi Akun Klien', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">✅</div>
      <div style="font-size:14px;font-weight:700;color:#1f2328;margin-bottom:6px">Verifikasi Akun?</div>
      <div style="font-size:13px;color:#6b7280">
        Verifikasi dan aktifkan akun klien <strong>${esc(nama)}</strong>?<br>
        Setelah diverifikasi, klien dapat login dan mengajukan permohonan.
      </div>
    </div>`, [
    { label: 'Batal',        cls: 'btn-secondary', action: closeModal },
    { label: '✓ Verifikasi', cls: 'btn-success',   action: async () => {
      try {
        const res = await apiPost(`ltk/klien/${userId}/verifikasi`, {})
        closeModal()
        showToast(res.message || 'Akun klien berhasil diverifikasi.')
        ltkLoadKlienPending()
        // Update recent list di dashboard jika sedang ada
        const dashEl = document.getElementById('ltkRecentList')
        if (dashEl) ltkLoadRecentPermohonan()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ══════════════════════════════════════════════════════════════
// ltkDownloadWithAuth — Download file dari API endpoint dengan JWT
// Dipanggil dari onclick button download Nota Dinas
// ══════════════════════════════════════════════════════════════
async function ltkDownloadWithAuth(event, apiPath, filename) {
  event.preventDefault()
  try {
    showToast('Mengunduh file…', 'info')
    const token = localStorage.getItem('mka_token')
    const res   = await fetch(BASE_URL + apiPath, {
      headers: token ? { 'Authorization': 'Bearer ' + token } : {},
    })
    if (!res.ok) {
      const err = await res.json().catch(() => null)
      showToast((err?.message) || `Gagal mengunduh (HTTP ${res.status}).`, 'error')
      return
    }
    const blob = await res.blob()
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = filename
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch(e) {
    showToast('Gagal mengunduh: ' + e.message, 'error')
  }
}
