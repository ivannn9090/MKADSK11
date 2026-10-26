/* ============================================================
   biolink.js — Biolink Manager (Multi-Card)

   Setiap card = satu halaman publik dengan slug-nya sendiri.
   Satu user bisa punya banyak card; setiap card punya banyak link.

   UI: daftar card → klik card → panel link di bawahnya expand
   ============================================================ */
'use strict'

// ── State ────────────────────────────────────────────────────────
let _blCards        = []       // array semua card user
let _blActiveCardId = null     // card yang sedang dibuka / diedit
let _blItems        = {}       // cache items per cardId: { 5: [...] }
let _blDeleteTarget = null     // { type:'card'|'item', cardId, itemId?, nama }
let _blEditItemId   = null     // null = mode tambah

// ── Entry point ──────────────────────────────────────────────────
async function renderBiolink() {
  const content = document.getElementById('mainContent')

  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:flex-start;
                margin-bottom:20px;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-size:18px;font-weight:700;color:var(--gray-800)">Biolink Manager</div>
        <div style="font-size:12px;color:var(--gray-500);margin-top:2px">
          Kelola halaman multi-tautan publik. Setiap card memiliki URL publik tersendiri.
        </div>
      </div>
      <button class="btn btn-primary" onclick="blOpenCardModal(null)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15" style="margin-right:6px">
          <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
        </svg>
        Buat Card Baru
      </button>
    </div>

    <!-- Daftar Card -->
    <div id="blCardList"></div>

    <!-- ── MODAL CARD (buat / edit) ──────────────────────── -->
    <div id="blCardModal" style="display:none;position:fixed;inset:0;
         background:rgba(0,0,0,.5);z-index:500;align-items:center;
         justify-content:center;padding:70px 16px 24px">
      <div style="background:var(--white);border-radius:12px;width:100%;max-width:520px;
                  box-shadow:0 8px 32px rgba(0,0,0,.2);display:flex;flex-direction:column;
                  max-height:calc(100vh - 94px)">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);flex-shrink:0;
                    display:flex;align-items:center;justify-content:space-between">
          <div id="blCardModalTitle" style="font-weight:700;font-size:15px;color:var(--gray-800)">
            Buat Card Baru
          </div>
          <button onclick="blCloseCardModal()"
                  style="background:none;border:none;cursor:pointer;color:var(--gray-400);
                         padding:4px;border-radius:6px;line-height:0">
            <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
              <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
          </button>
        </div>
        <div style="padding:20px;overflow-y:auto;flex:1">
          <input type="hidden" id="blCardId">

          <!-- Foto + Nama -->
          <div style="display:flex;gap:16px;align-items:flex-start;margin-bottom:16px">
            <div style="flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:8px">
              <div style="width:72px;height:72px;border-radius:50%;overflow:hidden;
                           border:2px solid var(--border);background:var(--gray-100);
                           display:flex;align-items:center;justify-content:center">
                <img id="blCardAvatarImg" src="" alt=""
                     style="width:100%;height:100%;object-fit:cover;display:none">
                <svg id="blCardAvatarPh" viewBox="0 0 24 24" fill="currentColor"
                     width="30" height="30" style="color:var(--gray-300)">
                  <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                </svg>
              </div>
              <label style="font-size:11px;padding:4px 8px;border:1px solid var(--border);
                             border-radius:6px;cursor:pointer;background:var(--gray-50);color:var(--gray-700)">
                Foto
                <input type="file" id="blCardFoto" accept="image/*" style="display:none"
                       onchange="blPreviewCardFoto(this)">
              </label>
            </div>
            <div style="flex:1;display:flex;flex-direction:column;gap:12px">
              <div>
                <label class="form-label">Nama Card <span style="color:#ef4444">*</span></label>
                <input type="text" class="form-control" id="blCardNama"
                       placeholder="Misal: Workshop 2025, Portal Layanan" maxlength="255">
              </div>
              <div>
                <label class="form-label">Slug URL
                  <span style="font-size:11px;color:var(--gray-400)">(kosongkan = auto)</span>
                </label>
                <div style="display:flex;align-items:center;gap:5px">
                  <span style="font-size:12px;color:var(--gray-400);white-space:nowrap">/biolink/</span>
                  <input type="text" class="form-control" id="blCardSlug"
                         placeholder="workshop-2025" maxlength="80"
                         pattern="[a-zA-Z0-9_\\-]{3,80}" style="flex:1">
                </div>
                <small style="font-size:11px;color:var(--gray-400)">Huruf, angka, - dan _.</small>
              </div>
            </div>
          </div>

          <!-- Cover image -->
          <div style="margin-bottom:14px">
            <label class="form-label">Cover / Banner</label>
            <div id="blCardCoverPreviewWrap" style="width:100%;height:100px;border-radius:8px;
                 overflow:hidden;border:1.5px dashed var(--border);background:var(--gray-50);
                 display:flex;align-items:center;justify-content:center;margin-bottom:8px;
                 position:relative;cursor:pointer" onclick="document.getElementById('blCardCover').click()">
              <img id="blCardCoverImg" src="" alt=""
                   style="width:100%;height:100%;object-fit:cover;display:none;position:absolute;inset:0">
              <div id="blCardCoverPh" style="display:flex;flex-direction:column;align-items:center;
                   gap:4px;color:var(--gray-400);font-size:12px;pointer-events:none">
                <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                  <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-1.1 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                </svg>
                Klik untuk pilih gambar cover
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <label style="font-size:11px;padding:4px 10px;border:1px solid var(--border);
                             border-radius:6px;cursor:pointer;background:var(--gray-50);color:var(--gray-700)">
                Pilih Cover
                <input type="file" id="blCardCover" accept="image/*" style="display:none"
                       onchange="blPreviewCardCover(this)">
              </label>
              <button type="button" onclick="blRemoveCardCover()"
                      id="blCardCoverRemoveBtn" style="display:none;font-size:11px;padding:4px 10px;
                      border:1px solid #ef4444;border-radius:6px;cursor:pointer;
                      background:transparent;color:#ef4444">
                Hapus Cover
              </button>
              <span style="font-size:11px;color:var(--gray-400)">JPG/PNG/WebP, maks 4 MB. Rasio 3:1 ideal.</span>
            </div>
          </div>

          <!-- Background halaman -->
          <div style="margin-bottom:14px">
            <label class="form-label">Background Halaman</label>
            <div id="blCardBgPreviewWrap" style="width:100%;height:80px;border-radius:8px;
                 overflow:hidden;border:1.5px dashed var(--border);background:var(--gray-50);
                 display:flex;align-items:center;justify-content:center;margin-bottom:8px;
                 position:relative;cursor:pointer" onclick="document.getElementById('blCardBg').click()">
              <img id="blCardBgImg" src="" alt=""
                   style="width:100%;height:100%;object-fit:cover;display:none;position:absolute;inset:0">
              <div id="blCardBgPh" style="display:flex;flex-direction:column;align-items:center;
                   gap:4px;color:var(--gray-400);font-size:12px;pointer-events:none">
                <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                  <path d="M1 5h2v14H1zm4 0h2v14H5zm17 0H10c-.55 0-1 .45-1 1v12c0 .55.45 1 1 1h12c.55 0 1-.45 1-1V6c0-.55-.45-1-1-1zm-1 12H11V7h10v10zm-3.57-4.38l-2 2.57L14 13.47l-2 2.53h8z"/>
                </svg>
                Klik untuk pilih gambar background
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <label style="font-size:11px;padding:4px 10px;border:1px solid var(--border);
                             border-radius:6px;cursor:pointer;background:var(--gray-50);color:var(--gray-700)">
                Pilih Background
                <input type="file" id="blCardBg" accept="image/*" style="display:none"
                       onchange="blPreviewCardBg(this)">
              </label>
              <button type="button" onclick="blRemoveCardBg()"
                      id="blCardBgRemoveBtn" style="display:none;font-size:11px;padding:4px 10px;
                      border:1px solid #ef4444;border-radius:6px;cursor:pointer;
                      background:transparent;color:#ef4444">
                Hapus Background
              </button>
              <span style="font-size:11px;color:var(--gray-400)">JPG/PNG/WebP, maks 6 MB.</span>
            </div>
          </div>

          <div style="margin-bottom:14px">
            <label class="form-label">Deskripsi Singkat</label>
            <textarea class="form-control" id="blCardBio" rows="2" maxlength="500"
                      placeholder="Keterangan singkat tentang card ini…"></textarea>
          </div>

          <div style="margin-bottom:14px">
            <label class="form-label">Warna Aksen</label>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="color" id="blCardWarna" value="#1a6fb5"
                     oninput="document.getElementById('blCardWarnaText').value=this.value"
                     style="width:40px;height:34px;border:1px solid var(--border);
                            border-radius:6px;padding:2px;cursor:pointer">
              <input type="text" class="form-control" id="blCardWarnaText"
                     value="#1a6fb5" maxlength="7" style="width:90px"
                     oninput="if(/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(this.value)) document.getElementById('blCardWarna').value=this.value">
            </div>
          </div>

          <!-- Media Sosial -->
          <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
              <label class="form-label" style="margin:0">Tautan Media Sosial</label>
              <button type="button" onclick="blAddSosmedRow()"
                      style="font-size:12px;padding:3px 10px;border:1px solid var(--border);
                             border-radius:6px;background:var(--gray-50);cursor:pointer;color:var(--gray-700)">
                + Tambah
              </button>
            </div>
            <div id="blCardSosmedList" style="display:flex;flex-direction:column;gap:6px"></div>
          </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid var(--border);flex-shrink:0;
                    display:flex;justify-content:flex-end;gap:8px">
          <button onclick="blCloseCardModal()"
                  style="padding:8px 16px;border:1px solid var(--border);border-radius:8px;
                         background:var(--gray-50);color:var(--gray-700);cursor:pointer;font-size:13px">
            Batal
          </button>
          <button onclick="blSubmitCard()" class="btn btn-primary" id="blCardSaveBtn">
            <span id="blCardSaveTxt">Buat Card</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ── MODAL LINK (tambah / edit link) ───────────────── -->
    <div id="blItemModal" style="display:none;position:fixed;inset:0;
         background:rgba(0,0,0,.5);z-index:500;align-items:center;
         justify-content:center;padding:70px 16px 24px">
      <div style="background:var(--white);border-radius:12px;width:100%;max-width:480px;
                  box-shadow:0 8px 32px rgba(0,0,0,.2);display:flex;flex-direction:column;
                  max-height:calc(100vh - 94px)">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);flex-shrink:0;
                    display:flex;align-items:center;justify-content:space-between">
          <div id="blItemModalTitle" style="font-weight:700;font-size:15px;color:var(--gray-800)">
            Tambah Link
          </div>
          <button onclick="blCloseItemModal()"
                  style="background:none;border:none;cursor:pointer;color:var(--gray-400);
                         padding:4px;border-radius:6px;line-height:0">
            <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
              <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
          </button>
        </div>
        <div style="padding:20px;overflow-y:auto;flex:1">
          <input type="hidden" id="blItemId">
          <div style="margin-bottom:14px">
            <label class="form-label">Judul Tombol <span style="color:#ef4444">*</span></label>
            <input type="text" class="form-control" id="blItemJudul"
                   placeholder="Contoh: Website Resmi, Portal Layanan" maxlength="255">
          </div>
          <div style="margin-bottom:14px">
            <label class="form-label">URL Tujuan <span style="color:#ef4444">*</span></label>
            <input type="url" class="form-control" id="blItemUrl"
                   placeholder="https://contoh.go.id/layanan">
            <small style="font-size:11px;color:var(--gray-400)">Sertakan https://</small>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
            <div>
              <label class="form-label">Ikon
                <span style="font-size:11px;color:var(--gray-400)">(Emoji)</span>
              </label>
              <input type="text" class="form-control" id="blItemIkon"
                     placeholder="🌐" maxlength="10">
            </div>
            <div>
              <label class="form-label">Urutan</label>
              <input type="number" class="form-control" id="blItemUrutan"
                     value="0" min="0" max="9999">
              <small style="font-size:11px;color:var(--gray-400)">Kecil = lebih atas</small>
            </div>
          </div>
          <div id="blItemTrackingWrap"
               style="background:var(--gray-50);border:1px solid var(--border);
                      border-radius:8px;padding:12px 14px">
            <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin:0">
              <input type="checkbox" id="blItemTracking"
                     style="margin-top:3px;flex-shrink:0;width:15px;height:15px;
                            accent-color:var(--primary);cursor:pointer">
              <div>
                <div style="font-weight:600;font-size:13px;color:var(--gray-800)">
                  Aktifkan Click Tracking
                </div>
                <div style="font-size:12px;color:var(--gray-500);margin-top:2px;line-height:1.5">
                  Buat short link otomatis untuk mencatat setiap klik tombol ini.
                </div>
              </div>
            </label>
          </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid var(--border);flex-shrink:0;
                    display:flex;justify-content:flex-end;gap:8px">
          <button onclick="blCloseItemModal()"
                  style="padding:8px 16px;border:1px solid var(--border);border-radius:8px;
                         background:var(--gray-50);color:var(--gray-700);cursor:pointer;font-size:13px">
            Batal
          </button>
          <button onclick="blSubmitItem()" class="btn btn-primary" id="blItemSaveBtn">
            <span id="blItemSaveTxt">Simpan</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ── MODAL HAPUS ─────────────────────────────────── -->
    <div id="blDeleteModal" style="display:none;position:fixed;inset:0;
         background:rgba(0,0,0,.5);z-index:500;align-items:center;
         justify-content:center;padding:70px 16px 24px">
      <div style="background:var(--white);border-radius:12px;width:100%;max-width:400px;
                  box-shadow:0 8px 32px rgba(0,0,0,.2)">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);
                    display:flex;align-items:center;justify-content:space-between">
          <div id="blDeleteTitle" style="font-weight:700;font-size:15px;color:var(--gray-800)">
            Hapus
          </div>
          <button onclick="blCloseDeleteModal()"
                  style="background:none;border:none;cursor:pointer;color:var(--gray-400);
                         padding:4px;border-radius:6px;line-height:0">
            <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
              <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
          </button>
        </div>
        <div style="padding:20px">
          <p style="font-size:14px;color:var(--gray-700);line-height:1.5">
            Hapus <strong id="blDeleteName"></strong>? Tindakan ini tidak dapat dibatalkan.
          </p>
        </div>
        <div style="padding:12px 20px;border-top:1px solid var(--border);
                    display:flex;justify-content:flex-end;gap:8px">
          <button onclick="blCloseDeleteModal()"
                  style="padding:8px 16px;border:1px solid var(--border);border-radius:8px;
                         background:var(--gray-50);color:var(--gray-700);cursor:pointer;font-size:13px">
            Batal
          </button>
          <button onclick="blConfirmDelete()" id="blDeleteConfirmBtn"
                  style="padding:8px 16px;border:none;border-radius:8px;
                         background:#ef4444;color:#fff;cursor:pointer;font-size:13px;font-weight:600">
            Ya, Hapus
          </button>
        </div>
      </div>
    </div>`

  await blLoadCards()
}

// ── API helper ───────────────────────────────────────────────────
function _blApi(method, path, body) {
  const opts = { method }
  if (body instanceof FormData) {
    opts.body = body
  } else if (body !== null && body !== undefined) {
    opts.body = JSON.stringify(body)
  }
  return api('biolink/' + path, opts)
}

// ================================================================
// CARDS — Load & Render
// ================================================================

async function blLoadCards() {
  const list = document.getElementById('blCardList')
  if (!list) return
  list.innerHTML = `<div style="padding:32px;text-align:center;color:var(--gray-400)">
    <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"
         style="animation:spin 1s linear infinite">
      <path d="M12 4V1L8 5l4 4V6a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8z"/>
    </svg>
    <div style="margin-top:8px;font-size:13px">Memuat…</div>
  </div>`

  const res = await _blApi('GET', 'cards')
  if (!res) return
  if (!res.status) { showToast(res.message || 'Gagal memuat card.', 'error'); list.innerHTML = ''; return }

  _blCards = res.data || []

  if (_blCards.length === 0) {
    list.innerHTML = `<div style="padding:48px 24px;text-align:center;color:var(--gray-400);
      background:var(--white);border:1px solid var(--border);border-radius:12px">
      <svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40" style="opacity:.3">
        <path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7C4.24 7 2 9.24 2 12s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1 0 1.71-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/>
      </svg>
      <div style="margin-top:12px;font-size:14px;font-weight:600">Belum ada card</div>
      <div style="font-size:12px;margin-top:4px">Klik "Buat Card Baru" untuk memulai</div>
    </div>`
    return
  }

  list.innerHTML = ''
  _blCards.forEach(card => list.appendChild(_blBuildCardEl(card)))
}

function _blBuildCardEl(card) {
  const wrap = document.createElement('div')
  wrap.id = `blCard-${card.id}`
  wrap.style.cssText = `background:var(--white);border:1px solid var(--border);
    border-radius:12px;margin-bottom:14px;overflow:hidden;
    opacity:${card.is_active ? '1' : '.6'}`

  const accentColor = card.warna_aksen || '#1a6fb5'
  const initials    = (card.nama || '?').charAt(0).toUpperCase()
  const fotoHtml    = card.foto_url
    ? `<img src="${esc(card.foto_url)}" style="width:100%;height:100%;object-fit:cover">`
    : `<div style="width:100%;height:100%;background:${esc(accentColor)};display:flex;
         align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff">
         ${initials}</div>`

  wrap.innerHTML = `
    <!-- Card Header -->
    <div style="padding:14px 16px;display:flex;align-items:center;gap:14px;
                border-left:4px solid ${esc(accentColor)}">
      <div style="width:48px;height:48px;border-radius:50%;overflow:hidden;
                   flex-shrink:0;border:2px solid var(--border)">${fotoHtml}</div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:14px;color:var(--gray-800);
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          ${esc(card.nama)}
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin-top:3px;flex-wrap:wrap">
          <a href="${esc(card.publik_url || '')}" target="_blank" rel="noopener noreferrer"
             style="font-size:11px;color:var(--primary);text-decoration:none"
             title="Buka halaman publik">
            /biolink/${esc(card.slug)}
            <svg viewBox="0 0 24 24" fill="currentColor" width="10" height="10" style="vertical-align:middle">
              <path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
            </svg>
          </a>
          <span style="font-size:11px;color:var(--gray-400)">
            ${card.total_item || 0} link
          </span>
          <span style="font-size:10px;font-weight:600;padding:1px 7px;border-radius:10px;
                       background:${card.is_active ? '#d1fae5' : '#fee2e2'};
                       color:${card.is_active ? '#065f46' : '#991b1b'}">
            ${card.is_active ? 'Aktif' : 'Nonaktif'}
          </span>
        </div>
      </div>
      <!-- Aksi card -->
      <div style="display:flex;gap:4px;flex-shrink:0">
        <button onclick="blOpenCardModal(${card.id})"
                style="background:none;border:none;cursor:pointer;color:var(--primary);
                       padding:6px;border-radius:6px" title="Edit card">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
          </svg>
        </button>
        <button onclick="blToggleCard(${card.id})"
                style="background:none;border:none;cursor:pointer;
                       color:${card.is_active ? '#f59e0b' : '#10b981'};
                       padding:6px;border-radius:6px"
                title="${card.is_active ? 'Nonaktifkan' : 'Aktifkan'} card">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
            <path d="${card.is_active
              ? 'M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46A11.804 11.804 0 0 0 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z'
              : 'M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z'}"/>
          </svg>
        </button>
        <button onclick="blConfirmDeleteCard(${card.id},'${esc(card.nama).replace(/'/g,"\\'")}' )"
                style="background:none;border:none;cursor:pointer;color:#ef4444;
                       padding:6px;border-radius:6px" title="Hapus card">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
            <path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
          </svg>
        </button>
        <!-- Toggle expand/collapse link list -->
        <button onclick="blToggleCardLinks(${card.id}, this)"
                style="background:none;border:none;cursor:pointer;color:var(--gray-500);
                       padding:6px;border-radius:6px" title="Kelola link">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
            <path d="M7 10l5 5 5-5z"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Link list panel (collapse default) -->
    <div id="blLinks-${card.id}" style="display:none;border-top:1px solid var(--border)">
      <div style="padding:10px 16px;display:flex;align-items:center;justify-content:space-between;
                  background:var(--gray-50)">
        <span style="font-size:12px;font-weight:600;color:var(--gray-600)">Daftar Link</span>
        <button onclick="blOpenItemModal(${card.id}, null)"
                style="font-size:12px;padding:5px 12px;border:none;border-radius:6px;
                       background:${esc(accentColor)};color:#fff;cursor:pointer;font-weight:600">
          + Tambah Link
        </button>
      </div>
      <div id="blLinkBody-${card.id}" style="padding:0">
        <div style="padding:20px;text-align:center;color:var(--gray-400);font-size:13px">
          Memuat link…
        </div>
      </div>
    </div>`

  return wrap
}

async function blToggleCardLinks(cardId, btn) {
  const panel = document.getElementById(`blLinks-${cardId}`)
  if (!panel) return
  const isOpen = panel.style.display !== 'none'
  panel.style.display = isOpen ? 'none' : ''

  // Ganti ikon panah
  btn.innerHTML = isOpen
    ? `<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M7 10l5 5 5-5z"/></svg>`
    : `<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M7 14l5-5 5 5z"/></svg>`

  if (!isOpen) {
    await blLoadItems(cardId)
  }
}

// ================================================================
// ITEMS — Load & Render
// ================================================================

async function blLoadItems(cardId) {
  const body = document.getElementById(`blLinkBody-${cardId}`)
  if (!body) return

  body.innerHTML = `<div style="padding:16px;text-align:center;color:var(--gray-400);font-size:13px">
    Memuat link…
  </div>`

  const res = await _blApi('GET', `cards/${cardId}/items`)
  if (!res) return
  if (!res.status) {
    body.innerHTML = `<div style="padding:16px;color:#ef4444;font-size:13px">${esc(res.message || 'Gagal memuat.')}</div>`
    return
  }

  const items = res.data || []
  _blItems[String(cardId)] = items

  if (items.length === 0) {
    body.innerHTML = `<div style="padding:24px;text-align:center;color:var(--gray-400);font-size:13px">
      Belum ada link. Klik "+ Tambah Link" untuk menambahkan.
    </div>`
    return
  }

  // Tabel link
  body.innerHTML = `<div style="overflow-x:auto">
    <table style="width:100%;min-width:500px;border-collapse:collapse">
      <thead>
        <tr style="background:var(--gray-50)">
          <th style="padding:8px 12px;text-align:left;font-size:11px;color:var(--gray-500);
                     font-weight:600;border-bottom:1px solid var(--border);width:44px;text-align:center">⇅</th>
          <th style="padding:8px 12px;text-align:left;font-size:11px;color:var(--gray-500);
                     font-weight:600;border-bottom:1px solid var(--border)">Judul / URL</th>
          <th style="padding:8px 12px;text-align:center;font-size:11px;color:var(--gray-500);
                     font-weight:600;border-bottom:1px solid var(--border);width:70px">Status</th>
          <th style="padding:8px 12px;text-align:center;font-size:11px;color:var(--gray-500);
                     font-weight:600;border-bottom:1px solid var(--border);width:70px">Aksi</th>
        </tr>
      </thead>
      <tbody id="blLinkTbody-${cardId}"></tbody>
    </table>
  </div>`

  const tbody = document.getElementById(`blLinkTbody-${cardId}`)
  items.forEach(item => tbody.appendChild(_blBuildItemRow(cardId, item)))
}

function _blBuildItemRow(cardId, item) {
  const tr = document.createElement('tr')
  tr.dataset.id   = item.id
  tr.style.cssText = `border-bottom:1px solid var(--border);opacity:${item.is_active ? '1' : '.5'}`
  tr.innerHTML = `
    <td style="padding:8px 12px;text-align:center;color:var(--gray-300);cursor:grab;font-size:14px">☰</td>
    <td style="padding:8px 12px">
      <div style="font-size:13px;font-weight:600;color:var(--gray-800)">
        ${item.ikon ? `<span style="margin-right:5px">${esc(item.ikon)}</span>` : ''}
        ${esc(item.judul)}
      </div>
      <a href="${esc(item.url_tujuan)}" target="_blank" rel="noopener noreferrer"
         style="font-size:11px;color:var(--primary);word-break:break-all">
        ${esc(item.url_tujuan.length > 55 ? item.url_tujuan.slice(0,55)+'…' : item.url_tujuan)}
      </a>
      ${item.short_link_id
        ? '<div style="font-size:10px;color:var(--primary);margin-top:1px">📊 tracking aktif</div>'
        : ''}
    </td>
    <td style="padding:8px 12px;text-align:center">
      <label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;font-size:11px">
        <input type="checkbox" ${item.is_active ? 'checked' : ''}
               onchange="blToggleItem(${cardId},${item.id})"
               style="accent-color:var(--primary);width:14px;height:14px">
        <span style="color:var(--gray-500)">${item.is_active ? 'Aktif' : 'Nonaktif'}</span>
      </label>
    </td>
    <td style="padding:8px 12px;text-align:center">
      <div style="display:flex;gap:2px;justify-content:center">
        <button onclick="blOpenItemModal(${cardId},${item.id})"
                style="background:none;border:none;cursor:pointer;color:var(--primary);padding:4px"
                title="Edit">
          <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15">
            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
          </svg>
        </button>
        <button onclick="blConfirmDeleteItem(${cardId},${item.id},'${esc(item.judul).replace(/'/g,"\\'")}' )"
                style="background:none;border:none;cursor:pointer;color:#ef4444;padding:4px"
                title="Hapus">
          <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15">
            <path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
          </svg>
        </button>
      </div>
    </td>`
  return tr
}

// ================================================================
// MODAL CARD
// ================================================================

function blOpenCardModal(cardId) {
  const modal   = document.getElementById('blCardModal')
  const title   = document.getElementById('blCardModalTitle')
  const saveTxt = document.getElementById('blCardSaveTxt')
  const set     = (id, v) => { const el = document.getElementById(id); if (el) el.value = v }

  // Reset
  set('blCardId', ''); set('blCardNama', ''); set('blCardSlug', ''); set('blCardBio', '')
  set('blCardWarna', '#1a6fb5'); set('blCardWarnaText', '#1a6fb5')
  const sosmedList = document.getElementById('blCardSosmedList')
  if (sosmedList) sosmedList.innerHTML = ''
  const img = document.getElementById('blCardAvatarImg')
  const ph  = document.getElementById('blCardAvatarPh')
  if (img) { img.src = ''; img.style.display = 'none' }
  if (ph)  ph.style.display = ''
  // Reset cover
  const coverImg = document.getElementById('blCardCoverImg')
  const coverPh  = document.getElementById('blCardCoverPh')
  const coverRmv = document.getElementById('blCardCoverRemoveBtn')
  const coverInput = document.getElementById('blCardCover')
  if (coverImg) { coverImg.src = ''; coverImg.style.display = 'none' }
  if (coverPh)  coverPh.style.display = ''
  if (coverRmv) coverRmv.style.display = 'none'
  if (coverInput) coverInput.value = ''
  // Reset background
  const bgImg   = document.getElementById('blCardBgImg')
  const bgPh    = document.getElementById('blCardBgPh')
  const bgRmv   = document.getElementById('blCardBgRemoveBtn')
  const bgInput = document.getElementById('blCardBg')
  if (bgImg)   { bgImg.src = ''; bgImg.style.display = 'none' }
  if (bgPh)    bgPh.style.display = ''
  if (bgRmv)   bgRmv.style.display = 'none'
  if (bgInput) bgInput.value = ''

  if (cardId) {
    const card = _blCards.find(c => String(c.id) === String(cardId))
    if (!card) return
    title.textContent   = 'Edit Card'
    saveTxt.textContent = 'Simpan'
    set('blCardId',       card.id)
    set('blCardNama',     card.nama    || '')
    set('blCardSlug',     card.slug    || '')
    set('blCardBio',      card.bio     || '')
    set('blCardWarna',    card.warna_aksen || '#1a6fb5')
    set('blCardWarnaText', card.warna_aksen || '#1a6fb5')
    if (card.foto_url) {
      if (img) { img.src = card.foto_url; img.style.display = 'block' }
      if (ph)  ph.style.display = 'none'
    }
    if (card.cover_url) {
      if (coverImg) { coverImg.src = card.cover_url; coverImg.style.display = 'block' }
      if (coverPh)  coverPh.style.display = 'none'
      if (coverRmv) coverRmv.style.display = ''
    }
    if (card.bg_url) {
      if (bgImg) { bgImg.src = card.bg_url; bgImg.style.display = 'block' }
      if (bgPh)  bgPh.style.display = 'none'
      if (bgRmv) bgRmv.style.display = ''
    }
    const arr = Array.isArray(card.sosmed_json) ? card.sosmed_json : []
    arr.forEach(sm => blAddSosmedRow(sm.platform, sm.url))
  } else {
    title.textContent   = 'Buat Card Baru'
    saveTxt.textContent = 'Buat Card'
  }

  modal.style.display = 'flex'
}

function blCloseCardModal() {
  const modal = document.getElementById('blCardModal')
  if (modal) modal.style.display = 'none'
}

async function blSubmitCard() {
  const cardId = document.getElementById('blCardId')?.value
  const nama   = document.getElementById('blCardNama')?.value?.trim()
  if (!nama) { showToast('Nama card wajib diisi.', 'error'); return }

  const btn = document.getElementById('blCardSaveBtn')
  if (btn) btn.disabled = true

  try {
    const fd = new FormData()
    fd.append('nama',        nama)
    fd.append('slug',        document.getElementById('blCardSlug')?.value?.trim() || '')
    fd.append('bio',         document.getElementById('blCardBio')?.value?.trim()  || '')
    fd.append('warna_aksen', document.getElementById('blCardWarna')?.value        || '#1a6fb5')

    const fotoFile = document.getElementById('blCardFoto')?.files?.[0]
    if (fotoFile) fd.append('foto', fotoFile)

    const coverFile = document.getElementById('blCardCover')?.files?.[0]
    if (coverFile) fd.append('cover', coverFile)

    const bgFile = document.getElementById('blCardBg')?.files?.[0]
    if (bgFile) fd.append('bg', bgFile)

    const sosmedArr = []
    document.querySelectorAll('#blCardSosmedList .bl-sosmed-row').forEach(row => {
      const plat = row.querySelector('.bl-sosmed-plat')?.value?.trim()
      const url  = row.querySelector('.bl-sosmed-url')?.value?.trim()
      if (plat && url) sosmedArr.push({ platform: plat, url })
    })
    fd.append('sosmed_json', JSON.stringify(sosmedArr))

    const path = cardId ? `cards/${cardId}` : 'cards'
    const res  = await _blApi('POST', path, fd)

    if (!res || !res.status) {
      showToast(res?.message || 'Gagal menyimpan card.', 'error')
      return
    }

    showToast(cardId ? 'Card berhasil diperbarui.' : 'Card berhasil dibuat.', 'success')
    blCloseCardModal()
    await blLoadCards()
  } catch (e) {
    showToast('Terjadi kesalahan.', 'error')
  } finally {
    if (btn) btn.disabled = false
  }
}

async function blToggleCard(cardId) {
  const res = await _blApi('PATCH', `cards/${cardId}/toggle`, {})
  if (!res || !res.status) { showToast(res?.message || 'Gagal.', 'error'); return }
  showToast('Status card diperbarui.', 'success')
  await blLoadCards()
}

// ================================================================
// MODAL ITEM
// ================================================================

function blOpenItemModal(cardId, itemId) {
  _blActiveCardId = String(cardId)
  _blEditItemId   = itemId ? String(itemId) : null

  const modal   = document.getElementById('blItemModal')
  const title   = document.getElementById('blItemModalTitle')
  const saveTxt = document.getElementById('blItemSaveTxt')
  const trkWrap = document.getElementById('blItemTrackingWrap')
  const set     = (id, v) => { const el = document.getElementById(id); if (el) el.value = v }

  set('blItemId', ''); set('blItemJudul', ''); set('blItemUrl', '')
  set('blItemIkon', '')
  const chk = document.getElementById('blItemTracking')
  if (chk) chk.checked = false

  if (itemId) {
    const items = _blItems[String(cardId)] || []
    const item  = items.find(i => String(i.id) === String(itemId))
    if (!item) return
    title.textContent   = 'Edit Link'
    saveTxt.textContent = 'Perbarui'
    if (trkWrap) trkWrap.style.display = 'none'  // tracking sudah set saat buat
    set('blItemId',     item.id)
    set('blItemJudul',  item.judul)
    set('blItemUrl',    item.url_tujuan)
    set('blItemIkon',   item.ikon || '')
    set('blItemUrutan', item.urutan)
  } else {
    title.textContent   = 'Tambah Link'
    saveTxt.textContent = 'Simpan'
    if (trkWrap) trkWrap.style.display = ''
    const items = _blItems[String(cardId)] || []
    set('blItemUrutan', items.length)
  }

  modal.style.display = 'flex'
}

function blCloseItemModal() {
  const modal = document.getElementById('blItemModal')
  if (modal) modal.style.display = 'none'
  _blEditItemId   = null
  _blActiveCardId = null
}

async function blSubmitItem() {
  const judul    = document.getElementById('blItemJudul')?.value?.trim()
  const url      = document.getElementById('blItemUrl')?.value?.trim()
  const ikon     = document.getElementById('blItemIkon')?.value?.trim()
  const urutan   = parseInt(document.getElementById('blItemUrutan')?.value || '0')
  const tracking = document.getElementById('blItemTracking')?.checked
  const cardId   = _blActiveCardId

  if (!judul) { showToast('Judul tombol wajib diisi.', 'error'); return }
  if (!url)   { showToast('URL tujuan wajib diisi.', 'error');   return }
  if (!cardId) return

  const btn = document.getElementById('blItemSaveBtn')
  if (btn) btn.disabled = true

  try {
    let res
    if (_blEditItemId) {
      res = await _blApi('PUT', `cards/${cardId}/items/${_blEditItemId}`,
                         { judul, url_tujuan: url, ikon, urutan })
    } else {
      res = await _blApi('POST', `cards/${cardId}/items`,
                         { judul, url_tujuan: url, ikon, urutan, use_short_link: tracking ? 1 : 0 })
    }

    if (!res || !res.status) { showToast(res?.message || 'Gagal menyimpan.', 'error'); return }

    showToast(_blEditItemId ? 'Link diperbarui.' : 'Link ditambahkan.', 'success')
    blCloseItemModal()
    await blLoadItems(cardId)
    // Perbarui counter di card header
    await blLoadCards()
  } catch (e) {
    showToast('Terjadi kesalahan.', 'error')
  } finally {
    if (btn) btn.disabled = false
  }
}

async function blToggleItem(cardId, itemId) {
  const res = await _blApi('PATCH', `cards/${cardId}/items/${itemId}/toggle`, {})
  if (!res || !res.status) { showToast(res?.message || 'Gagal.', 'error'); return }
  await blLoadItems(cardId)
}

// ================================================================
// HAPUS (Card & Item)
// ================================================================

function blConfirmDeleteCard(cardId, nama) {
  _blDeleteTarget = { type: 'card', cardId, nama }
  document.getElementById('blDeleteTitle').textContent = 'Hapus Card'
  document.getElementById('blDeleteName').textContent  = `"${nama}"`
  document.getElementById('blDeleteModal').style.display = 'flex'
}

function blConfirmDeleteItem(cardId, itemId, nama) {
  _blDeleteTarget = { type: 'item', cardId, itemId, nama }
  document.getElementById('blDeleteTitle').textContent = 'Hapus Link'
  document.getElementById('blDeleteName').textContent  = `"${nama}"`
  document.getElementById('blDeleteModal').style.display = 'flex'
}

function blCloseDeleteModal() {
  document.getElementById('blDeleteModal').style.display = 'none'
  _blDeleteTarget = null
}

async function blConfirmDelete() {
  if (!_blDeleteTarget) return
  const btn = document.getElementById('blDeleteConfirmBtn')
  if (btn) btn.disabled = true

  try {
    const t = _blDeleteTarget
    let res
    if (t.type === 'card') {
      res = await _blApi('DELETE', `cards/${t.cardId}`, null)
    } else {
      res = await _blApi('DELETE', `cards/${t.cardId}/items/${t.itemId}`, null)
    }

    if (!res || !res.status) { showToast(res?.message || 'Gagal menghapus.', 'error'); return }
    showToast(t.type === 'card' ? 'Card berhasil dihapus.' : 'Link berhasil dihapus.', 'success')
    blCloseDeleteModal()
    if (t.type === 'card') {
      await blLoadCards()
    } else {
      await blLoadItems(t.cardId)
      await blLoadCards()
    }
  } catch (e) {
    showToast('Terjadi kesalahan.', 'error')
  } finally {
    if (btn) btn.disabled = false
  }
}

// ================================================================
// SOSMED ROW & UTILS
// ================================================================

function blAddSosmedRow(platform = '', url = '') {
  const list = document.getElementById('blCardSosmedList')
  if (!list) return
  const platforms = [
    ['website','🌐 Website'],['instagram','📸 Instagram'],['twitter','🐦 Twitter/X'],
    ['facebook','👤 Facebook'],['youtube','▶ YouTube'],['linkedin','💼 LinkedIn'],
    ['tiktok','🎵 TikTok'],['whatsapp','💬 WhatsApp'],['telegram','✈ Telegram'],['email','✉ Email'],
  ]
  const opts = platforms.map(([v,l]) =>
    `<option value="${v}"${platform===v?' selected':''}>${l}</option>`).join('')

  const row = document.createElement('div')
  row.className = 'bl-sosmed-row'
  row.style.cssText = 'display:flex;gap:6px;align-items:center'
  row.innerHTML = `
    <select class="form-control bl-sosmed-plat" style="width:130px;flex-shrink:0">${opts}</select>
    <input type="url" class="form-control bl-sosmed-url"
           value="${url.replace(/"/g,'&quot;')}" placeholder="https://…" style="flex:1">
    <button type="button" onclick="this.closest('.bl-sosmed-row').remove()"
            style="background:none;border:none;cursor:pointer;color:var(--gray-400);
                   padding:4px;flex-shrink:0;line-height:0">
      <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
      </svg>
    </button>`
  list.appendChild(row)
}

function blPreviewCardFoto(input) {
  const file = input.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = e => {
    const img = document.getElementById('blCardAvatarImg')
    const ph  = document.getElementById('blCardAvatarPh')
    if (img) { img.src = e.target.result; img.style.display = 'block' }
    if (ph)  ph.style.display = 'none'
  }
  reader.readAsDataURL(file)
}

function blPreviewCardCover(input) {
  const file = input.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = e => {
    const img = document.getElementById('blCardCoverImg')
    const ph  = document.getElementById('blCardCoverPh')
    const rmv = document.getElementById('blCardCoverRemoveBtn')
    if (img) { img.src = e.target.result; img.style.display = 'block' }
    if (ph)  ph.style.display = 'none'
    if (rmv) rmv.style.display = ''
  }
  reader.readAsDataURL(file)
}

function blRemoveCardCover() {
  const img   = document.getElementById('blCardCoverImg')
  const ph    = document.getElementById('blCardCoverPh')
  const rmv   = document.getElementById('blCardCoverRemoveBtn')
  const input = document.getElementById('blCardCover')
  if (img)   { img.src = ''; img.style.display = 'none' }
  if (ph)    ph.style.display = ''
  if (rmv)   rmv.style.display = 'none'
  if (input) input.value = ''
}

function blPreviewCardBg(input) {
  const file = input.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = e => {
    const img = document.getElementById('blCardBgImg')
    const ph  = document.getElementById('blCardBgPh')
    const rmv = document.getElementById('blCardBgRemoveBtn')
    if (img) { img.src = e.target.result; img.style.display = 'block' }
    if (ph)  ph.style.display = 'none'
    if (rmv) rmv.style.display = ''
  }
  reader.readAsDataURL(file)
}

function blRemoveCardBg() {
  const img   = document.getElementById('blCardBgImg')
  const ph    = document.getElementById('blCardBgPh')
  const rmv   = document.getElementById('blCardBgRemoveBtn')
  const input = document.getElementById('blCardBg')
  if (img)   { img.src = ''; img.style.display = 'none' }
  if (ph)    ph.style.display = ''
  if (rmv)   rmv.style.display = 'none'
  if (input) input.value = ''
}
