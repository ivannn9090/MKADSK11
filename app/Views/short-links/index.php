<!-- ================================================================
     SHORT LINKS — URL Shortener & QR Code Generator
     Partial view — di-render sebagai tab/page di SPA shell
     ================================================================ -->
<div id="page-short-links" class="page-section" style="display:none">

  <!-- ── Header ─────────────────────────────────────────────── -->
  <div class="page-header">
    <div>
      <h1 class="page-title">URL Shortener & QR Code</h1>
      <p class="page-sub">Buat tautan pendek dan QR Code secara instan</p>
    </div>
    <button class="btn btn-primary" id="btnOpenShortLinkForm">
      <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
      Buat Short Link
    </button>
  </div>

  <!-- ── Form Card (tersembunyi secara default) ─────────────── -->
  <div id="shortLinkFormCard" class="card" style="display:none;margin-bottom:20px">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
      <div class="card-title" id="shortLinkFormTitle">Buat Short Link Baru</div>
      <button type="button" class="btn-icon" id="btnCloseShortLinkForm" title="Tutup form">
        <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
      </button>
    </div>
    <div class="card-body">
      <form id="shortLinkForm" autocomplete="off">
        <input type="hidden" id="slEditId" value="">

        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">URL Asli (Long URL) <span class="required">*</span></label>
            <input type="url" class="form-control" id="slLongUrl"
                   placeholder="https://contoh.com/halaman-sangat-panjang/artikel?param=nilai"
                   required>
            <small class="form-hint">Masukkan URL lengkap yang ingin dipersingkat.</small>
          </div>

          <div class="form-group">
            <label class="form-label">Custom Slug <span class="form-label-opt">(Opsional)</span></label>
            <div style="display:flex;gap:6px">
              <span class="input-prefix" style="
                display:flex;align-items:center;padding:0 10px;
                background:var(--gray-100);border:1px solid var(--border);
                border-right:none;border-radius:6px 0 0 6px;
                font-size:12px;color:var(--gray-500);white-space:nowrap;flex-shrink:0">
                s/
              </span>
              <input type="text" class="form-control" id="slSlug"
                     placeholder="contoh-saya"
                     pattern="[a-zA-Z0-9_\-]{3,64}"
                     maxlength="64"
                     style="border-radius:0 6px 6px 0">
            </div>
            <small class="form-hint">Kosongkan untuk slug acak. Huruf, angka, - dan _ saja.</small>
          </div>

          <div class="form-group">
            <label class="form-label">Judul <span class="form-label-opt">(Opsional)</span></label>
            <input type="text" class="form-control" id="slTitle"
                   placeholder="Nama/deskripsi singkat link ini" maxlength="255">
          </div>
        </div>

        <!-- Area preview QR setelah berhasil dibuat -->
        <div id="slQrPreviewArea" style="display:none;margin-top:16px;padding:16px;
             background:var(--gray-50);border:1px solid var(--border);border-radius:8px">
          <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
            <div style="text-align:center">
              <img id="slQrPreviewImg" src="" alt="QR Code"
                   style="width:140px;height:140px;border:1px solid var(--border);border-radius:6px;display:block">
              <a id="slQrDownloadBtn" href="#" download class="btn btn-sm btn-outline"
                 style="margin-top:8px;display:inline-flex;align-items:center;gap:5px">
                <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                Unduh QR
              </a>
            </div>
            <div style="flex:1;min-width:200px">
              <div class="form-label" style="margin-bottom:4px">Short URL Anda</div>
              <div style="display:flex;align-items:center;gap:8px">
                <input type="text" class="form-control" id="slPreviewShortUrl" readonly
                       style="font-weight:600;color:var(--primary);background:var(--white)">
                <button type="button" class="btn btn-sm btn-outline" id="slCopyShortUrlBtn"
                        title="Salin ke clipboard">
                  <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/></svg>
                </button>
              </div>
              <div id="slCopySuccess" style="display:none;font-size:12px;color:var(--success);margin-top:4px">
                ✓ Berhasil disalin!
              </div>
            </div>
          </div>
        </div>

        <div class="form-actions" style="margin-top:20px;display:flex;gap:10px">
          <button type="submit" class="btn btn-primary" id="slSubmitBtn">
            <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z"/></svg>
            <span id="slSubmitBtnText">Buat & Generate QR Code</span>
          </button>
          <button type="button" class="btn btn-outline" id="btnCancelShortLink">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Tabel Daftar Short Links ───────────────────────────── -->
  <div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
      <div class="card-title">Daftar Short Link Saya</div>
      <button class="btn btn-sm btn-outline" id="btnRefreshShortLinks" title="Muat ulang">
        <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
        Muat Ulang
      </button>
    </div>
    <div class="card-body" style="padding:0">

      <!-- Loading state -->
      <div id="slTableLoading" style="padding:32px;text-align:center;color:var(--gray-400)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24" class="spin" style="animation:spin 1s linear infinite"><path d="M12 4V1L8 5l4 4V6a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8z"/></svg>
        <div style="margin-top:8px;font-size:13px">Memuat data…</div>
      </div>

      <!-- Empty state -->
      <div id="slTableEmpty" style="display:none;padding:48px 24px;text-align:center;color:var(--gray-400)">
        <svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40" style="opacity:.35"><path d="M17 7h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1 0 1.43-.98 2.63-2.31 2.98l1.46 1.46C20.88 15.61 22 13.87 22 12c0-2.76-2.24-5-5-5zm-1 4h-2.19l2 2H16v-2zM2 4.27l3.11 3.11A4.991 4.991 0 0 0 2 12c0 2.76 2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1 0-1.55 1.14-2.84 2.64-3.06L8.73 11H8v2h2.73l8 8L20 19.73 3.27 3 2 4.27z"/></svg>
        <div style="margin-top:12px;font-size:14px;font-weight:600">Belum ada short link</div>
        <div style="font-size:12px;margin-top:4px">Klik "Buat Short Link" untuk memulai</div>
      </div>

      <!-- Table -->
      <div id="slTableWrapper" style="display:none;overflow-x:auto">
        <table class="data-table" style="width:100%;min-width:700px">
          <thead>
            <tr>
              <th style="width:36px">#</th>
              <th>Judul / URL Asli</th>
              <th style="width:160px">Short Link</th>
              <th style="width:80px;text-align:center">QR Code</th>
              <th style="width:130px">Dibuat</th>
              <th style="width:80px;text-align:center">Klik</th>
              <th style="width:100px;text-align:center">Aksi</th>
            </tr>
          </thead>
          <tbody id="slTableBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── Modal detail log akses ─────────────────────────────── -->
  <div id="slLogModal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:640px;width:95vw">
      <div class="modal-header">
        <div class="modal-title" id="slLogModalTitle">Log Akses</div>
        <button class="modal-close" id="slLogModalClose">
          <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <div id="slLogModalContent" style="overflow-x:auto"></div>
      </div>
    </div>
  </div>

  <!-- ── Modal konfirmasi hapus ─────────────────────────────── -->
  <div id="slDeleteModal" class="modal-overlay" style="display:none">
    <div class="modal" style="max-width:440px;width:95vw">
      <div class="modal-header">
        <div class="modal-title">Hapus Short Link</div>
        <button class="modal-close" id="slDeleteModalClose">
          <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <p>Apakah Anda yakin ingin menghapus short link ini? File QR Code juga akan dihapus.</p>
        <p style="font-size:12px;color:var(--gray-400);margin-top:4px">Tindakan ini tidak dapat dibatalkan.</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" id="slDeleteCancelBtn">Batal</button>
        <button class="btn btn-danger" id="slDeleteConfirmBtn">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
          Ya, Hapus
        </button>
      </div>
    </div>
  </div>

</div><!-- /#page-short-links -->
