<!-- =================================================================
     BIOLINK MANAGER — Partial SPA View
     Di-include dari app/Views/app.php dan ditampilkan via navigasi JS
     ================================================================= -->
<div id="page-biolink" class="page-section" style="display:none">

  <!-- ── Header ─────────────────────────────────────────────────────── -->
  <div class="page-header">
    <div>
      <h1 class="page-title">Biolink Manager</h1>
      <p class="page-sub">Kelola halaman multi-tautan publik direktorat Anda</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <a id="blPublikLink" href="#" target="_blank" rel="noopener noreferrer"
         class="btn btn-outline" style="display:none">
        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
          <path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
        </svg>
        Lihat Halaman Publik
      </a>
      <button class="btn btn-primary" id="blAddItemBtn">
        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
          <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
        </svg>
        Tambah Tautan
      </button>
    </div>
  </div>

  <!-- ================================================================ -->
  <!-- PANEL 1 — Pengaturan Profil                                       -->
  <!-- ================================================================ -->
  <div class="card" style="margin-bottom:20px">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
      <div class="card-title">Pengaturan Profil</div>
      <button class="btn-icon" id="blProfileToggle" title="Buka/tutup">
        <svg id="blProfileToggleIcon" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
          <path d="M7 10l5 5 5-5z"/>
        </svg>
      </button>
    </div>
    <div class="card-body" id="blProfileBody">
      <form id="blProfileForm" enctype="multipart/form-data" autocomplete="off">

        <!-- Foto & Info dasar -->
        <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;margin-bottom:20px">
          <!-- Avatar preview + upload -->
          <div style="flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:10px">
            <div id="blAvatarPreviewWrap" style="width:88px;height:88px;border-radius:50%;overflow:hidden;
                 border:2px solid var(--border);background:var(--gray-100);display:flex;
                 align-items:center;justify-content:center">
              <img id="blAvatarPreviewImg" src="" alt="Foto Profil"
                   style="width:100%;height:100%;object-fit:cover;display:none">
              <svg id="blAvatarPlaceholder" viewBox="0 0 24 24" fill="currentColor"
                   width="36" height="36" style="color:var(--gray-300)">
                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
              </svg>
            </div>
            <label class="btn btn-sm btn-outline" style="cursor:pointer;margin:0">
              Ganti Foto
              <input type="file" id="blFotoInput" name="foto" accept="image/*"
                     style="display:none" aria-label="Upload foto profil">
            </label>
            <small class="form-hint" style="text-align:center">JPG/PNG/WebP, maks 2 MB</small>
          </div>

          <!-- Field teks -->
          <div style="flex:1;min-width:240px;display:flex;flex-direction:column;gap:14px">
            <div class="form-group" style="margin:0">
              <label class="form-label">Nama Direktorat / Divisi <span class="required">*</span></label>
              <input type="text" class="form-control" id="blNama" name="nama"
                     placeholder="Direktorat Sistem dan Kebijakan" maxlength="255" required>
            </div>
            <div class="form-group" style="margin:0">
              <label class="form-label">Slug URL <span class="form-label-opt">(auto dari nama)</span></label>
              <div style="display:flex;align-items:center;gap:6px">
                <span style="font-size:12px;color:var(--gray-500);white-space:nowrap">/biolink/</span>
                <input type="text" class="form-control" id="blSlug" name="slug"
                       placeholder="direktorat-saya" maxlength="80"
                       pattern="[a-zA-Z0-9_\-]{3,80}"
                       style="flex:1">
              </div>
              <small class="form-hint">Huruf, angka, tanda hubung, dan underscore saja.</small>
            </div>
            <div class="form-group" style="margin:0">
              <label class="form-label">Warna Aksen</label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="color" id="blWarnaAksen" name="warna_aksen"
                       value="#1a6fb5" style="width:44px;height:36px;border:1px solid var(--border);
                       border-radius:6px;padding:2px;cursor:pointer">
                <input type="text" class="form-control" id="blWarnaAksenText"
                       placeholder="#1a6fb5" maxlength="7" style="width:100px">
                <span style="font-size:12px;color:var(--gray-400)">Warna tombol tautan</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Bio -->
        <div class="form-group">
          <label class="form-label">Bio / Deskripsi Singkat</label>
          <textarea class="form-control" id="blBio" name="bio" rows="3"
                    placeholder="Deskripsi singkat tentang direktorat atau unit Anda..."
                    maxlength="500"></textarea>
          <small class="form-hint">Maks. 500 karakter.</small>
        </div>

        <!-- Media Sosial -->
        <div class="form-group">
          <label class="form-label" style="display:flex;align-items:center;gap:8px;justify-content:space-between">
            <span>Tautan Media Sosial</span>
            <button type="button" class="btn btn-sm btn-outline" id="blAddSosmedBtn">+ Tambah</button>
          </label>
          <div id="blSosmedList" style="display:flex;flex-direction:column;gap:8px;margin-top:8px"></div>
        </div>

        <div class="form-actions" style="margin-top:4px">
          <button type="submit" class="btn btn-primary" id="blProfileSaveBtn">
            <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15">
              <path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z"/>
            </svg>
            Simpan Profil
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ================================================================ -->
  <!-- PANEL 2 — Daftar Tautan (Items)                                   -->
  <!-- ================================================================ -->
  <div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
      <div class="card-title">Daftar Tautan</div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-sm btn-outline" id="blRefreshItemsBtn" title="Muat ulang">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
            <path d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
          </svg>
          Muat Ulang
        </button>
        <button class="btn btn-sm btn-primary" id="blSaveOrderBtn" style="display:none">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
            <path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z"/>
          </svg>
          Simpan Urutan
        </button>
      </div>
    </div>
    <div class="card-body" style="padding:0">

      <!-- Loading -->
      <div id="blItemsLoading" style="padding:32px;text-align:center;color:var(--gray-400)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"
             style="animation:spin 1s linear infinite">
          <path d="M12 4V1L8 5l4 4V6a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8z"/>
        </svg>
        <div style="margin-top:8px;font-size:13px">Memuat data…</div>
      </div>

      <!-- Empty -->
      <div id="blItemsEmpty" style="display:none;padding:48px 24px;text-align:center;color:var(--gray-400)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40" style="opacity:.3">
          <path d="M17 7h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1H17l2.96 2.96.04-.06L23 12c0-2.76-2.24-5-5-5zM7 12c0-1.71 1.39-3.1 3.1-3.1H14V7H10C7.24 7 5 9.24 5 12s2.24 5 5 5h4v-1.9H10C8.29 15.1 7 13.71 7 12zm-1 1h2v-2H6v2zm12-1h-2v2h2v-2z"/>
        </svg>
        <div style="margin-top:12px;font-size:14px;font-weight:600">Belum ada tautan</div>
        <div style="font-size:12px;margin-top:4px">Klik "Tambah Tautan" untuk memulai</div>
      </div>

      <!-- Tabel Items -->
      <div id="blItemsTableWrap" style="display:none;overflow-x:auto">
        <table class="data-table" style="width:100%;min-width:620px">
          <thead>
            <tr>
              <th style="width:48px;text-align:center" title="Seret untuk mengurutkan">⇅</th>
              <th style="width:52px;text-align:center">Urutan</th>
              <th>Judul Tombol</th>
              <th>URL Tujuan</th>
              <th style="width:80px;text-align:center">Klik</th>
              <th style="width:90px;text-align:center">Status</th>
              <th style="width:100px;text-align:center">Aksi</th>
            </tr>
          </thead>
          <tbody id="blItemsTbody"></tbody>
        </table>
      </div>

    </div>
  </div>

  <!-- ================================================================ -->
  <!-- MODAL — Tambah / Edit Tautan                                      -->
  <!-- ================================================================ -->
  <div id="blItemModal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:520px;width:95vw">
      <div class="modal-header">
        <div class="modal-title" id="blItemModalTitle">Tambah Tautan</div>
        <button class="modal-close" id="blItemModalClose">
          <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <form id="blItemForm" autocomplete="off">
          <input type="hidden" id="blItemId">

          <div class="form-group">
            <label class="form-label">Judul Tombol <span class="required">*</span></label>
            <input type="text" class="form-control" id="blItemJudul"
                   placeholder="Contoh: Portal Layanan, Website Resmi" maxlength="255" required>
          </div>

          <div class="form-group">
            <label class="form-label">URL Tujuan <span class="required">*</span></label>
            <input type="url" class="form-control" id="blItemUrl"
                   placeholder="https://contoh.go.id/layanan" required>
            <small class="form-hint">Masukkan URL lengkap termasuk https://</small>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-group">
              <label class="form-label">Ikon <span class="form-label-opt">(Emoji)</span></label>
              <input type="text" class="form-control" id="blItemIkon"
                     placeholder="🌐" maxlength="10">
              <small class="form-hint">Paste emoji pilihan Anda</small>
            </div>
            <div class="form-group">
              <label class="form-label">Urutan</label>
              <input type="number" class="form-control" id="blItemUrutan"
                     value="0" min="0" max="9999">
              <small class="form-hint">Angka lebih kecil = tampil lebih atas</small>
            </div>
          </div>

          <!-- Toggle: gunakan URL Shortener untuk analytics -->
          <div class="form-group" id="blShortLinkGroup">
            <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
              <input type="checkbox" id="blUseShortLink" style="margin-top:3px;flex-shrink:0">
              <div>
                <div style="font-weight:600;font-size:13px">Aktifkan Click Tracking (URL Shortener)</div>
                <div style="font-size:12px;color:var(--gray-400);margin-top:2px">
                  Sistem akan otomatis membuat short link dan mencatat setiap klik tombol ini ke database.
                </div>
              </div>
            </label>
          </div>

        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" id="blItemModalCancel">Batal</button>
        <button class="btn btn-primary" id="blItemModalSave">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
            <path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z"/>
          </svg>
          <span id="blItemModalSaveTxt">Simpan</span>
        </button>
      </div>
    </div>
  </div>

  <!-- ================================================================ -->
  <!-- MODAL — Konfirmasi Hapus                                          -->
  <!-- ================================================================ -->
  <div id="blDeleteModal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:420px;width:95vw">
      <div class="modal-header">
        <div class="modal-title">Hapus Tautan</div>
        <button class="modal-close" id="blDeleteModalClose">
          <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <p>Apakah Anda yakin ingin menghapus tautan <strong id="blDeleteItemName"></strong>?</p>
        <p style="font-size:12px;color:var(--gray-400);margin-top:4px">Tindakan ini tidak dapat dibatalkan.</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" id="blDeleteCancelBtn">Batal</button>
        <button class="btn btn-danger" id="blDeleteConfirmBtn">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
            <path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
          </svg>
          Ya, Hapus
        </button>
      </div>
    </div>
  </div>

</div><!-- /#page-biolink -->
