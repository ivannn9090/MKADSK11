/* ============================================================
   settings.js — Halaman Pengaturan Aplikasi (role: admin)
   Tab: Umum | Email SMTP | Pengguna | Statistik
   ============================================================ */
'use strict';

let _settingsData    = {};
let _settingsUsers   = [];
let _settingsEditId  = null;

const _ALL_ROLES = [
  { value: 'admin',              label: 'Administrator' },
  { value: 'direktur',           label: 'Direktur' },
  { value: 'tu',                 label: 'Tata Usaha' },
  { value: 'ketua_tim',          label: 'Ketua Tim' },
  { value: 'anggota_tim',        label: 'Anggota Tim' },
  { value: 'perencana_anggaran', label: 'Perencana Anggaran' },
];

// ---------------------------------------------------------------
// Entry point
// ---------------------------------------------------------------
async function renderSettings() {
  if (currentUser.role !== 'admin') {
    document.getElementById('mainContent').innerHTML =
      `<div style="padding:40px;text-align:center;color:var(--danger)">
        Akses ditolak. Halaman ini hanya untuk Administrator.
      </div>`;
    return;
  }

  const content = document.getElementById('mainContent');
  content.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;height:200px">
    <div class="spinner-border text-primary" role="status" style="width:2rem;height:2rem"></div>
  </div>`;

  const [settingsRes, statsRes] = await Promise.all([
    apiGet('admin/settings').catch(() => null),
    apiGet('admin/stats').catch(() => null),
  ]);

  if (settingsRes && settingsRes.status) {
    _settingsData = {};
    (settingsRes.data.items || []).forEach(s => { _settingsData[s.setting_key] = s; });
  }

  content.innerHTML = _settingsShell(statsRes && statsRes.status ? statsRes.data : null);
  _settingsBindEvents();
  _settingsSwitchTab('umum', document.querySelector('[data-stab="umum"]'));
}

// ---------------------------------------------------------------
// Shell
// ---------------------------------------------------------------
function _settingsShell(stats) {
  return `
  <div class="settings-wrap" style="max-width:900px;margin:0 auto;padding:0 4px">

    <!-- Tab bar -->
    <div class="settings-tabs" style="display:flex;gap:4px;border-bottom:2px solid var(--border-color);margin-bottom:24px;flex-wrap:wrap">
      ${[
        ['umum',      '⚙️', 'Umum'],
        ['email',     '✉️', 'Konfigurasi Email'],
        ['users',     '👥', 'Pengguna'],
        ['statistik', '📊', 'Statistik'],
      ].map(([k, icon, label]) => `
        <button data-stab="${k}" onclick="_settingsSwitchTab('${k}',this)"
          style="padding:10px 18px;border:none;background:none;font-size:13px;font-weight:600;
                 color:var(--gray-500);cursor:pointer;border-bottom:2px solid transparent;
                 margin-bottom:-2px;display:flex;align-items:center;gap:6px;transition:all .15s">
          <span>${icon}</span>${label}
        </button>`).join('')}
    </div>

    <!-- Tab: Umum -->
    <div id="stab-umum" style="display:none">
      <div class="card" style="padding:24px">
        <div class="section-title" style="margin-bottom:20px">Pengaturan Umum</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="form-group">
            <label class="form-label">Nama Aplikasi</label>
            <input type="text" class="form-control" id="set_app_name"
              value="${_sv('app_name','MKA System')}" placeholder="Nama aplikasi">
          </div>
          <div class="form-group">
            <label class="form-label">URL Aplikasi</label>
            <input type="text" class="form-control" id="set_app_url"
              value="${_sv('app_url','')}" placeholder="https://domain.go.id">
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label" style="margin-bottom:10px;display:flex;align-items:center;gap:10px">
              Aktifkan Registrasi Pengguna Baru
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
                <input type="checkbox" id="set_registration_enabled" style="width:18px;height:18px;cursor:pointer"
                  ${_sv('registration_enabled','0') === '1' ? 'checked' : ''}>
                <span id="set_reg_label" style="font-size:12px;color:${_sv('registration_enabled','0')==='1'?'var(--success)':'var(--gray-400)'}">
                  ${_sv('registration_enabled','0') === '1' ? 'Aktif' : 'Nonaktif'}
                </span>
              </label>
            </label>
            <div style="font-size:12px;color:var(--gray-400)">
              Jika diaktifkan, halaman login akan menampilkan link "Daftar Akun Baru" dengan verifikasi OTP email.
            </div>
          </div>
        </div>
        <div style="margin-top:20px">
          <button class="btn btn-primary" onclick="_settingsSaveUmum()">
            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
            Simpan Pengaturan
          </button>
        </div>
      </div>
    </div>

    <!-- Tab: Email SMTP -->
    <div id="stab-email" style="display:none">
      <div class="card" style="padding:24px;margin-bottom:16px">
        <div class="section-title" style="margin-bottom:20px">Konfigurasi SMTP</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="form-group">
            <label class="form-label">SMTP Host</label>
            <input type="text" class="form-control" id="set_smtp_host"
              value="${_sv('smtp_host','')}" placeholder="smtp.gmail.com">
          </div>
          <div class="form-group">
            <label class="form-label">SMTP Port</label>
            <input type="number" class="form-control" id="set_smtp_port"
              value="${_sv('smtp_port','587')}" placeholder="587">
          </div>
          <div class="form-group">
            <label class="form-label">SMTP Username</label>
            <input type="text" class="form-control" id="set_smtp_user"
              value="${_sv('smtp_user','')}" placeholder="email@domain.com" autocomplete="off">
          </div>
          <div class="form-group">
            <label class="form-label">SMTP Password</label>
            <div style="position:relative">
              <input type="password" class="form-control" id="set_smtp_pass"
                value="${_sv('smtp_pass','')}" placeholder="App password / secret" autocomplete="new-password"
                style="padding-right:40px">
              <button type="button" onclick="_settingsTogglePass('set_smtp_pass',this)"
                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);padding:0">
                <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Enkripsi</label>
            <select class="form-control" id="set_smtp_crypto">
              <option value="tls" ${_sv('smtp_crypto','tls')==='tls'?'selected':''}>TLS (STARTTLS, port 587)</option>
              <option value="ssl" ${_sv('smtp_crypto','tls')==='ssl'?'selected':''}>SSL (port 465)</option>
              <option value=""   ${_sv('smtp_crypto','tls')===''?'selected':''}>None (port 25)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Email Pengirim (From)</label>
            <input type="email" class="form-control" id="set_smtp_from_email"
              value="${_sv('smtp_from_email','')}" placeholder="noreply@domain.go.id">
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Nama Pengirim</label>
            <input type="text" class="form-control" id="set_smtp_from_name"
              value="${_sv('smtp_from_name','MKA System')}" placeholder="MKA System">
          </div>
        </div>
        <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <button class="btn btn-primary" onclick="_settingsSaveEmail()">
            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
            Simpan Konfigurasi
          </button>
          <button class="btn btn-secondary" onclick="_settingsShowTestEmail()">
            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
            Test Kirim Email
          </button>
        </div>
      </div>

      <!-- Form test email -->
      <div id="settingsTestEmailForm" style="display:none" class="card" style="padding:20px">
        <div style="padding:20px">
          <div class="section-title" style="margin-bottom:12px">Test Kirim Email</div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="flex:1;min-width:220px;margin-bottom:0">
              <label class="form-label">Email Tujuan</label>
              <input type="email" class="form-control" id="settingsTestEmailTo" placeholder="test@domain.com">
            </div>
            <button class="btn btn-primary" onclick="_settingsSendTestEmail()" id="settingsTestEmailBtn">Kirim Test</button>
            <button class="btn btn-ghost" onclick="document.getElementById('settingsTestEmailForm').style.display='none'">Batal</button>
          </div>
          <div id="settingsTestEmailResult" style="margin-top:10px"></div>
        </div>
      </div>

      <!-- Panduan SMTP -->
      <div class="card" style="padding:20px">
        <div class="section-title" style="margin-bottom:12px;font-size:13px">Panduan Konfigurasi SMTP</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:12.5px">
          <div style="background:var(--gray-50,#f7f8fa);border-radius:8px;padding:12px">
            <div style="font-weight:700;margin-bottom:6px">Gmail (App Password)</div>
            <div style="color:var(--gray-500)">Host: smtp.gmail.com<br>Port: 587 / Enkripsi: TLS<br>User: email@gmail.com<br>Pass: App Password (bukan password Gmail)</div>
          </div>
          <div style="background:var(--gray-50,#f7f8fa);border-radius:8px;padding:12px">
            <div style="font-weight:700;margin-bottom:6px">Office 365</div>
            <div style="color:var(--gray-500)">Host: smtp.office365.com<br>Port: 587 / Enkripsi: TLS<br>User: email@domain.onmicrosoft.com</div>
          </div>
          <div style="background:var(--gray-50,#f7f8fa);border-radius:8px;padding:12px">
            <div style="font-weight:700;margin-bottom:6px">Mailtrap (Dev/Test)</div>
            <div style="color:var(--gray-500)">Host: sandbox.smtp.mailtrap.io<br>Port: 2525 / Enkripsi: TLS<br>User & Pass: dari Mailtrap dashboard</div>
          </div>
          <div style="background:var(--gray-50,#f7f8fa);border-radius:8px;padding:12px">
            <div style="font-weight:700;margin-bottom:6px">SMTP Server Lokal</div>
            <div style="color:var(--gray-500)">Host: localhost atau 127.0.0.1<br>Port: 25 / Enkripsi: None<br>User & Pass bisa dikosongkan</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Pengguna -->
    <div id="stab-users" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <input type="text" class="form-control" id="settingsUserSearch"
            placeholder="Cari nama / email..." style="width:220px"
            oninput="_settingsFilterUsers(this.value)">
          <select class="form-control" id="settingsUserRoleFilter" style="width:160px"
            onchange="_settingsFilterUsers(document.getElementById('settingsUserSearch').value)">
            <option value="">Semua Role</option>
            ${_ALL_ROLES.map(r => `<option value="${r.value}">${r.label}</option>`).join('')}
          </select>
        </div>
        <button class="btn btn-primary" onclick="_settingsOpenUserForm(null)">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
          Tambah Pengguna
        </button>
      </div>
      <div class="table-wrap">
        <table id="settingsUserTable">
          <thead><tr><th>#</th><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th style="text-align:center">Ganti PW</th><th>Aksi</th></tr></thead>
          <tbody id="settingsUserBody"><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--gray-400)">
            <div class="spinner-border" style="width:1.2rem;height:1.2rem"></div>
          </td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- Tab: Statistik -->
    <div id="stab-statistik" style="display:none">
      <div id="settingsStatContent">
        <div style="display:flex;align-items:center;justify-content:center;height:150px">
          <div class="spinner-border text-primary" style="width:2rem;height:2rem"></div>
        </div>
      </div>
    </div>

  </div>

  <!-- Modal Form Pengguna -->
  <div id="settingsUserOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1050;
    display:none;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:12px;padding:28px 24px;width:100%;max-width:480px;
      max-height:90vh;overflow-y:auto;margin:16px;box-shadow:0 8px 40px rgba(0,0,0,.2)">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <div style="font-size:16px;font-weight:700" id="settingsUserFormTitle">Tambah Pengguna</div>
        <button onclick="_settingsCloseUserForm()" style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--gray-400)">&times;</button>
      </div>
      <form id="settingsUserForm" onsubmit="event.preventDefault();_settingsSubmitUser()">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Nama Lengkap <span style="color:red">*</span></label>
            <input type="text" class="form-control" id="suf_nama" placeholder="Nama lengkap" required>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Email <span style="color:red">*</span></label>
            <input type="email" class="form-control" id="suf_email" placeholder="email@domain.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Role <span style="color:red">*</span></label>
            <select class="form-control" id="suf_role" required>
              ${_ALL_ROLES.map(r => `<option value="${r.value}">${r.label}</option>`).join('')}
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-control" id="suf_status">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Nonaktif</option>
            </select>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Jabatan</label>
            <input type="text" class="form-control" id="suf_jabatan" placeholder="Jabatan (opsional)">
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label" id="suf_pass_label">Password <span style="color:red">*</span></label>
            <div style="position:relative">
              <input type="password" class="form-control" id="suf_password"
                placeholder="Minimal 6 karakter" autocomplete="new-password" style="padding-right:40px">
              <button type="button" onclick="_settingsTogglePass('suf_password',this)"
                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);padding:0">
                <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
              </button>
            </div>
            <div id="suf_pass_hint" style="font-size:11.5px;color:var(--gray-400);margin-top:3px">Kosongkan jika tidak ingin mengubah password.</div>
          </div>
        </div>
        <div class="form-group" style="grid-column:1/-1;margin-top:4px">
          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;user-select:none">
            <input type="checkbox" id="suf_must_change_password"
              style="width:16px;height:16px;margin-top:2px;flex-shrink:0;accent-color:var(--warning,#d97706);cursor:pointer">
            <div>
              <div style="font-weight:600;font-size:13px;color:var(--gray-700)">Wajib Ganti Password</div>
              <div style="font-size:11.5px;color:var(--gray-400);margin-top:2px">
                Tandai akun ini sebagai baru — pengguna akan diminta mengganti password saat pertama kali login.
              </div>
            </div>
          </label>
        </div>
        <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end">
          <button type="button" class="btn btn-ghost" onclick="_settingsCloseUserForm()">Batal</button>
          <button type="submit" class="btn btn-primary" id="settingsUserFormBtn">Simpan</button>
        </div>
      </form>
    </div>
  </div>`;
}

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------
function _sv(key, def = '') {
  return esc(_settingsData[key] ? (_settingsData[key].setting_value || def) : def);
}

function _settingsTogglePass(inputId, btn) {
  const inp = document.getElementById(inputId);
  if (!inp) return;
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.style.color = inp.type === 'text' ? 'var(--primary)' : 'var(--gray-400)';
}

function _settingsBindEvents() {
  // Toggle registrasi enabled label
  const regChk = document.getElementById('set_registration_enabled');
  const regLbl = document.getElementById('set_reg_label');
  if (regChk && regLbl) {
    regChk.addEventListener('change', () => {
      regLbl.textContent = regChk.checked ? 'Aktif' : 'Nonaktif';
      regLbl.style.color = regChk.checked ? 'var(--success)' : 'var(--gray-400)';
    });
  }
}

// ---------------------------------------------------------------
// Tab switching
// ---------------------------------------------------------------
function _settingsSwitchTab(tab, btn) {
  document.querySelectorAll('[id^="stab-"]').forEach(el => el.style.display = 'none');
  document.querySelectorAll('[data-stab]').forEach(b => {
    b.style.color        = 'var(--gray-500)';
    b.style.borderBottom = '2px solid transparent';
  });
  const el = document.getElementById('stab-' + tab);
  if (el) el.style.display = '';
  if (btn) {
    btn.style.color        = 'var(--primary)';
    btn.style.borderBottom = '2px solid var(--primary)';
  }
  if (tab === 'users')     _settingsLoadUsers();
  if (tab === 'statistik') _settingsLoadStats();
}

// ---------------------------------------------------------------
// Save — Umum
// ---------------------------------------------------------------
async function _settingsSaveUmum() {
  const settings = {
    app_name:               document.getElementById('set_app_name').value.trim(),
    app_url:                document.getElementById('set_app_url').value.trim(),
    registration_enabled:   document.getElementById('set_registration_enabled').checked ? '1' : '0',
  };
  const res = await apiPost('admin/settings', { settings });
  if (res && res.status) showSuccess(res.message || 'Tersimpan');
  else showError(res ? res.message : 'Gagal menyimpan');
}

// ---------------------------------------------------------------
// Save — Email SMTP
// ---------------------------------------------------------------
async function _settingsSaveEmail() {
  const passVal = document.getElementById('set_smtp_pass').value;
  const settings = {
    smtp_host:       document.getElementById('set_smtp_host').value.trim(),
    smtp_port:       document.getElementById('set_smtp_port').value.trim(),
    smtp_user:       document.getElementById('set_smtp_user').value.trim(),
    smtp_crypto:     document.getElementById('set_smtp_crypto').value,
    smtp_from_email: document.getElementById('set_smtp_from_email').value.trim(),
    smtp_from_name:  document.getElementById('set_smtp_from_name').value.trim(),
  };
  // Hanya kirim password jika tidak masked
  if (passVal && passVal !== '••••••••') settings.smtp_pass = passVal;
  const res = await apiPost('admin/settings', { settings });
  if (res && res.status) showSuccess(res.message || 'Konfigurasi email tersimpan');
  else showError(res ? res.message : 'Gagal menyimpan');
}

// ---------------------------------------------------------------
// Test email
// ---------------------------------------------------------------
function _settingsShowTestEmail() {
  const el = document.getElementById('settingsTestEmailForm');
  if (el) { el.style.display = el.style.display === 'none' ? '' : 'none'; }
}

async function _settingsSendTestEmail() {
  const toEmail = (document.getElementById('settingsTestEmailTo').value || '').trim();
  if (!toEmail) { showError('Masukkan alamat email tujuan'); return; }
  const btn = document.getElementById('settingsTestEmailBtn');
  const res_el = document.getElementById('settingsTestEmailResult');
  btn.disabled = true; btn.textContent = 'Mengirim...';

  const res = await apiPost('admin/test-email', { to_email: toEmail, to_name: 'Test' });
  btn.disabled = false; btn.textContent = 'Kirim Test';

  const ok = res && res.status;
  res_el.innerHTML = `<div style="padding:8px 12px;border-radius:6px;font-size:13px;
    background:${ok?'#e6f4ea':'#fde8e8'};color:${ok?'#1a7f37':'#cf222e'};margin-top:4px">
    ${ok ? '✓' : '✗'} ${res ? esc(res.message) : 'Koneksi gagal'}
  </div>`;
}

// ---------------------------------------------------------------
// Users tab
// ---------------------------------------------------------------
async function _settingsLoadUsers() {
  const tbody = document.getElementById('settingsUserBody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--gray-400)">
    <div class="spinner-border" style="width:1.2rem;height:1.2rem"></div>
  </td></tr>`;

  const res = await apiGet('admin/users');
  _settingsUsers = (res && res.status) ? (res.data.items || []) : [];
  _settingsRenderUserTable(_settingsUsers);
}

function _settingsFilterUsers(q) {
  const role = document.getElementById('settingsUserRoleFilter').value;
  let list = _settingsUsers;
  if (role) list = list.filter(u => u.role === role);
  if (q.trim()) {
    const lq = q.trim().toLowerCase();
    list = list.filter(u => (u.nama||'').toLowerCase().includes(lq) || (u.email||'').toLowerCase().includes(lq));
  }
  _settingsRenderUserTable(list);
}

function _settingsRenderUserTable(users) {
  const tbody = document.getElementById('settingsUserBody');
  if (!tbody) return;
  if (!users.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--gray-400)">Tidak ada pengguna.</td></tr>`;
    return;
  }
  tbody.innerHTML = users.map((u, i) => `
    <tr>
      <td style="color:var(--gray-400);font-size:12px">${i+1}</td>
      <td><div style="font-weight:600">${esc(u.nama)}</div><div style="font-size:11px;color:var(--gray-400)">${esc(u.jabatan||'')}</div></td>
      <td style="font-size:13px">${esc(u.email)}</td>
      <td><span class="badge ${roleBadge(u.role)}" style="font-size:11px">${roleLabel(u.role)}</span></td>
      <td><span class="status-badge ${u.status==='aktif'?'status-berjalan':'status-terlambat'}" style="font-size:11px">${u.status==='aktif'?'Aktif':'Nonaktif'}</span></td>
      <td style="text-align:center">
        ${parseInt(u.must_change_password) === 1
          ? `<span title="Wajib ganti password" style="display:inline-flex;align-items:center;justify-content:center;
              background:var(--warning-pale,#fffbeb);color:var(--warning,#d97706);
              border:1px solid #fde68a;border-radius:20px;padding:2px 8px;font-size:11px;font-weight:600;gap:4px;white-space:nowrap">
              <svg viewBox="0 0 24 24" fill="currentColor" width="11" height="11"><path d="M18 8h-1V6A5 5 0 0 0 7 6v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zm-6 9a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm3.1-9H8.9V6A3.1 3.1 0 0 1 12 2.9 3.1 3.1 0 0 1 15.1 6v2z"/></svg>
              Baru
            </span>`
          : `<span style="color:var(--gray-300,#cbd5e1);font-size:18px;line-height:1">—</span>`}
      </td>
      <td style="white-space:nowrap">
        <button class="btn btn-ghost btn-sm" onclick="_settingsOpenUserForm(${u.id})"
          style="font-size:12px;padding:3px 8px">Edit</button>
        ${u.id !== (currentUser && currentUser.id) ? `
        <button class="btn btn-ghost btn-sm" onclick="_settingsToggleUserStatus(${u.id},'${u.status}','${esc(u.nama)}')"
          style="font-size:12px;padding:3px 8px;color:${u.status==='aktif'?'var(--danger)':'var(--success)'}" >
          ${u.status==='aktif'?'Nonaktifkan':'Aktifkan'}
        </button>` : ''}
      </td>
    </tr>`).join('');
}

function _settingsOpenUserForm(id) {
  _settingsEditId = id;
  const isEdit = id !== null;
  // Gunakan == (loose) agar string "5" cocok dengan number 5
  const user = isEdit ? _settingsUsers.find(u => u.id == id) : null;

  document.getElementById('settingsUserFormTitle').textContent = isEdit ? 'Edit Pengguna' : 'Tambah Pengguna';
  document.getElementById('suf_nama').value    = user ? user.nama     : '';
  document.getElementById('suf_email').value   = user ? user.email    : '';
  document.getElementById('suf_jabatan').value = user ? (user.jabatan||'') : '';
  document.getElementById('suf_role').value    = user ? user.role     : 'anggota_tim';
  document.getElementById('suf_status').value  = user ? user.status   : 'aktif';
  document.getElementById('suf_must_change_password').checked = user ? parseInt(user.must_change_password) === 1 : false;
  document.getElementById('suf_password').value = '';

  // Password required only on create
  const passLbl  = document.getElementById('suf_pass_label');
  const passHint = document.getElementById('suf_pass_hint');
  const passInp  = document.getElementById('suf_password');
  if (isEdit) {
    passLbl.innerHTML  = 'Password';
    passHint.style.display = '';
    passInp.removeAttribute('required');
  } else {
    passLbl.innerHTML  = 'Password <span style="color:red">*</span>';
    passHint.style.display = 'none';
    passInp.setAttribute('required','required');
  }

  const overlay = document.getElementById('settingsUserOverlay');
  overlay.style.display = 'flex';
}

function _settingsCloseUserForm() {
  document.getElementById('settingsUserOverlay').style.display = 'none';
  _settingsEditId = null;
}

async function _settingsSubmitUser() {
  const btn = document.getElementById('settingsUserFormBtn');
  btn.disabled = true; btn.textContent = 'Menyimpan...';

  const payload = {
    nama:                 document.getElementById('suf_nama').value.trim(),
    email:                document.getElementById('suf_email').value.trim(),
    jabatan:              document.getElementById('suf_jabatan').value.trim(),
    role:                 document.getElementById('suf_role').value,
    status:               document.getElementById('suf_status').value,
    must_change_password: document.getElementById('suf_must_change_password').checked ? 1 : 0,
  };
  const pass = document.getElementById('suf_password').value;
  if (pass) payload.password = pass;

  const res = _settingsEditId
    ? await apiPut(`admin/users/${_settingsEditId}`, payload)
    : await apiPost('admin/users', payload);

  btn.disabled = false; btn.textContent = 'Simpan';

  if (!res || !res.status) { showError(res ? res.message : 'Gagal menyimpan'); return; }
  showSuccess(res.message || 'Berhasil');
  _settingsCloseUserForm();
  await _settingsLoadUsers();
}

async function _settingsToggleUserStatus(id, currentStatus, nama) {
  const newStatus = currentStatus === 'aktif' ? 'nonaktif' : 'aktif';
  const label     = newStatus === 'aktif' ? 'mengaktifkan' : 'menonaktifkan';
  if (!confirm(`${label.charAt(0).toUpperCase()+label.slice(1)} pengguna "${nama}"?`)) return;

  const res = newStatus === 'nonaktif'
    ? await apiDelete(`admin/users/${id}`)
    : await apiPut(`admin/users/${id}`, { status: 'aktif' });

  if (!res || !res.status) { showError(res ? res.message : 'Gagal'); return; }
  showSuccess(res.message || 'Berhasil');
  await _settingsLoadUsers();
}

// ---------------------------------------------------------------
// Statistik tab
// ---------------------------------------------------------------
async function _settingsLoadStats() {
  const el = document.getElementById('settingsStatContent');
  if (!el) return;

  const res = await apiGet('admin/stats');
  if (!res || !res.status) {
    el.innerHTML = `<div style="padding:24px;color:var(--danger)">Gagal memuat statistik.</div>`;
    return;
  }

  const { total_users, by_role } = res.data;
  const roleOrder = ['admin','direktur','ketua_tim','anggota_tim','perencana_anggaran'];
  const colors    = { admin:'#7c5cd8', direktur:'#3b82f6', ketua_tim:'#10b981', anggota_tim:'#f59e0b', perencana_anggaran:'#ef4444' };

  el.innerHTML = `
    <div style="margin-bottom:24px">
      <div style="font-size:13px;color:var(--gray-500);margin-bottom:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em">
        Total Pengguna Terdaftar
      </div>
      <div style="font-size:48px;font-weight:800;color:var(--primary)">${total_users || 0}</div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
      ${roleOrder.map(r => {
        const cnt   = by_role[r] || 0;
        const color = colors[r] || '#57606a';
        return `<div style="background:${color}14;border:1px solid ${color}33;border-radius:10px;padding:16px;text-align:center">
          <div style="font-size:28px;font-weight:800;color:${color}">${cnt}</div>
          <div style="font-size:12px;color:${color};margin-top:4px;font-weight:600">${roleLabel(r)}</div>
        </div>`;
      }).join('')}
    </div>`;
}
