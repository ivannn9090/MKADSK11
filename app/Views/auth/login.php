<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Monitoring Kegiatan dan Anggaran</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,"Segoe UI",system-ui,sans-serif;background:#f0f4ff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px}
.card{background:#fff;border-radius:16px;box-shadow:0 4px 32px rgba(30,64,175,.12);width:100%;max-width:440px;padding:36px 32px}
.logo{text-align:center;margin-bottom:24px}
.logo-icon{width:56px;height:56px;background:linear-gradient(135deg,#1e40af,#3b82f6);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px}
.logo-icon svg{width:30px;height:30px;fill:#fff}
h1{font-size:21px;font-weight:700;color:#1e3a8a;text-align:center;margin-bottom:3px}
.subtitle{text-align:center;color:#64748b;font-size:12.5px;margin-bottom:20px}
.form-group{margin-bottom:14px}
label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:5px}
input[type=text],input[type=email],input[type=password],input[type=tel],select{width:100%;border:1.5px solid #d1d5db;border-radius:10px;padding:10px 13px;font-size:14px;color:#111827;outline:none;transition:border-color .2s;background:#fff}
input:focus,select:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.btn-main{width:100%;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;border:none;border-radius:10px;padding:12px;font-size:15px;font-weight:600;cursor:pointer;transition:opacity .2s;margin-top:4px;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-main:hover{opacity:.9}
.btn-main:disabled{opacity:.55;cursor:not-allowed}
.btn-link{background:none;border:none;color:#3b82f6;font-size:13px;cursor:pointer;padding:0;text-decoration:underline}
.btn-ghost{background:none;border:1.5px solid #d1d5db;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;color:#374151;cursor:pointer;transition:.15s}
.btn-ghost:hover{background:#f1f5ff;border-color:#93c5fd}
.err{display:none;margin-top:10px;padding:10px 14px;background:#fef2f2;border:1.5px solid #fecaca;border-radius:10px;
     display:none;align-items:flex-start;gap:10px;font-size:13px;color:#b91c1c;line-height:1.5}
.err-icon{flex-shrink:0;width:18px;height:18px;margin-top:1px}
.err-text{flex:1}
@keyframes shake{0%,100%{transform:translateX(0)}15%,45%,75%{transform:translateX(-6px)}30%,60%,90%{transform:translateX(6px)}}
@keyframes fadeInDown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.shake{animation:shake .45s ease}
.err.visible{display:flex;animation:fadeInDown .2s ease}
input.input-error{border-color:#f87171!important;box-shadow:0 0 0 3px rgba(239,68,68,.12)!important;background:#fff8f8}
.ok{color:#15803d;font-size:12.5px;margin-top:5px;padding:8px 12px;background:#f0fdf4;border-radius:8px;display:none}
.demo{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 14px;margin-top:14px;font-size:12px;color:#1e40af}
.demo b{display:block;margin-bottom:6px;font-size:12.5px}
.demo-row{display:flex;align-items:center;justify-content:space-between;padding:3px 0;border-bottom:1px solid #dbeafe;gap:8px}
.demo-row:last-child{border:none}
.demo-role{font-weight:700;min-width:90px;color:#1e3a8a;font-size:11.5px}
.demo-cred{color:#374151;font-size:11.5px;flex:1}
.demo-fill{background:none;border:none;color:#3b82f6;font-size:11px;cursor:pointer;padding:0 0 0 4px;text-decoration:underline;white-space:nowrap}
.demo-fill:hover{color:#1e40af}
.spinner{display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.divider{border:none;border-top:1px solid #e5e7eb;margin:16px 0}
.info-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;font-size:12.5px;color:#1e40af;margin-bottom:16px;line-height:1.6}
.success-box{text-align:center;padding:8px 0}
.success-icon{width:64px;height:64px;background:linear-gradient(135deg,#059669,#10b981);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px}
.success-icon svg{width:32px;height:32px;fill:#fff}
</style>
</head>
<body>
<div class="card">

  <!-- =========================================================
       STEP 0 — Form Login
  ========================================================= -->
  <div id="stepLogin">
    <div class="logo">
      <div class="logo-icon"><svg viewBox="0 0 24 24"><path d="M3 3h18v18H3V3zm2 2v14h14V5H5zm2 2h10v2H7V7zm0 4h10v2H7v-2zm0 4h6v2H7v-2z"/></svg></div>
      <h1>Monitoring Kegiatan</h1>
      <p class="subtitle">Sistem Monitoring Kegiatan dan Anggaran</p>
    </div>

    <form id="loginForm">
      <div class="form-group">
        <label for="loginEmail">Email</label>
        <input type="email" id="loginEmail" placeholder="email@instansi.go.id" required autocomplete="username">
      </div>
      <div class="form-group">
        <label for="loginPassword">Password</label>
        <input type="password" id="loginPassword" placeholder="Password" required autocomplete="current-password">
      </div>
      <div class="err" id="loginError" role="alert">
        <svg class="err-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        <span class="err-text"></span>
      </div>
      <button type="submit" class="btn-main" id="loginBtn">
        <div class="spinner" id="loginSpinner"></div>
        <span id="loginBtnText">Masuk</span>
      </button>
    </form>

    <div style="text-align:center;margin-top:14px">
      <span style="font-size:13px;color:#64748b">Belum punya akun? </span>
      <button class="btn-link" onclick="showStep('register')">Daftar Akun Baru</button>
    </div>

    <!-- Demo Accounts -->
    <div class="demo">
      <b>🔑 Akun Demo — password: <code style="background:#dbeafe;padding:0 4px;border-radius:4px">password123</code></b>
      <div class="demo-row">
        <span class="demo-role">Admin</span>
        <span class="demo-cred">admin@instansi.go.id</span>
        <button class="demo-fill" type="button" onclick="fillLogin('admin@instansi.go.id')">Pakai</button>
      </div>
      <div class="demo-row">
        <span class="demo-role">Direktur</span>
        <span class="demo-cred">direktur@instansi.go.id</span>
        <button class="demo-fill" type="button" onclick="fillLogin('direktur@instansi.go.id')">Pakai</button>
      </div>
      <div class="demo-row">
        <span class="demo-role">Ketua Tim</span>
        <span class="demo-cred">ketua.tim@instansi.go.id</span>
        <button class="demo-fill" type="button" onclick="fillLogin('ketua.tim@instansi.go.id')">Pakai</button>
      </div>
      <div class="demo-row">
        <span class="demo-role">Anggota Tim</span>
        <span class="demo-cred">anggota@instansi.go.id</span>
        <button class="demo-fill" type="button" onclick="fillLogin('anggota@instansi.go.id')">Pakai</button>
      </div>
      <div class="demo-row">
        <span class="demo-role">Perencana</span>
        <span class="demo-cred">perencana@instansi.go.id</span>
        <button class="demo-fill" type="button" onclick="fillLogin('perencana@instansi.go.id')">Pakai</button>
      </div>
    </div>

  </div>

  <!-- =========================================================
       STEP 1 — Form Registrasi
  ========================================================= -->
  <div id="stepRegister" style="display:none">
    <div class="logo">
      <div class="logo-icon"><svg viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div>
      <h1>Daftar Akun Klien</h1>
      <p class="subtitle">Layanan Telaah Konsep — ANRI</p>
    </div>

    <div class="info-box">
      ℹ️ Akun Anda akan <strong>diverifikasi oleh Tata Usaha</strong> sebelum dapat digunakan.
      Anda akan dihubungi setelah verifikasi selesai.
    </div>

    <form id="registerForm" autocomplete="off">
      <div class="form-group">
        <label>Nama Lengkap <span style="color:#ef4444">*</span></label>
        <input type="text" id="regNama" placeholder="Nama lengkap sesuai identitas" required>
      </div>
      <div class="form-group">
        <label>Email Instansi <span style="color:#ef4444">*</span></label>
        <input type="email" id="regEmail" placeholder="nama@anri.go.id" required autocomplete="username">
      </div>
      <div class="form-group">
        <label>Unit Kerja <span style="color:#ef4444">*</span></label>
        <input type="text" id="regUnitKerja" placeholder="Contoh: Deputi Bidang Tata Kelola Kearsipan" required>
      </div>
      <div class="form-group">
        <label>Jabatan</label>
        <input type="text" id="regJabatan" placeholder="Jabatan Anda (opsional)">
      </div>
      <div class="form-group">
        <label>No. Telepon</label>
        <input type="tel" id="regTelepon" placeholder="Nomor HP aktif (opsional)">
      </div>
      <div class="form-group">
        <label>Password <span style="color:#ef4444">*</span></label>
        <input type="password" id="regPass" placeholder="Minimal 8 karakter" required minlength="8" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label>Konfirmasi Password <span style="color:#ef4444">*</span></label>
        <input type="password" id="regPassConf" placeholder="Ulangi password" required autocomplete="new-password">
      </div>
      <div class="err" id="registerError" role="alert">
        <svg class="err-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        <span class="err-text"></span>
      </div>
      <button type="submit" class="btn-main" id="registerBtn">
        <div class="spinner" id="registerSpinner"></div>
        <span id="registerBtnText">Kirim Permohonan Pendaftaran</span>
      </button>
    </form>

    <hr class="divider">
    <div style="text-align:center">
      <button class="btn-link" onclick="showStep('login')">← Kembali ke Login</button>
    </div>
  </div>

  <!-- =========================================================
       STEP 2 — Berhasil Daftar (menunggu verifikasi TU)
  ========================================================= -->
  <div id="stepSuccess" style="display:none">
    <div class="success-box">
      <div class="success-icon">
        <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
      </div>
      <h1 style="margin-bottom:8px">Pendaftaran Terkirim!</h1>
      <p style="font-size:13.5px;color:#374151;line-height:1.7;margin-bottom:20px">
        Data Anda telah kami terima. Akun sedang menunggu verifikasi oleh
        <strong>Tata Usaha Direktorat Sistem Kearsipan</strong>.<br><br>
        Setelah diverifikasi, Anda dapat login dan mengajukan telaah konsep.
      </p>
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px;text-align:left;margin-bottom:20px;font-size:13px">
        <div style="font-weight:700;color:#166534;margin-bottom:8px">Detail Pendaftaran:</div>
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #dcfce7">
          <span style="color:#6b7280">Nama</span>
          <span id="successNama" style="font-weight:600;color:#111827"></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #dcfce7">
          <span style="color:#6b7280">Email</span>
          <span id="successEmail" style="font-weight:600;color:#111827"></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:4px 0">
          <span style="color:#6b7280">Unit Kerja</span>
          <span id="successUnit" style="font-weight:600;color:#111827;text-align:right;max-width:60%"></span>
        </div>
      </div>
      <button class="btn-main" onclick="showStep('login')" style="background:linear-gradient(135deg,#059669,#10b981)">
        ← Kembali ke Halaman Login
      </button>
    </div>
  </div>

</div><!-- .card -->

<script>
const BASE     = '<?= base_url('api/v1/') ?>';
const DASH_URL = '<?= base_url() ?>';

// ---------------------------------------------------------------
// Fill demo credentials
// ---------------------------------------------------------------
function fillLogin(email) {
  document.getElementById('loginEmail').value    = email;
  document.getElementById('loginPassword').value = 'password123';
  document.getElementById('loginEmail').focus();
}

// ---------------------------------------------------------------
// Step management
// ---------------------------------------------------------------
function showStep(step) {
  ['stepLogin','stepRegister','stepSuccess'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = (id === 'step' + step.charAt(0).toUpperCase() + step.slice(1)) ? '' : 'none';
  });
  clearErrors();
}

function clearErrors() {
  ['loginError','registerError'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('visible');
    const textEl = el.querySelector('.err-text') || el;
    textEl.textContent = '';
  });
  // Bersihkan state error pada input
  ['loginEmail','loginPassword'].forEach(fid => {
    const inp = document.getElementById(fid);
    if (inp) inp.classList.remove('input-error');
  });
}

function showErr(id, msg) {
  const el = document.getElementById(id); if (!el) return;
  const textEl = el.querySelector('.err-text') || el;
  textEl.textContent = msg;
  // Reset animasi dulu agar bisa re-trigger jika error muncul lagi
  el.classList.remove('visible', 'shake');
  void el.offsetWidth; // reflow
  el.classList.add('visible');

  // Shake input fields jika error login
  if (id === 'loginError') {
    ['loginEmail','loginPassword'].forEach(fid => {
      const inp = document.getElementById(fid);
      if (!inp) return;
      inp.classList.add('input-error', 'shake');
      inp.addEventListener('animationend', () => inp.classList.remove('shake'), { once: true });
    });
  }
}

// ---------------------------------------------------------------
// Login
// ---------------------------------------------------------------
// Bersihkan state error saat user mulai mengetik ulang
['loginEmail','loginPassword'].forEach(fid => {
  document.getElementById(fid)?.addEventListener('input', () => {
    document.getElementById(fid).classList.remove('input-error');
    const errEl = document.getElementById('loginError');
    if (errEl) errEl.classList.remove('visible');
  });
});

document.getElementById('loginForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn  = document.getElementById('loginBtn');
  const spin = document.getElementById('loginSpinner');
  const txt  = document.getElementById('loginBtnText');
  clearErrors();
  btn.disabled = true; spin.style.display = 'block'; txt.style.display = 'none';

  try {
    const res  = await fetch(BASE + 'auth/login', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        email:    document.getElementById('loginEmail').value.trim(),
        password: document.getElementById('loginPassword').value,
      })
    });
    const data = await res.json();
    if (data.status) {
      // Backend sudah memblokir klien yang belum diverifikasi (HTTP 403)
      // Jika sampai sini berarti login berhasil — simpan token dan redirect
      localStorage.setItem('mka_token',       data.data.token);
      localStorage.setItem('mka_user',        JSON.stringify(data.data.user));
      localStorage.setItem('mka_permissions', JSON.stringify(data.data.user.permissions || []));
      window.location.href = DASH_URL;
    } else {
      showErr('loginError', data.message || 'Email atau password salah.');
    }
  } catch(err) {
    showErr('loginError', 'Tidak dapat terhubung ke server.');
  } finally {
    btn.disabled = false; spin.style.display = 'none'; txt.style.display = 'block';
  }
});

// ---------------------------------------------------------------
// Register — langsung submit, tidak ada OTP
// ---------------------------------------------------------------
document.getElementById('registerForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  clearErrors();

  const nama      = document.getElementById('regNama').value.trim();
  const email     = document.getElementById('regEmail').value.trim();
  const unitKerja = document.getElementById('regUnitKerja').value.trim();
  const jabatan   = document.getElementById('regJabatan').value.trim();
  const telepon   = document.getElementById('regTelepon').value.trim();
  const pass      = document.getElementById('regPass').value;
  const passConf  = document.getElementById('regPassConf').value;

  if (!nama || !email || !unitKerja) {
    showErr('registerError', 'Nama, email, dan unit kerja wajib diisi.'); return;
  }
  if (pass !== passConf) {
    showErr('registerError', 'Konfirmasi password tidak cocok.'); return;
  }
  if (pass.length < 8) {
    showErr('registerError', 'Password minimal 8 karakter.'); return;
  }

  const btn  = document.getElementById('registerBtn');
  const spin = document.getElementById('registerSpinner');
  const txt  = document.getElementById('registerBtnText');
  btn.disabled = true; spin.style.display = 'block'; txt.style.display = 'none';

  try {
    const res  = await fetch(BASE + 'ltk/klien/register', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        nama,
        email,
        password:   pass,
        unit_kerja: unitKerja,
        jabatan:    jabatan  || null,
        no_telepon: telepon  || null,
      }),
    });
    const data = await res.json();
    if (data.status) {
      // Isi halaman sukses
      document.getElementById('successNama').textContent  = nama;
      document.getElementById('successEmail').textContent = email;
      document.getElementById('successUnit').textContent  = unitKerja;
      showStep('success');
    } else {
      showErr('registerError', data.message || 'Pendaftaran gagal. Silakan coba lagi.');
    }
  } catch(err) {
    showErr('registerError', 'Tidak dapat terhubung ke server.');
  } finally {
    btn.disabled = false; spin.style.display = 'none'; txt.style.display = 'block';
  }
});

// ---------------------------------------------------------------
// Auto-redirect jika sudah login
// ---------------------------------------------------------------
(function() {
  const token = localStorage.getItem('mka_token');
  if (!token) return;
  try {
    const parts   = token.split('.');
    const payload = JSON.parse(atob(parts[1].replace(/-/g,'+').replace(/_/g,'/')));
    if (payload.exp && payload.exp > Math.floor(Date.now() / 1000)) {
      window.location.href = DASH_URL;
    } else {
      localStorage.removeItem('mka_token');
      localStorage.removeItem('mka_user');
    }
  } catch(e) {
    localStorage.removeItem('mka_token');
    localStorage.removeItem('mka_user');
  }
})();
</script>
</body>
</html>
