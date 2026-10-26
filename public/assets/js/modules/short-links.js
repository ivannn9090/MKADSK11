/**
 * short-links.js — modul URL Shortener & QR Code Generator
 * Mengikuti pola render*() yang digunakan semua modul di aplikasi ini:
 *   - renderShortLinks() menulis HTML langsung ke #mainContent
 *   - Semua event handler diikat setelah innerHTML ditulis
 */
'use strict'

// ── State ──────────────────────────────────────────────────────
let _slLinks        = []
let _slDeleteTarget = null

// ── Render halaman utama ────────────────────────────────────────
async function renderShortLinks() {
  const content = document.getElementById('mainContent')

  content.innerHTML = `
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;
                margin-bottom:20px;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-size:18px;font-weight:700;color:var(--gray-800)">URL Shortener & QR Code</div>
        <div style="font-size:12px;color:#6b7280;margin-top:2px">Buat tautan pendek dan QR Code secara instan</div>
      </div>
      <button class="btn btn-primary" onclick="slOpenForm()">
        <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15" style="margin-right:6px"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        Buat Short Link
      </button>
    </div>

    <!-- Form Card (tersembunyi default) -->
    <div id="slFormCard" style="display:none;background:var(--white);border:1px solid var(--border);
         border-radius:12px;margin-bottom:20px;overflow:hidden">
      <div style="padding:16px 20px;border-bottom:1px solid var(--border);
                  display:flex;align-items:center;justify-content:space-between">
        <div style="font-weight:700;font-size:14px;color:var(--gray-800)">Buat Short Link Baru</div>
        <button onclick="slCloseForm()" style="background:none;border:none;cursor:pointer;
                color:var(--gray-400);padding:4px;border-radius:4px" title="Tutup">
          <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </button>
      </div>
      <div style="padding:20px">
        <form id="slForm" onsubmit="slSubmitForm(event)" autocomplete="off">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div style="grid-column:1/-1">
              <label class="form-label">URL Asli (Long URL) <span style="color:#ef4444">*</span></label>
              <input type="url" class="form-control" id="slLongUrl"
                     placeholder="https://contoh.com/halaman-panjang/?q=nilai" required>
              <small style="font-size:11px;color:var(--gray-500)">Masukkan URL lengkap yang ingin dipersingkat.</small>
            </div>
            <div>
              <label class="form-label">Custom Slug <span style="font-size:11px;color:var(--gray-400)">(Opsional)</span></label>
              <div style="display:flex">
                <span style="display:flex;align-items:center;padding:0 10px;
                       background:var(--gray-100);border:1px solid var(--border);
                       border-right:none;border-radius:6px 0 0 6px;
                       font-size:12px;color:var(--gray-500);white-space:nowrap">s/</span>
                <input type="text" class="form-control" id="slSlug"
                       placeholder="contoh-saya" pattern="[a-zA-Z0-9_\\-]{3,64}" maxlength="64"
                       style="border-radius:0 6px 6px 0">
              </div>
              <small style="font-size:11px;color:var(--gray-500)">Kosongkan untuk slug acak. Huruf, angka, - dan _.</small>
            </div>
            <div>
              <label class="form-label">Judul <span style="font-size:11px;color:var(--gray-400)">(Opsional)</span></label>
              <input type="text" class="form-control" id="slTitle"
                     placeholder="Nama/deskripsi singkat" maxlength="255">
            </div>
          </div>

          <!-- Preview QR setelah berhasil dibuat -->
          <div id="slQrPreview" style="display:none;margin-top:16px;padding:16px;
               background:var(--gray-50);border:1px solid var(--border);border-radius:8px">
            <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
              <div style="text-align:center">
                <img id="slQrImg" src="" alt="QR Code"
                     style="width:140px;height:140px;border:1px solid var(--border);border-radius:6px;display:block">
                <a id="slQrDownload" href="#" download
                   style="margin-top:8px;display:inline-flex;align-items:center;gap:5px;
                          font-size:12px;padding:4px 10px;border:1px solid var(--border);
                          border-radius:6px;color:var(--gray-700);text-decoration:none;background:var(--white)">
                  <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                  Unduh QR
                </a>
              </div>
              <div style="flex:1;min-width:200px">
                <div class="form-label" style="margin-bottom:4px">Short URL Anda</div>
                <div style="display:flex;align-items:center;gap:8px">
                  <input type="text" class="form-control" id="slPreviewUrl" readonly
                         style="font-weight:600;color:var(--primary);background:var(--white)">
                  <button type="button" onclick="slCopyPreviewUrl()"
                          style="padding:8px 12px;border:1px solid var(--border);border-radius:6px;
                                 background:var(--white);cursor:pointer;font-size:12px;white-space:nowrap">
                    Salin
                  </button>
                </div>
                <div id="slCopyOk" style="display:none;font-size:12px;color:#16a34a;margin-top:4px">✓ Disalin!</div>
              </div>
            </div>
          </div>

          <div style="margin-top:20px;display:flex;gap:10px">
            <button type="submit" class="btn btn-primary" id="slSubmitBtn">
              <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14" style="margin-right:6px"><path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z"/></svg>
              <span id="slSubmitTxt">Buat & Generate QR Code</span>
            </button>
            <button type="button" class="btn" onclick="slCloseForm()"
                    style="background:var(--gray-100);color:var(--gray-700);border:1px solid var(--border)">
              Batal
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Tabel -->
    <div style="background:var(--white);border:1px solid var(--border);border-radius:12px;overflow:hidden">
      <div style="padding:16px 20px;border-bottom:1px solid var(--border);
                  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <div style="font-weight:700;font-size:14px;color:var(--gray-800)">Daftar Short Link Saya</div>
        <button onclick="slLoadLinks()" style="display:inline-flex;align-items:center;gap:6px;
                padding:6px 12px;font-size:12px;border:1px solid var(--border);border-radius:6px;
                background:var(--white);color:var(--gray-600);cursor:pointer">
          <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13"><path d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
          Muat Ulang
        </button>
      </div>
      <div id="slTableArea" style="padding:0"></div>
    </div>

    <!-- Modal log akses -->
    <div id="slLogModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);
         z-index:1050;align-items:center;justify-content:center">
      <div style="background:var(--white);border-radius:12px;max-width:640px;width:95vw;
                  max-height:80vh;display:flex;flex-direction:column;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);
                    display:flex;align-items:center;justify-content:space-between">
          <div id="slLogTitle" style="font-weight:700;font-size:14px"></div>
          <button onclick="slCloseLogModal()" style="background:none;border:none;cursor:pointer;
                  color:var(--gray-400);padding:4px">
            <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
          </button>
        </div>
        <div id="slLogBody" style="padding:20px;overflow-y:auto"></div>
      </div>
    </div>

    <!-- Modal hapus -->
    <div id="slDelModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);
         z-index:1050;align-items:center;justify-content:center">
      <div style="background:var(--white);border-radius:12px;max-width:440px;width:95vw;padding:24px">
        <div style="font-weight:700;font-size:15px;margin-bottom:10px">Hapus Short Link</div>
        <p style="font-size:13px;color:var(--gray-600)">
          Apakah Anda yakin ingin menghapus short link ini? File QR Code juga akan dihapus.<br>
          <span style="font-size:12px;color:var(--gray-400)">Tindakan ini tidak dapat dibatalkan.</span>
        </p>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
          <button onclick="slCloseDelModal()"
                  style="padding:8px 18px;border:1px solid var(--border);border-radius:6px;
                         background:var(--white);cursor:pointer;font-size:13px">Batal</button>
          <button onclick="slDoDelete()"
                  style="padding:8px 18px;border:none;border-radius:6px;
                         background:#ef4444;color:#fff;cursor:pointer;font-size:13px;font-weight:600">
            Ya, Hapus
          </button>
        </div>
      </div>
    </div>
  `

  // Tutup modal saat klik overlay
  document.getElementById('slLogModal').addEventListener('click', e => {
    if (e.target.id === 'slLogModal') slCloseLogModal()
  })
  document.getElementById('slDelModal').addEventListener('click', e => {
    if (e.target.id === 'slDelModal') slCloseDelModal()
  })

  slLoadLinks()
}

// ── Tampilkan/tutup form ────────────────────────────────────────
function slOpenForm() {
  const card = document.getElementById('slFormCard')
  if (!card) return
  document.getElementById('slForm').reset()
  document.getElementById('slQrPreview').style.display = 'none'
  card.style.display = 'block'
  card.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
function slCloseForm() {
  const card = document.getElementById('slFormCard')
  if (card) card.style.display = 'none'
}

// ── Submit form ─────────────────────────────────────────────────
async function slSubmitForm(e) {
  e.preventDefault()
  const btn     = document.getElementById('slSubmitBtn')
  const btnTxt  = document.getElementById('slSubmitTxt')
  const longUrl = document.getElementById('slLongUrl').value.trim()
  const slug    = document.getElementById('slSlug').value.trim()
  const title   = document.getElementById('slTitle').value.trim()

  if (!longUrl) { showToast('URL asli wajib diisi.', 'error'); return }

  btn.disabled      = true
  btnTxt.textContent = 'Memproses…'

  try {
    const body = { long_url: longUrl }
    if (slug)  body.slug  = slug
    if (title) body.title = title

    const res    = await apiPost('short-links', body)
    const record = res.data

    // Tampilkan preview QR
    if (record.qr_url) {
      document.getElementById('slQrImg').src              = record.qr_url
      document.getElementById('slPreviewUrl').value       = record.short_url
      document.getElementById('slQrDownload').href        = record.qr_url
      document.getElementById('slQrDownload').download    = 'qr-' + record.slug + '.png'
      document.getElementById('slQrPreview').style.display = 'block'
    }

    showToast('Short link berhasil dibuat!', 'success')
    // Reset isian form tapi biarkan preview tetap terlihat
    document.getElementById('slLongUrl').value = ''
    document.getElementById('slSlug').value    = ''
    document.getElementById('slTitle').value   = ''

    slLoadLinks()
  } catch (err) {
    showToast(err.message || 'Gagal membuat short link.', 'error')
  } finally {
    btn.disabled       = false
    btnTxt.textContent = 'Buat & Generate QR Code'
  }
}

// ── Salin URL di preview ────────────────────────────────────────
function slCopyPreviewUrl() {
  const val = document.getElementById('slPreviewUrl')?.value || ''
  if (!val) return
  navigator.clipboard.writeText(val).then(() => {
    const ok = document.getElementById('slCopyOk')
    if (ok) { ok.style.display = 'block'; setTimeout(() => { ok.style.display = 'none' }, 2000) }
  }).catch(() => showToast('Gagal menyalin.', 'error'))
}

// ── Load & render tabel ─────────────────────────────────────────
async function slLoadLinks() {
  const area = document.getElementById('slTableArea')
  if (!area) return

  area.innerHTML = `<div style="padding:32px;text-align:center;color:var(--gray-400)">
    <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"
         style="animation:spin 1s linear infinite;display:inline-block">
      <path d="M12 4V1L8 5l4 4V6a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8z"/>
    </svg>
    <div style="margin-top:8px;font-size:13px">Memuat data…</div>
  </div>`

  try {
    const res = await apiGet('short-links')
    _slLinks  = res.data || []
    _slRenderTable(area, _slLinks)
  } catch (err) {
    area.innerHTML = `<p style="padding:24px;color:#ef4444;font-size:13px">Gagal memuat: ${_slEsc(err.message)}</p>`
  }
}

function _slRenderTable(area, links) {
  if (!links.length) {
    area.innerHTML = `
      <div style="padding:48px 24px;text-align:center;color:var(--gray-400)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40" style="opacity:.3">
          <path d="M17 7h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1 0 1.43-.98 2.63-2.31 2.98l1.46 1.46C20.88 15.61 22 13.87 22 12c0-2.76-2.24-5-5-5zm-1 4h-2.19l2 2H16v-2zM2 4.27l3.11 3.11A4.991 4.991 0 0 0 2 12c0 2.76 2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1 0-1.55 1.14-2.84 2.64-3.06L8.73 11H8v2h2.73l8 8L20 19.73 3.27 3 2 4.27z"/>
        </svg>
        <div style="margin-top:12px;font-size:14px;font-weight:600">Belum ada short link</div>
        <div style="font-size:12px;margin-top:4px">Klik "Buat Short Link" untuk memulai</div>
      </div>`
    return
  }

  area.innerHTML = `
    <div style="overflow-x:auto">
      <table style="width:100%;min-width:700px;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="background:var(--gray-50);border-bottom:1px solid var(--border)">
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;width:36px">#</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px">Judul / URL Asli</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;width:180px">Short Link</th>
            <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-600);font-size:12px;width:80px">QR Code</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;width:120px">Dibuat</th>
            <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-600);font-size:12px;width:70px">Klik</th>
            <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-600);font-size:12px;width:90px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          ${links.map((link, i) => _slRowHtml(link, i)).join('')}
        </tbody>
      </table>
    </div>`
}

function _slRowHtml(link, idx) {
  const shortUrl = link.short_url || ''
  const qrUrl    = link.qr_url    || ''
  const title    = link.title     ? `<div style="font-weight:600;margin-bottom:2px">${_slEsc(link.title)}</div>` : ''
  const longDisp = _slTrunc(link.long_url || '', 60)
  const created  = _slFmtDate(link.created_at)
  const klik     = Number(link.total_klik || 0)

  return `
    <tr style="border-bottom:1px solid var(--border);transition:background .15s"
        onmouseover="this.style.background='var(--gray-50)'"
        onmouseout="this.style.background=''">
      <td style="padding:10px 12px;color:var(--gray-400)">${idx + 1}</td>
      <td style="padding:10px 12px">
        ${title}
        <a href="${_slEsc(link.long_url)}" target="_blank" rel="noopener"
           style="font-size:11px;color:var(--gray-500);word-break:break-all"
           title="${_slEsc(link.long_url)}">${_slEsc(longDisp)}</a>
      </td>
      <td style="padding:10px 12px">
        <a href="${_slEsc(shortUrl)}" target="_blank" rel="noopener"
           style="font-size:12px;font-weight:600;color:var(--primary)">${_slEsc(shortUrl)}</a><br>
        <button onclick="slCopyText('${_slEsc(shortUrl)}', this)"
                style="background:none;border:none;font-size:11px;color:var(--gray-500);
                       cursor:pointer;padding:0;margin-top:2px">Salin</button>
      </td>
      <td style="padding:10px 12px;text-align:center">
        ${qrUrl
          ? `<a href="${_slEsc(qrUrl)}" download="qr-${_slEsc(link.slug)}.png" title="Unduh QR Code">
               <img src="${_slEsc(qrUrl)}" alt="QR" width="52" height="52"
                    style="border:1px solid var(--border);border-radius:4px;cursor:pointer">
             </a>`
          : '<span style="color:var(--gray-300)">—</span>'
        }
      </td>
      <td style="padding:10px 12px;font-size:12px;color:var(--gray-500)">${created}</td>
      <td style="padding:10px 12px;text-align:center">
        <span style="font-size:14px;font-weight:700;color:${klik > 0 ? 'var(--primary)' : 'var(--gray-400)'}">${klik.toLocaleString('id-ID')}</span><br>
        <button onclick="slShowLogs(${link.id}, '${_slEsc(shortUrl)}')"
                style="background:none;border:none;font-size:11px;color:var(--gray-500);cursor:pointer;padding:0">
          Detail
        </button>
      </td>
      <td style="padding:10px 12px;text-align:center">
        <button onclick="slConfirmDelete(${link.id})"
                style="padding:5px 10px;border:1px solid #fca5a5;border-radius:5px;
                       background:#fff1f2;color:#dc2626;cursor:pointer;font-size:12px">
          <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12"><path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
        </button>
      </td>
    </tr>`
}

// ── Salin teks ke clipboard ─────────────────────────────────────
function slCopyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.textContent
    btn.textContent = '✓ Disalin'
    setTimeout(() => { btn.textContent = orig }, 1500)
  }).catch(() => showToast('Gagal menyalin.', 'error'))
}

// ── Modal log akses ─────────────────────────────────────────────
async function slShowLogs(id, shortUrl) {
  const modal = document.getElementById('slLogModal')
  const title = document.getElementById('slLogTitle')
  const body  = document.getElementById('slLogBody')
  if (!modal) return

  title.textContent = 'Log Akses — ' + shortUrl
  body.innerHTML    = '<p style="text-align:center;color:var(--gray-400)">Memuat…</p>'
  modal.style.display = 'flex'

  try {
    const res   = await apiGet('short-links/' + id + '/logs?limit=100')
    const data  = res.data  || {}
    const logs  = data.logs || []
    const total = data.total_klik || 0

    if (!logs.length) {
      body.innerHTML = `
        <div style="text-align:center;padding:24px;color:var(--gray-400)">
          <div style="font-size:32px;font-weight:700;color:var(--primary)">${total}</div>
          <div style="font-size:12px;margin-top:4px">Total Klik</div>
          <div style="margin-top:16px;font-size:13px">Belum ada log akses tercatat.</div>
        </div>`
      return
    }

    body.innerHTML = `
      <div style="margin-bottom:12px;display:flex;align-items:center;gap:10px">
        <span style="font-size:22px;font-weight:700;color:var(--primary)">${total}</span>
        <span style="font-size:12px;color:var(--gray-500)">Total Klik</span>
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
          <thead>
            <tr style="background:var(--gray-50);border-bottom:1px solid var(--border)">
              <th style="padding:8px;text-align:left">#</th>
              <th style="padding:8px;text-align:left">Waktu</th>
              <th style="padding:8px;text-align:left">IP Address</th>
              <th style="padding:8px;text-align:left">Referrer</th>
              <th style="padding:8px;text-align:left">User Agent</th>
            </tr>
          </thead>
          <tbody>
            ${logs.map((log, i) => `
              <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:7px 8px;color:var(--gray-400)">${i + 1}</td>
                <td style="padding:7px 8px;white-space:nowrap">${_slEsc(log.accessed_at || '—')}</td>
                <td style="padding:7px 8px">${_slEsc(log.ip_address || '—')}</td>
                <td style="padding:7px 8px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="${_slEsc(log.referer || '')}">${_slEsc(_slTrunc(log.referer || '—', 28))}</td>
                <td style="padding:7px 8px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    title="${_slEsc(log.user_agent || '')}">${_slEsc(_slTrunc(log.user_agent || '—', 35))}</td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>`
  } catch (err) {
    body.innerHTML = `<p style="color:#ef4444">Gagal memuat log: ${_slEsc(err.message)}</p>`
  }
}

function slCloseLogModal() {
  const m = document.getElementById('slLogModal')
  if (m) m.style.display = 'none'
}

// ── Modal hapus ─────────────────────────────────────────────────
function slConfirmDelete(id) {
  _slDeleteTarget = id
  const m = document.getElementById('slDelModal')
  if (m) m.style.display = 'flex'
}
function slCloseDelModal() {
  const m = document.getElementById('slDelModal')
  if (m) m.style.display = 'none'
}
async function slDoDelete() {
  if (!_slDeleteTarget) return
  slCloseDelModal()
  try {
    await apiDelete('short-links/' + _slDeleteTarget)
    showToast('Short link berhasil dihapus.', 'success')
    _slDeleteTarget = null
    slLoadLinks()
  } catch (err) {
    showToast('Gagal menghapus: ' + err.message, 'error')
  }
}

// ── Util ────────────────────────────────────────────────────────
function _slEsc(str) {
  return String(str || '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')
}
function _slTrunc(str, n) { return str.length > n ? str.slice(0, n) + '…' : str }
function _slFmtDate(str) {
  if (!str) return '—'
  try { return new Date(str).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' }) }
  catch (_) { return str }
}
