<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Menghubungkan Google Calendar…</title>
  <script>
    // Inject dari PHP agar tidak hardcode path — sama persis dengan _head.php
    window.APP_BASE_URL = '<?= rtrim(base_url(), '/') ?>/';
    window.APP_API_URL  = '<?= base_url('api/v1/') ?>';
    window.APP_LOGIN_URL = '<?= base_url('login') ?>';
  </script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: -apple-system, "Segoe UI", system-ui, sans-serif;
      background: #f3f4f6;
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh;
    }
    .card {
      background: #fff;
      border-radius: 14px;
      padding: 40px 36px;
      width: 100%;
      max-width: 420px;
      text-align: center;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
    }
    .icon { font-size: 48px; margin-bottom: 16px; }
    .title {
      font-size: 18px; font-weight: 700;
      color: #1f2328; margin-bottom: 8px;
    }
    .sub {
      font-size: 13px; color: #6b7280;
      line-height: 1.6; margin-bottom: 20px;
    }
    .spinner {
      width: 36px; height: 36px;
      border: 3px solid #e5e7eb;
      border-top-color: #3b82d4;
      border-radius: 50%;
      animation: spin .8s linear infinite;
      margin: 0 auto 20px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .btn {
      display: inline-block; padding: 9px 22px;
      background: #3b82d4; color: #fff;
      border-radius: 7px; font-size: 13px;
      font-weight: 600; text-decoration: none;
      border: none; cursor: pointer;
    }
    .btn:hover { background: #2563eb; }
    .btn-secondary {
      background: #f3f4f6; color: #374151;
      margin-left: 8px;
    }
    .btn-secondary:hover { background: #e5e7eb; }
    .error-box {
      background: #fef2f2; border: 1px solid #fecaca;
      border-radius: 8px; padding: 12px 14px;
      font-size: 12px; color: #dc2626;
      text-align: left; margin-bottom: 18px;
      word-break: break-word;
    }
    .success-box {
      background: #f0fdf4; border: 1px solid #bbf7d0;
      border-radius: 8px; padding: 12px 14px;
      font-size: 12px; color: #166534;
      text-align: left; margin-bottom: 18px;
    }
    #statusArea { display: none; }
  </style>
</head>
<body>
  <div class="card">
    <div id="loadingArea">
      <div class="spinner"></div>
      <div class="title">Menghubungkan Google Calendar…</div>
      <div class="sub">Memproses otorisasi, mohon tunggu sebentar.</div>
    </div>

    <div id="statusArea">
      <div class="icon" id="statusIcon">✅</div>
      <div class="title" id="statusTitle"></div>
      <div id="statusDetail"></div>
      <div style="margin-top:16px">
        <button class="btn" onclick="window.close()">Tutup Tab</button>
        <a class="btn btn-secondary" id="backToMkaBtn" href="/">Kembali ke MKA</a>
      </div>
    </div>
  </div>

  <script>
    (async function () {
      const params   = new URLSearchParams(window.location.search)
      const code     = params.get('code')
      const state    = params.get('state')
      const errParam = params.get('error')

      // Set link "Kembali ke MKA" menggunakan base URL dari PHP
      const backBtn = document.getElementById('backToMkaBtn')
      if (backBtn && window.APP_BASE_URL) backBtn.href = window.APP_BASE_URL

      const loadEl   = document.getElementById('loadingArea')
      const statusEl = document.getElementById('statusArea')
      const iconEl   = document.getElementById('statusIcon')
      const titleEl  = document.getElementById('statusTitle')
      const detailEl = document.getElementById('statusDetail')

      function showResult(ok, title, detail) {
        loadEl.style.display   = 'none'
        statusEl.style.display = 'block'
        iconEl.textContent     = ok ? '✅' : '❌'
        titleEl.textContent    = title
        detailEl.innerHTML     = ok
          ? `<div class="success-box">${detail}</div>`
          : `<div class="error-box">${detail}</div>`
      }

      // Google mengembalikan error (misalnya user menolak akses)
      if (errParam) {
        showResult(false, 'Otorisasi Ditolak',
          'Google menolak permintaan akses: <strong>' + errParam + '</strong>.<br>' +
          'Silakan coba lagi dari halaman Pengaturan Google Calendar.')
        return
      }

      if (!code || !state) {
        showResult(false, 'Parameter Tidak Lengkap',
          'Callback dari Google tidak mengandung <code>code</code> atau <code>state</code>.<br>' +
          'Coba ulangi proses dari halaman Pengaturan.')
        return
      }

      // Ambil token MKA dari localStorage
      const mkaToken = localStorage.getItem('mka_token')
      if (!mkaToken) {
        showResult(false, 'Sesi Berakhir',
          'Token MKA tidak ditemukan di browser. Silakan login ulang ke MKA System, ' +
          'lalu coba hubungkan Google Calendar kembali.')
        return
      }

      try {
        // Gunakan APP_API_URL yang diinjeksi PHP — bukan hardcode '/'
        const apiUrl = (window.APP_API_URL || '/api/v1/') + 'schedule/google/callback'
        const res = await fetch(apiUrl, {
          method:  'POST',
          headers: {
            'Content-Type':  'application/json',
            'Authorization': 'Bearer ' + mkaToken,
          },
          body: JSON.stringify({ code, state }),
        })

        const json = await res.json()

        if (!res.ok || !json.status) {
          showResult(false, 'Koneksi Gagal',
            (json.message || 'Terjadi kesalahan saat menukar authorization code.') +
            '<br><small style="color:#9ca3af">HTTP ' + res.status + '</small>')
          return
        }

        const email = json.data?.email || '—'
        showResult(true, 'Google Calendar Terhubung!',
          'Berhasil terhubung sebagai <strong>' + email + '</strong>.<br>' +
          'Anda sekarang bisa menggunakan fitur sinkronisasi Google Calendar.<br>' +
          '<span style="color:#6b7280">Tab ini bisa ditutup.</span>')

        // Broadcast ke semua tab MKA yang terbuka agar refresh status
        try {
          localStorage.setItem('mka_gcal_connected', Date.now().toString())
        } catch (_) {}

        // Auto-tutup tab setelah 3 detik jika berhasil
        setTimeout(() => { try { window.close() } catch(_) {} }, 3000)

      } catch (e) {
        showResult(false, 'Koneksi Gagal',
          'Gagal menghubungi server MKA: <strong>' + e.message + '</strong>.<br>' +
          'Pastikan server MKA berjalan dan coba lagi.')
      }
    })()
  </script>
</body>
</html>
