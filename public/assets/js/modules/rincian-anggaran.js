/* ============================================================
   rincian-anggaran.js — Komponen biaya penyusun pagu kegiatan
   Hak akses write: direktur + perencana_anggaran
   ============================================================ */
'use strict';

// Satuan yang sering dipakai — user tetap bisa ketik bebas
const SATUAN_QTY_LIST = ['Orang', 'Unit', 'Eksemplar', 'Paket', 'Lembar', 'Buah', 'Set', 'Kali'];
const SATUAN_VOL_LIST = ['Kali', 'Hari', 'Kegiatan', 'Bulan', 'Tahun', 'Jam', 'Sesi'];

// ---------------------------------------------------------------
// Render tab Rincian Anggaran (dipanggil dari kegiatan.js)
// ---------------------------------------------------------------
async function loadRincianAnggaran(kegiatanId, isOffBudget = false) {
  const container = document.getElementById(`tab-rincian-${kegiatanId}`);
  if (!container) return;
  container.innerHTML = '<div style="padding:20px;text-align:center"><div class="spinner-lg"></div></div>';

  // Off-budget: gunakan endpoint khusus yang membaca sumber anggaran
  // On-budget : gunakan /rincian/sisa yang menyertakan nominal_terpakai
  const endpoint = isOffBudget
    ? `off-budget/${kegiatanId}/rincian`
    : `kegiatan/${kegiatanId}/rincian/sisa`;

  const res = await apiGet(endpoint);
  if (!res || !res.status) {
    container.innerHTML = '<div style="padding:16px;color:#dc2626">Gagal memuat rincian anggaran.</div>';
    return;
  }

  const rows  = res.data.rows  || [];
  const total = rows.reduce((s, r) => s + parseFloat(r.subtotal || 0), 0);
  // Off-budget: rincian berasal dari sumber, tidak bisa diedit di sini
  const canEdit = !isOffBudget && hasPermission('anggaran.input');

  container.innerHTML = `
    ${canEdit ? `
      <div style="margin-bottom:14px">
        <button class="btn btn-primary btn-sm" onclick="showFormTambahRincian(${kegiatanId})">
          + Tambah Komponen Biaya
        </button>
      </div>
    ` : ''}

    ${isOffBudget && rows.length > 0 ? `
      <div style="background:#f5f3ff;border:1px solid #c4b5fd;border-radius:7px;padding:9px 14px;
                  margin-bottom:12px;font-size:12px;color:#5b21b6;display:flex;align-items:center;gap:8px">
        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M13 9h-2V7h2m0 10h-2v-6h2m-1-9A10 10 0 0 0 2 12a10 10 0 0 0 10 10 10 10 0 0 0 10-10A10 10 0 0 0 12 2z"/></svg>
        Rincian anggaran ini otomatis berasal dari sumber yang dialokasikan. Untuk mengubah, kelola melalui <strong>⚙ Kelola Sumber</strong>.
      </div>
    ` : ''}

    <div id="rincianList-${kegiatanId}">
      ${renderRincianTable(rows, kegiatanId, canEdit, isOffBudget)}
    </div>

    <div class="rincian-total-bar" id="rincianTotalBar-${kegiatanId}">
      <span>${isOffBudget ? 'Total Alokasi Off Budget' : 'Total Pagu Anggaran (dari rincian)'}</span>
      <span class="rincian-total-val" id="rincianTotal-${kegiatanId}">${rupiah(total)}</span>
    </div>

    ${rows.length > 0 && canEdit ? `
      <div style="margin-top:10px;font-size:11.5px;color:#64748b">
        💡 Pagu anggaran kegiatan otomatis diperbarui setiap kali rincian berubah.
      </div>
    ` : ''}
  `;
}

function renderRincianTable(rows, kegiatanId, canEdit, isOffBudget = false) {
  if (!rows || rows.length === 0) {
    return `<div style="text-align:center;padding:32px;color:#94a3b8;font-size:13px">
      ${isOffBudget
        ? 'Belum ada sumber anggaran yang dialokasikan.<br>Tambahkan sumber melalui <strong>⚙ Kelola Sumber</strong>.'
        : `Belum ada komponen biaya.<br>${canEdit ? 'Klik <strong>+ Tambah Komponen Biaya</strong> untuk memulai.' : ''}`}
    </div>`;
  }

  const totalRealisasi = rows.reduce((s, r) => s + parseFloat(r.nominal_terpakai || 0), 0);
  const totalSubtotal  = rows.reduce((s, r) => s + parseFloat(r.subtotal || 0), 0);

  return `
    <div class="rincian-table-wrap">
      <table class="rincian-table">
        <thead>
          <tr>
            <th style="width:32px">#</th>
            <th>Komponen / Uraian</th>
            <th style="text-align:right;width:68px">Qty</th>
            <th style="width:64px">Satuan</th>
            <th style="text-align:right;width:68px">Volume</th>
            <th style="width:64px">Satuan</th>
            <th style="text-align:right;width:120px">Harga Satuan</th>
            <th style="text-align:right;width:120px">Subtotal</th>
            <th style="text-align:right;width:110px">Realisasi</th>
            ${canEdit ? '<th style="width:56px"></th>' : ''}
          </tr>
        </thead>
        <tbody>
          ${rows.map((r, i) => {
            const subtotal    = parseFloat(r.subtotal || 0);
            const terpakai    = parseFloat(r.nominal_terpakai || 0);
            const sisa        = parseFloat(r.nominal_sisa     || subtotal - terpakai);
            const rawPct      = subtotal > 0 ? Math.round(terpakai / subtotal * 100) : 0;
            const pct         = Math.min(100, rawPct); // display cap
            const serapanColor = rawPct > 100 ? '#dc2626'
                               : rawPct === 100 ? '#16a34a'
                               : rawPct >= 50   ? '#d97706'
                               :                  '#94a3b8';
            // OB sumber-only rows (no linked rincian_id): simplified display
            const isObSumber  = isOffBudget && !r.rincian_id;
            return `
            <tr id="rincian-row-${r.obs_id || r.id}">
              <td style="color:#94a3b8;font-size:12px;text-align:center">${i + 1}</td>
              <td>
                <div style="font-weight:600;font-size:13px">${esc(r.komponen)}</div>
                ${r.uraian ? `<div style="font-size:11.5px;color:#64748b;margin-top:2px">${esc(r.uraian)}</div>` : ''}
                ${isObSumber ? `<div style="font-size:11px;color:#7c3aed;margin-top:2px">⛓ Alokasi langsung dari sumber</div>` : ''}
              </td>
              ${isObSumber ? `
              <td colspan="5" style="text-align:center;color:#94a3b8;font-size:12px;font-style:italic">— alokasi sumber —</td>
              ` : `
              <td style="text-align:right">${formatAngka(r.qty)}</td>
              <td style="color:#64748b;font-size:12px">${esc(r.satuan_qty || '—')}</td>
              <td style="text-align:right">${formatAngka(r.volume)}</td>
              <td style="color:#64748b;font-size:12px">${esc(r.satuan_vol || '—')}</td>
              <td style="text-align:right">${rupiah(r.harga_satuan)}</td>
              `}
              <td style="text-align:right;font-weight:700;color:#1e40af">${rupiah(subtotal)}</td>
              <td style="text-align:right">
                <div style="font-weight:700;color:${serapanColor}">${rupiah(terpakai)}</div>
                <div style="font-size:10.5px;color:#94a3b8;margin-top:1px">${pct}%${rawPct > 100 ? ' ⚠' : ''} · sisa ${rupiahSingkat(sisa)}</div>
              </td>
              ${canEdit ? `
                <td style="white-space:nowrap;text-align:center">
                  <button class="btn-icon" title="Edit" onclick="showFormEditRincian(${kegiatanId}, ${r.id}, ${JSON.stringify(r).replace(/"/g,'&quot;')})">✏️</button>
                  <button class="btn-icon" title="Hapus" onclick="confirmHapusRincian(${kegiatanId}, ${r.id}, '${esc(r.komponen)}')">🗑️</button>
                </td>
              ` : ''}
            </tr>`;
          }).join('')}
        </tbody>
        <tfoot>
          <tr style="background:var(--gray-50);font-weight:700">
            <td colspan="7" style="text-align:right;padding:8px;font-size:12.5px;color:var(--gray-700)">Total</td>
            <td style="text-align:right;padding:8px;color:#1e40af;font-size:13px">${rupiah(totalSubtotal)}</td>
            <td style="text-align:right;padding:8px;color:#16a34a;font-size:13px">${rupiah(totalRealisasi)}</td>
            ${canEdit ? '<td></td>' : ''}
          </tr>
        </tfoot>
      </table>
    </div>
  `;
}

// ---------------------------------------------------------------
// Form Tambah / Edit Rincian
// ---------------------------------------------------------------
function showFormTambahRincian(kegiatanId) {
  _openRincianForm(kegiatanId, null, null);
}

function showFormEditRincian(kegiatanId, rid, data) {
  _openRincianForm(kegiatanId, rid, data);
}

function _openRincianForm(kegiatanId, rid, existing) {
  const isEdit = !!rid;
  const title  = isEdit ? 'Edit Komponen Biaya' : 'Tambah Komponen Biaya';

  const satuanQtyOpts = SATUAN_QTY_LIST.map(s =>
    `<option value="${s}" ${existing?.satuan_qty === s ? 'selected' : ''}>${s}</option>`
  ).join('');
  const satuanVolOpts = SATUAN_VOL_LIST.map(s =>
    `<option value="${s}" ${existing?.satuan_vol === s ? 'selected' : ''}>${s}</option>`
  ).join('');

  openModal(title, `
    <div class="form-group">
      <label>Komponen / Nama Item *</label>
      <input type="text" id="fr_komponen"
        placeholder="cth: Uang Harian Rapat, Narasumber, Pembelian Buku…"
        value="${esc(existing?.komponen || '')}">
    </div>
    <div class="form-group">
      <label>Uraian <span style="color:#94a3b8;font-weight:400">(opsional)</span></label>
      <input type="text" id="fr_uraian"
        placeholder="Keterangan tambahan, misal: termasuk transport"
        value="${esc(existing?.uraian || '')}">
    </div>

    <div class="rincian-kalkulasi-grid">
      <!-- Qty -->
      <div class="form-group">
        <label>Qty *</label>
        <input type="number" id="fr_qty" min="0" step="any"
          value="${existing?.qty ?? 1}" oninput="hitungPreviewRincian()">
      </div>
      <div class="form-group">
        <label>Satuan Qty</label>
        <input type="text" id="fr_satuan_qty" list="dlist_satuan_qty"
          placeholder="Orang, Unit…"
          value="${esc(existing?.satuan_qty || '')}">
        <datalist id="dlist_satuan_qty">${satuanQtyOpts}</datalist>
      </div>

      <!-- Volume -->
      <div class="form-group">
        <label>Volume *</label>
        <input type="number" id="fr_volume" min="0" step="any"
          value="${existing?.volume ?? 1}" oninput="hitungPreviewRincian()">
      </div>
      <div class="form-group">
        <label>Satuan Vol</label>
        <input type="text" id="fr_satuan_vol" list="dlist_satuan_vol"
          placeholder="Kali, Hari…"
          value="${esc(existing?.satuan_vol || '')}">
        <datalist id="dlist_satuan_vol">${satuanVolOpts}</datalist>
      </div>

      <!-- Harga -->
      <div class="form-group" style="grid-column:1/-1">
        <label>Harga Satuan (Rp) *</label>
        <input type="number" id="fr_harga" min="0" step="any"
          value="${existing?.harga_satuan ?? ''}"
          placeholder="0"
          oninput="hitungPreviewRincian()">
      </div>
    </div>

    <!-- Preview kalkulasi -->
    <div class="rincian-preview" id="rincianPreview">
      <div class="rincian-preview-formula" id="rincianFormula">—</div>
      <div class="rincian-preview-total" id="rincianPreviewTotal">Rp 0</div>
    </div>
  `, [
    { label: 'Batal',  cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary',  action: `saveRincian(${kegiatanId}, ${rid || 'null'})` },
  ]);

  // Hitung preview langsung saat form terbuka (mode edit)
  hitungPreviewRincian();
}

/** Live preview kalkulasi subtotal di form */
function hitungPreviewRincian() {
  const qty   = parseFloat(document.getElementById('fr_qty')?.value) || 0;
  const vol   = parseFloat(document.getElementById('fr_volume')?.value) || 0;
  const harga = parseFloat(document.getElementById('fr_harga')?.value) || 0;
  const sub   = qty * vol * harga;

  const sqty = document.getElementById('fr_satuan_qty')?.value || '';
  const svol = document.getElementById('fr_satuan_vol')?.value || '';

  const formulaEl = document.getElementById('rincianFormula');
  const totalEl   = document.getElementById('rincianPreviewTotal');
  if (!formulaEl || !totalEl) return;

  const parts = [];
  if (qty > 0)   parts.push(`${formatAngka(qty)}${sqty ? ' ' + sqty : ''}`);
  if (vol > 0)   parts.push(`${formatAngka(vol)}${svol ? ' ' + svol : ''}`);
  if (harga > 0) parts.push(rupiah(harga));
  formulaEl.textContent = parts.length ? parts.join(' × ') : '—';
  totalEl.textContent   = rupiah(sub);
  totalEl.style.color   = sub > 0 ? '#1e40af' : '#94a3b8';
}

// ---------------------------------------------------------------
// Save (create / update)
// ---------------------------------------------------------------
async function saveRincian(kegiatanId, rid) {
  const payload = {
    komponen:     document.getElementById('fr_komponen')?.value?.trim(),
    uraian:       document.getElementById('fr_uraian')?.value?.trim() || null,
    qty:          parseFloat(document.getElementById('fr_qty')?.value) || 0,
    satuan_qty:   document.getElementById('fr_satuan_qty')?.value?.trim() || null,
    volume:       parseFloat(document.getElementById('fr_volume')?.value) || 0,
    satuan_vol:   document.getElementById('fr_satuan_vol')?.value?.trim() || null,
    harga_satuan: parseFloat(document.getElementById('fr_harga')?.value) || 0,
  };

  if (!payload.komponen)        { showToast('Nama komponen wajib diisi', 'error'); return; }
  if (payload.qty <= 0)         { showToast('Qty harus lebih dari 0', 'error'); return; }
  if (payload.volume <= 0)      { showToast('Volume harus lebih dari 0', 'error'); return; }
  if (payload.harga_satuan <= 0){ showToast('Harga satuan harus lebih dari 0', 'error'); return; }

  const res = rid
    ? await apiPut(`kegiatan/${kegiatanId}/rincian/${rid}`, payload)
    : await apiPost(`kegiatan/${kegiatanId}/rincian`, payload);

  if (res && res.status) {
    showToast(rid ? 'Rincian diperbarui' : 'Rincian ditambahkan', 'success');
    closeModal();
    // Refresh tab rincian + refresh card di grid
    await loadRincianAnggaran(kegiatanId);
    refreshCardInGrid(kegiatanId);
  } else {
    showToast(res?.message || 'Gagal menyimpan', 'error');
  }
}

// ---------------------------------------------------------------
// Delete
// ---------------------------------------------------------------
async function confirmHapusRincian(kegiatanId, rid, nama) {
  if (!confirm(`Hapus komponen "${nama}"?\nPagu anggaran akan otomatis diperbarui.`)) return;
  const res = await apiDelete(`kegiatan/${kegiatanId}/rincian/${rid}`);
  if (res && res.status) {
    showToast('Komponen dihapus', 'success');
    await loadRincianAnggaran(kegiatanId);
    refreshCardInGrid(kegiatanId);
  } else {
    showToast(res?.message || 'Gagal menghapus', 'error');
  }
}

// ---------------------------------------------------------------
// Helper
// ---------------------------------------------------------------
function formatAngka(val) {
  const n = parseFloat(val);
  if (isNaN(n)) return '0';
  // Tampilkan tanpa desimal jika bulat
  return n % 1 === 0 ? n.toLocaleString('id-ID') : n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}
