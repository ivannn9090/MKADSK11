/* ============================================================
   rpd.js — Rencana Penarikan Dana (RPD) page
   ============================================================ */
'use strict';

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
  const canEdit = hasPermission('rpd.edit');

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
