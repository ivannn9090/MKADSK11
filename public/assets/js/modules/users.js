/* ============================================================
   users.js — Manajemen Pengguna + Manajemen Role Dinamis
   Tab 1 — Pengguna Internal DSK
   Tab 2 — Klien
   Tab 3 — Manajemen Role (admin only)
   ============================================================ */
'use strict';

// ── State ────────────────────────────────────────────────────
let _usersActiveTab  = 'internal';
let _rolesCache      = [];   // cache roles dari API
let _permsCache      = [];   // cache semua permissions yang tersedia
let _posisiCache     = [];   // cache posisi tim dari API

// ────────────────────────────────────────────────────────────
// Entry point
// ────────────────────────────────────────────────────────────
async function renderUsers() {
  const isAdmin   = currentUser.role === 'admin';
  const canManage = hasPermission('users.manage');
  const content = document.getElementById('mainContent');
  content.innerHTML = `
    <div style="display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:20px">
      <button id="tabBtnInternal" onclick="usersShowTab('internal')"
        class="users-tab-btn">🏢 Pengguna Internal DSK</button>
      <button id="tabBtnKlien" onclick="usersShowTab('klien')"
        class="users-tab-btn">
        👤 Klien
        <span id="klienPendingBadge" style="display:none;background:#ef4444;color:#fff;
          font-size:10px;font-weight:700;border-radius:99px;padding:1px 6px;margin-left:4px">0</span>
      </button>
      ${canManage ? `<button id="tabBtnPosisi" onclick="usersShowTab('posisi')"
        class="users-tab-btn">📌 Posisi Tim</button>` : ''}
      ${isAdmin ? `<button id="tabBtnRoles" onclick="usersShowTab('roles')"
        class="users-tab-btn">⚙️ Manajemen Role</button>` : ''}
    </div>

    <div id="panelInternal">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
        <div style="font-size:13px;color:#6b7280">Akun dibuat oleh Admin — Direktorat Sistem Kearsipan ANRI</div>
        ${canManage ? `<button class="btn btn-primary" onclick="showUserForm()">+ Tambah Pengguna</button>` : ''}
      </div>
      <div id="internalTableWrap"><div class="loading-page"><div class="spinner-lg"></div></div></div>
    </div>

    <div id="panelKlien" style="display:none">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
        <div style="font-size:13px;color:#6b7280">Akun terdaftar melalui registrasi mandiri</div>
        <button class="btn btn-sm btn-secondary" onclick="usersLoadKlien()">↻ Refresh</button>
      </div>
      <div id="klienTableWrap"><div class="loading-page"><div class="spinner-lg"></div></div></div>
    </div>

    ${canManage ? `<div id="panelPosisi" style="display:none">
      <div id="posisiPanel"><div class="loading-page"><div class="spinner-lg"></div></div></div>
    </div>` : ''}

    ${isAdmin ? `<div id="panelRoles" style="display:none">
      <div id="rolesPanel"><div class="loading-page"><div class="spinner-lg"></div></div></div>
    </div>` : ''}
  `;

  // Load roles & posisi cache paralel
  await Promise.all([_loadRolesCache(), _loadPosisiCache()]);

  usersShowTab(_usersActiveTab);
  usersLoadInternal();
  usersLoadKlien();
  if (canManage) _renderPosisiPanel();
  if (isAdmin)   _renderRolesPanel();
}

// ── Tab switcher ─────────────────────────────────────────────
function usersShowTab(tab) {
  _usersActiveTab = tab;
  const ACCENT = '#3b82f6';
  ['internal','klien','posisi','roles'].forEach(t => {
    const btn   = document.getElementById(`tabBtn${t.charAt(0).toUpperCase()+t.slice(1)}`);
    const panel = document.getElementById(`panel${t.charAt(0).toUpperCase()+t.slice(1)}`);
    if (!btn || !panel) return;
    const active = t === tab;
    btn.style.borderBottomColor = active ? ACCENT : 'transparent';
    btn.style.color             = active ? ACCENT : '#6b7280';
    panel.style.display         = active ? '' : 'none';
  });
}

// ── Load roles cache ─────────────────────────────────────────
async function _loadRolesCache() {
  const [rolesRes, permsRes] = await Promise.all([
    apiGet('roles'),
    apiGet('roles/permissions'),
  ]);
  _rolesCache  = rolesRes?.data  || [];
  _permsCache  = permsRes?.data  || [];
}

// ── Load posisi tim cache ────────────────────────────────────
async function _loadPosisiCache() {
  const res = await apiGet('posisi-tim');
  _posisiCache = res?.data || [];
}

// ════════════════════════════════════════════════════════════
// TAB 1 — PENGGUNA INTERNAL DSK
// ════════════════════════════════════════════════════════════
async function usersLoadInternal() {
  const el = document.getElementById('internalTableWrap');
  if (!el) return;

  const res = await apiGet('users');
  if (!res || !res.status) {
    el.innerHTML = `<div class="alert alert-danger">Gagal memuat pengguna.</div>`; return;
  }

  const rows      = (res.data || []).filter(u => u.role !== 'admin');
  const canDelete = hasPermission('users.manage');
  const canEdit   = hasPermission('users.manage');

  if (!rows.length) { el.innerHTML = emptyState('Belum ada pengguna internal.'); return; }

  el.innerHTML = `
    <div class="table-wrap">
      <table style="font-size:13px">
        <thead>
          <tr>
            <th style="width:36px">#</th>
            <th>Nama</th>
            <th>Email</th>
            <th>Jabatan</th>
            <th>Posisi Tim</th>
            <th>Role</th>
            <th>Status</th>
            <th style="width:70px"></th>
          </tr>
        </thead>
        <tbody>
          ${rows.map((u, i) => _internalRow(u, i + 1, canEdit, canDelete)).join('')}
        </tbody>
      </table>
    </div>`;
}

function _internalRow(u, no, canEdit, canDelete) {
  // Multi-role badges
  const userRoles  = u.roles || [u.role];
  const roleBadges = userRoles.map(slug => {
    const warna = _roleWarna(slug);
    return `<span class="status-badge" style="background:${warna}22;color:${warna};margin-right:3px">${_roleLabel(slug)}</span>`;
  }).join('');

  // Multi-posisi badges
  const userPosisi  = u.posisi || (u.posisi_tim ? [{id:0, nama:u.posisi_tim}] : []);
  const posisiBadges = userPosisi.length
    ? userPosisi.map(p => `<span style="font-size:11px;background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;border-radius:4px;padding:1px 6px;margin-right:2px;white-space:nowrap">${esc(p.nama)}</span>`).join('')
    : `<span style="color:#9ca3af">—</span>`;

  return `
    <tr id="user-row-${u.id}">
      <td style="color:#9ca3af;font-size:12px">${no}</td>
      <td style="font-weight:600">${esc(u.nama)}</td>
      <td style="color:#6b7280;font-size:12px">${esc(u.email)}</td>
      <td>${esc(u.jabatan||'—')}</td>
      <td style="min-width:100px">${posisiBadges}</td>
      <td style="min-width:120px">${roleBadges}</td>
      <td>
        <span class="status-badge ${u.status==='aktif'?'status-selesai':'status-terlambat'}">
          ${u.status}
        </span>
      </td>
      <td style="white-space:nowrap">
        ${canEdit ? `<button class="btn-icon" title="Edit"
          onclick="showUserForm(${JSON.stringify(u).replace(/"/g,'&quot;')})">✏️</button>` : ''}
        ${canDelete && u.id !== currentUser.id ? `
          <button class="btn-icon" title="Hapus" style="color:#ef4444"
            onclick="usersDeleteInternal(${u.id},'${esc(u.nama)}')">🗑</button>` : ''}
      </td>
    </tr>`;
}

// ── Form tambah / edit pengguna ──────────────────────────────
function showUserForm(user) {
  const isEdit    = !!user;
  const isTu      = currentUser.role === 'tu';
  const disEdit   = (isTu && isEdit) ? 'disabled style="background:var(--gray-100)"' : '';
  const userRoles = user?.roles || (user?.role ? [user.role] : []);

  // Multi-role checkboxes
  const roleCheckboxes = _rolesCache
    .filter(r => !['admin','klien'].includes(r.slug))
    .map(r => {
      const checked = userRoles.includes(r.slug) ? 'checked' : '';
      const warna   = r.warna || '#6b7280';
      return `<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;
                      padding:4px 8px;border-radius:6px;border:1px solid ${checked?warna+'55':'#e5e7eb'};
                      background:${checked?warna+'11':'#fff'};transition:all .15s" id="rl_wrap_${r.slug}">
        <input type="checkbox" class="fu_role_cb" value="${r.slug}" ${checked}
               onchange="_roleCheckboxChange(this,'${r.slug}','${warna}')">
        <span style="width:8px;height:8px;border-radius:50%;background:${warna};flex-shrink:0"></span>
        ${esc(r.label)}
      </label>`;
    }).join('');

  // Multi-posisi checkboxes
  const userPosisiIds = (user?.posisi || []).map(p => p.id);
  const posisiCheckboxes = _posisiCache.map(p => {
    const checked = userPosisiIds.includes(p.id) ? 'checked' : '';
    return `<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;
                    padding:4px 8px;border-radius:6px;border:1px solid ${checked?'#0369a1':'#e5e7eb'};
                    background:${checked?'#f0f9ff':'#fff'};transition:all .15s" id="pt_wrap_${p.id}">
      <input type="checkbox" class="fu_posisi_cb" value="${p.id}" ${checked}
             onchange="_posisiCheckboxChange(this,${p.id})">
      ${esc(p.nama)}
      ${p.is_system ? '' : ''}
    </label>`;
  }).join('') || '<span style="color:#9ca3af;font-size:12px">Belum ada posisi. Tambah di tab Posisi Tim.</span>';

  openModal(isEdit ? 'Edit Pengguna' : 'Tambah Pengguna', `
    ${isTu && isEdit ? `<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;
        padding:10px 14px;font-size:12.5px;color:#92400e;margin-bottom:14px">
      ⚠️ Sebagai Tata Usaha, saat edit Anda hanya dapat mengubah <strong>Role</strong>.
    </div>` : ''}
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Nama <span class="required">*</span></label>
        <input type="text" class="form-control" id="fu_nama" value="${esc(user?.nama||'')}" ${disEdit}>
      </div>
      <div class="form-group">
        <label class="form-label">Email <span class="required">*</span></label>
        <input type="email" class="form-control" id="fu_email" value="${esc(user?.email||'')}" ${disEdit}>
      </div>
    </div>
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Jabatan</label>
        <input type="text" class="form-control" id="fu_jabatan" value="${esc(user?.jabatan||'')}" ${disEdit}>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Posisi Tim
        <span style="font-size:11px;color:#6b7280;font-weight:400">(pilih satu atau lebih, opsional)</span>
      </label>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px">
        ${posisiCheckboxes}
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Role <span class="required">*</span>
        <span style="font-size:11px;color:#6b7280;font-weight:400">(pilih satu atau lebih)</span>
      </label>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px">
        ${roleCheckboxes}
      </div>
    </div>
    ${isEdit && !isTu ? `
    <div class="form-group">
      <label class="form-label">Status</label>
      <select class="form-control" id="fu_status">
        <option value="aktif"    ${user?.status==='aktif'   ?'selected':''}>Aktif</option>
        <option value="nonaktif" ${user?.status==='nonaktif'?'selected':''}>Nonaktif</option>
      </select>
    </div>` : ''}
    ${!isTu || !isEdit ? `
    <div class="form-group">
      <label class="form-label">${isEdit?'Password Baru (kosongkan jika tidak diubah)':'Password *'}</label>
      <input type="password" class="form-control" id="fu_password" ${isEdit?'':'required'}>
    </div>` : ''}
  `, [
    { label: 'Batal',  cls: 'btn-secondary', action: closeModal },
    { label: 'Simpan', cls: 'btn-primary',   action: () => saveUser(isEdit ? user.id : null) },
  ]);
}

/** Update visual checkbox role saat diklik */
function _roleCheckboxChange(cb, slug, warna) {
  const wrap = document.getElementById(`rl_wrap_${slug}`);
  if (!wrap) return;
  wrap.style.borderColor = cb.checked ? warna + '55' : '#e5e7eb';
  wrap.style.background  = cb.checked ? warna + '11' : '#fff';
}

/** Update visual checkbox posisi saat diklik */
function _posisiCheckboxChange(cb, id) {
  const wrap = document.getElementById(`pt_wrap_${id}`);
  if (!wrap) return;
  wrap.style.borderColor = cb.checked ? '#0369a1' : '#e5e7eb';
  wrap.style.background  = cb.checked ? '#f0f9ff' : '#fff';
}

async function saveUser(id) {
  const isTu     = currentUser.role === 'tu';
  const roles    = Array.from(document.querySelectorAll('.fu_role_cb:checked')).map(cb => cb.value);
  const posisiIds = Array.from(document.querySelectorAll('.fu_posisi_cb:checked')).map(cb => parseInt(cb.value));

  if (!roles.length) { showToast('Pilih minimal satu role.', 'error'); return; }

  if (isTu && id) {
    const res = await apiPut(`users/${id}`, { roles });
    if (res?.status) { showToast('Role diperbarui.'); closeModal(); usersLoadInternal(); }
    else showToast(res?.message || 'Gagal.', 'error');
    return;
  }
  const data = {
    nama:    document.getElementById('fu_nama')?.value?.trim(),
    email:   document.getElementById('fu_email')?.value?.trim(),
    jabatan: document.getElementById('fu_jabatan')?.value?.trim(),
    roles,
    status:  document.getElementById('fu_status')?.value,
    password: document.getElementById('fu_password')?.value,
  };
  if (!data.nama || !data.email) { showToast('Nama dan email wajib diisi.', 'error'); return; }
  if (!id && !data.password)     { showToast('Password wajib diisi.', 'error'); return; }

  const res = id ? await apiPut(`users/${id}`, data) : await apiPost('users', data);
  if (!res?.status) { showToast(res?.message || 'Gagal.', 'error'); return; }

  // Simpan posisi tim secara terpisah (jika ada user id)
  const savedId = id || res.data?.id;
  if (savedId) {
    await apiPut(`users/${savedId}/posisi`, { posisi_ids: posisiIds });
  }

  showToast(id ? 'Pengguna diperbarui.' : 'Pengguna dibuat.');
  closeModal();
  await _loadPosisiCache();
  usersLoadInternal();
}

async function usersDeleteInternal(id, nama) {
  openModal('Hapus Pengguna', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">🗑️</div>
      <div style="font-size:14px;font-weight:700;margin-bottom:6px">Hapus akun <strong>${esc(nama)}</strong>?</div>
      <div style="font-size:13px;color:#6b7280">Tindakan ini tidak dapat dibatalkan.</div>
    </div>`, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Hapus', cls: 'btn-danger',    action: async () => {
      const res = await apiDelete(`users/${id}`);
      if (!res?.status) { showToast(res?.message || 'Gagal.', 'error'); return; }
      closeModal(); showToast(`Akun ${nama} dihapus.`); usersLoadInternal();
    }},
  ]);
}

// ════════════════════════════════════════════════════════════
// TAB 2 — KLIEN
// ════════════════════════════════════════════════════════════
async function usersLoadKlien() {
  const el = document.getElementById('klienTableWrap');
  if (!el) return;
  el.innerHTML = '<div class="spinner-sm" style="margin:20px auto"></div>';

  const res = await apiGet('users/klien');
  if (!res || !res.status) { el.innerHTML = `<div class="alert alert-danger">Gagal memuat data klien.</div>`; return; }

  const rows      = res.data || [];
  const pending   = rows.filter(r => !parseInt(r.is_verified));
  const verified  = rows.filter(r => parseInt(r.is_verified));
  const badge     = document.getElementById('klienPendingBadge');
  if (badge) { badge.style.display = pending.length ? 'inline' : 'none'; badge.textContent = pending.length; }
  if (!rows.length) { el.innerHTML = emptyState('Belum ada akun klien terdaftar.'); return; }

  el.innerHTML = `
    ${pending.length ? `
    <div style="font-size:12px;font-weight:700;color:#b45309;background:#fef3c7;
      border-radius:6px;padding:3px 10px;display:inline-block;margin-bottom:8px">⏳ Menunggu Verifikasi (${pending.length})</div>
    <div class="table-wrap" style="margin-bottom:20px">
      <table style="font-size:13px">
        <thead><tr><th>#</th><th>Nama</th><th>Email</th><th>Unit Kerja</th><th>Jabatan</th><th>Telepon</th><th>Status</th><th></th></tr></thead>
        <tbody>${pending.map((k,i) => usersKlienRow(k,i+1,false)).join('')}</tbody>
      </table></div>` : ''}
    <div style="font-size:12px;font-weight:700;color:#166534;background:#dcfce7;
      border-radius:6px;padding:3px 10px;display:inline-block;margin-bottom:8px">✅ Terverifikasi (${verified.length})</div>
    ${verified.length ? `
    <div class="table-wrap">
      <table style="font-size:13px">
        <thead><tr><th>#</th><th>Nama</th><th>Email</th><th>Unit Kerja</th><th>Jabatan</th><th>Telepon</th><th>Diverifikasi</th><th>Status</th><th></th></tr></thead>
        <tbody>${verified.map((k,i) => usersKlienRow(k,i+1,true)).join('')}</tbody>
      </table></div>` :
    `<div style="font-size:13px;color:#6b7280;padding:12px 0">Belum ada klien terverifikasi.</div>`}`;
}

function usersKlienRow(k, no, isVerified) {
  const isAktif = k.status === 'aktif';
  const verCol  = isVerified
    ? `<td style="font-size:11px">${esc(k.verified_by_nama||'—')}<br>
         <span style="color:#9ca3af">${k.verified_at?k.verified_at.substring(0,10):''}</span></td>` : '';
  const actionBtn = !isVerified
    ? `<button class="btn btn-sm btn-success" style="font-size:11px"
         onclick="usersVerifikasiKlien(${k.id},'${esc(k.nama)}')">✓ Verifikasi</button>`
    : `<button class="btn btn-sm" style="font-size:11px;background:${isAktif?'#fee2e2':'#dcfce7'};color:${isAktif?'#b91c1c':'#166534'}"
         onclick="usersToggleStatusKlien(${k.id},'${k.status}','${esc(k.nama)}')">${isAktif?'Nonaktifkan':'Aktifkan'}</button>`;
  const canDel = hasPermission('users.manage');
  const delBtn = canDel ? `<button class="btn btn-sm" style="font-size:11px;background:#fee2e2;color:#b91c1c;margin-left:4px"
    onclick="usersDeleteKlien(${k.id},'${esc(k.nama)}')">🗑</button>` : '';
  return `<tr>
    <td style="color:#9ca3af;font-size:12px">${no}</td>
    <td style="font-weight:600">${esc(k.nama)}</td>
    <td>${esc(k.email)}</td>
    <td>${esc(k.unit_kerja||'—')}</td>
    <td>${esc(k.jabatan||k.jabatan_user||'—')}</td>
    <td>${esc(k.no_telepon||'—')}</td>
    ${verCol}
    <td><span class="status-badge ${isAktif?'status-selesai':'status-terlambat'}">${k.status}</span></td>
    <td style="white-space:nowrap">${actionBtn}${delBtn}</td>
  </tr>`;
}

async function usersVerifikasiKlien(userId, nama) {
  openModal('Verifikasi Akun Klien', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">✅</div>
      <div style="font-size:14px;font-weight:700;margin-bottom:6px">Verifikasi akun <strong>${esc(nama)}</strong>?</div>
      <div style="font-size:13px;color:#6b7280">Setelah diverifikasi, klien dapat login dan mengajukan permohonan.</div>
    </div>`, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: '✓ Verifikasi', cls: 'btn-success', action: async () => {
      const res = await apiPost(`ltk/klien/${userId}/verifikasi`, {});
      if (!res?.status) { showToast(res?.message||'Gagal.','error'); return; }
      closeModal(); showToast(`Akun ${nama} diverifikasi.`); usersLoadKlien();
    }},
  ]);
}

async function usersDeleteKlien(id, nama) {
  openModal('Hapus Akun Klien', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">🗑️</div>
      <div style="font-size:14px;font-weight:700;margin-bottom:6px">Hapus akun klien <strong>${esc(nama)}</strong>?</div>
      <div style="font-size:13px;color:#6b7280">Akun dengan permohonan aktif <strong>tidak dapat dihapus</strong>.</div>
    </div>`, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Hapus', cls: 'btn-danger', action: async () => {
      const res = await apiDelete(`users/klien/${id}`);
      if (!res?.status) { showToast(res?.message||'Gagal.','error'); return; }
      closeModal(); showToast(`Akun klien ${nama} dihapus.`); usersLoadKlien();
    }},
  ]);
}

async function usersToggleStatusKlien(userId, currentStatus, nama) {
  const newStatus = currentStatus === 'aktif' ? 'nonaktif' : 'aktif';
  const res = await apiPut(`users/klien/${userId}/status`, { status: newStatus });
  if (res?.status) { showToast(`Status diubah ke ${newStatus}.`); usersLoadKlien(); }
  else showToast(res?.message||'Gagal.','error');
}

// ════════════════════════════════════════════════════════════
// TAB 3 — MANAJEMEN POSISI TIM
// ════════════════════════════════════════════════════════════
async function _renderPosisiPanel() {
  const el = document.getElementById('posisiPanel');
  if (!el) return;

  await _loadPosisiCache();
  const posisi = _posisiCache;

  el.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div style="font-size:13px;color:#6b7280">
        Kelola posisi tim yang tersedia. Posisi sistem
        <span style="background:#dbeafe;color:#1e40af;border-radius:4px;padding:1px 6px;font-size:11px">sistem</span>
        tidak dapat dihapus, namun bisa diubah namanya.
      </div>
      <button class="btn btn-primary btn-sm" onclick="showPosisiForm()">+ Tambah Posisi</button>
    </div>
    <div class="table-wrap">
      <table style="font-size:13px">
        <thead><tr>
          <th style="width:40px">#</th>
          <th>Nama Posisi</th>
          <th style="width:100px">Tipe</th>
          <th style="width:80px"></th>
        </tr></thead>
        <tbody>
          ${posisi.map((p, i) => `
            <tr id="posisi-row-${p.id}">
              <td style="color:#9ca3af;font-size:12px">${i + 1}</td>
              <td style="font-weight:600">${esc(p.nama)}</td>
              <td>
                ${parseInt(p.is_system)
                  ? `<span style="font-size:10px;font-weight:700;background:#dbeafe;color:#1e40af;border-radius:4px;padding:1px 7px">SISTEM</span>`
                  : `<span style="font-size:10px;background:#f1f5f9;color:#6b7280;border-radius:4px;padding:1px 7px">Kustom</span>`}
              </td>
              <td style="white-space:nowrap">
                <button class="btn-icon" title="Edit" onclick="showPosisiForm(${JSON.stringify(p).replace(/"/g,'&quot;')})">✏️</button>
                ${!parseInt(p.is_system) ? `
                  <button class="btn-icon" title="Hapus" style="color:#ef4444"
                    onclick="posisiDelete(${p.id},'${esc(p.nama)}')">🗑</button>` : ''}
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>`;
}

function showPosisiForm(posisi) {
  const isEdit = !!posisi;
  openModal(isEdit ? 'Edit Posisi Tim' : 'Tambah Posisi Tim', `
    <div class="form-group">
      <label class="form-label">Nama Posisi <span class="required">*</span></label>
      <input type="text" class="form-control" id="fp_nama"
             value="${esc(posisi?.nama || '')}" placeholder="contoh: Koordinator Lapangan" maxlength="100">
    </div>
    ${isEdit && parseInt(posisi?.is_system) ? `
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;
        padding:10px 14px;font-size:12px;color:#1e40af">
      ℹ️ Posisi sistem — hanya nama yang dapat diubah.
    </div>` : ''}
  `, [
    { label: 'Batal',  cls: 'btn-secondary', action: closeModal },
    { label: 'Simpan', cls: 'btn-primary',   action: () => savePosisi(isEdit ? posisi.id : null) },
  ]);
}

async function savePosisi(id) {
  const nama = document.getElementById('fp_nama')?.value?.trim();
  if (!nama) { showToast('Nama posisi wajib diisi.', 'error'); return; }

  const res = id
    ? await apiPut(`posisi-tim/${id}`, { nama })
    : await apiPost('posisi-tim', { nama });

  if (!res?.status) { showToast(res?.message || 'Gagal.', 'error'); return; }

  showToast(id ? 'Posisi diperbarui.' : 'Posisi ditambahkan.', 'success');
  closeModal();
  await _loadPosisiCache();
  _renderPosisiPanel();
}

async function posisiDelete(id, nama) {
  openModal('Hapus Posisi Tim', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">🗑️</div>
      <div style="font-size:14px;font-weight:700;margin-bottom:6px">Hapus posisi <strong>${esc(nama)}</strong>?</div>
      <div style="font-size:13px;color:#6b7280">Posisi akan dihapus dari semua pengguna yang memilikinya.</div>
    </div>
  `, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Hapus', cls: 'btn-danger', action: async () => {
      const res = await apiDelete(`posisi-tim/${id}`);
      if (!res?.status) { showToast(res?.message || 'Gagal.', 'error'); return; }
      closeModal();
      showToast(`Posisi "${nama}" dihapus.`);
      await _loadPosisiCache();
      _renderPosisiPanel();
    }},
  ]);
}


// ════════════════════════════════════════════════════════════
// TAB 3 — MANAJEMEN ROLE (admin only)
// ════════════════════════════════════════════════════════════
async function _renderRolesPanel() {
  const el = document.getElementById('rolesPanel');
  if (!el) return;

  await _loadRolesCache();
  const roles = _rolesCache;
  const perms = _permsCache;

  // Label Indonesia untuk setiap grup permission
  const GROUP_LABELS = {
    kegiatan:   '📁 Kegiatan',
    task:       '✅ Sub-Kegiatan',
    anggaran:   '💰 Anggaran',
    rincian:    '📋 Rincian Anggaran',
    off_budget: '🔗 Off Budget',
    rpd:        '📊 RPD',
    ro:         '🗂️ Rumpun Output',
    laporan:    '📈 Laporan',
    users:      '👥 Pengguna',
    teams:      '🏢 Tim',
    audit:      '🔍 Audit Trail',
    settings:   '⚙️ Pengaturan',
    serasi:     '🗃️ SERASI',
    schedule:   '🗓️ Schedule',
    ltk:        '🔬 Telaah Konsep (LTK)',
  };

  // Kelompokkan permissions berdasarkan prefix, dengan urutan tetap
  const GROUP_ORDER = ['kegiatan','task','anggaran','rincian','off_budget','rpd','ro','laporan','users','teams','audit','settings','serasi','schedule','ltk'];
  const permGroups = {};
  perms.forEach(p => {
    const group = p.slug.split('.')[0];
    if (!permGroups[group]) permGroups[group] = [];
    permGroups[group].push(p);
  });
  // Urutkan sesuai GROUP_ORDER
  const orderedGroups = [
    ...GROUP_ORDER.filter(g => permGroups[g]),
    ...Object.keys(permGroups).filter(g => !GROUP_ORDER.includes(g)),
  ];

  el.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div style="font-size:13px;color:#6b7280">
        Kelola role dinamis. Role sistem (<span style="background:#dbeafe;color:#1e40af;
        border-radius:4px;padding:1px 6px;font-size:11px">sistem</span>) tidak dapat dihapus.
      </div>
      <button class="btn btn-primary btn-sm" onclick="showRoleForm()">+ Tambah Role</button>
    </div>

    <div style="display:flex;flex-direction:column;gap:12px" id="rolesList">
      ${roles.map(r => _roleCard(r, permGroups, orderedGroups, GROUP_LABELS)).join('')}
    </div>
  `;
}

function _roleCard(r, permGroups, orderedGroups, GROUP_LABELS) {
  const isSystem = parseInt(r.is_system);
  const perms    = r.permissions || [];

  // Permission checkbox grid per group
  const permHtml = orderedGroups.map(group => {
    const items = permGroups[group];
    const isLtk = group === 'ltk';
    return `
    <div style="margin-bottom:10px${isLtk ? ';padding:10px 12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px' : ''}">
      <div style="font-size:11px;font-weight:700;color:${isLtk?'#0369a1':'#6b7280'};text-transform:uppercase;
           letter-spacing:.05em;margin-bottom:5px">${GROUP_LABELS[group] || group}</div>
      <div style="display:flex;flex-wrap:wrap;gap:6px">
        ${items.map(p => `
          <label style="display:flex;align-items:center;gap:5px;font-size:12px;
                 cursor:pointer;color:#374151">
            <input type="checkbox"
              class="role-perm-cb"
              data-role="${r.slug}"
              data-perm="${p.slug}"
              ${perms.includes(p.slug) ? 'checked' : ''}
              onchange="roleTogglePermission('${r.slug}',${r.id},this)">
            ${p.label}
          </label>`).join('')}
      </div>
    </div>`
  }).join('');

  return `
    <div class="role-card" id="role-card-${r.id}">

      <!-- ── Header (selalu tampil, klik untuk expand/collapse) ── -->
      <div class="role-card-header" onclick="roleCardToggle(${r.id})">
        <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0">
          <span class="role-color-dot" style="background:${r.warna}"></span>
          <span style="font-weight:700;font-size:14px">${esc(r.label)}</span>
          <span style="font-size:11px;color:#6b7280;background:#f1f5f9;
                border-radius:4px;padding:1px 7px;white-space:nowrap">${r.slug}</span>
          ${isSystem ? `<span style="font-size:10px;font-weight:700;background:#dbeafe;
                color:#1e40af;border-radius:4px;padding:1px 7px;white-space:nowrap">SISTEM</span>` : ''}
          <span style="font-size:11.5px;color:#94a3b8;margin-left:4px">${perms.length} permission</span>
        </div>
        <div style="display:flex;align-items:center;gap:6px">
          <button class="btn-icon" title="Edit" onclick="event.stopPropagation();showRoleForm(${JSON.stringify(r).replace(/"/g,'&quot;')})">✏️</button>
          ${!isSystem ? `
            <button class="btn-icon" title="Hapus" style="color:#ef4444" onclick="event.stopPropagation();roleDelete(${r.id},'${esc(r.label)}')">🗑</button>
          ` : ''}
          <svg class="role-card-chevron" id="chevron-${r.id}"
               viewBox="0 0 24 24" fill="#94a3b8" width="16" height="16">
            <path d="M7 10l5 5 5-5z"/>
          </svg>
        </div>
      </div>

      <!-- ── Body (collapsed by default) ── -->
      <div class="role-card-body" id="role-body-${r.id}" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:10px 0 8px;border-top:1px solid #f1f5f9;margin-top:2px">
          <span style="font-size:12px;color:#6b7280">${perms.length} permission aktif</span>
          <button class="btn btn-sm" style="font-size:11px;
            padding:3px 12px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0"
            onclick="roleSavePermissions('${r.slug}',${r.id})">💾 Simpan Permission</button>
        </div>
        <div class="role-perms-grid">${permHtml}</div>
      </div>

    </div>`;
}

/** Toggle satu permission secara visual (simpan massal via tombol Simpan) */
function roleTogglePermission(slug, id, cb) {
  // Hanya visual update — simpan via tombol "Simpan Permission"
}

/** Expand / collapse body permission sebuah role card */
function roleCardToggle(id) {
  const body    = document.getElementById(`role-body-${id}`);
  const chevron = document.getElementById(`chevron-${id}`);
  if (!body) return;
  const isOpen = body.style.display !== 'none';
  body.style.display         = isOpen ? 'none' : 'block';
  chevron.style.transform    = isOpen ? ''      : 'rotate(180deg)';
}

/** Simpan semua permissions yang tercentang untuk satu role */
async function roleSavePermissions(slug, id) {
  const card = document.getElementById(`role-card-${id}`);
  if (!card) return;
  const checked = Array.from(card.querySelectorAll('.role-perm-cb:checked'))
                       .map(cb => cb.dataset.perm);
  const res = await apiPut(`roles/${id}`, { permissions: checked });
  if (res?.status) {
    showToast('Permission berhasil disimpan.', 'success');
    await _loadRolesCache();
  } else {
    showToast(res?.message || 'Gagal menyimpan.', 'error');
  }
}

function showRoleForm(role) {
  const isEdit   = !!role;
  const isSystem = role?.is_system;
  openModal(isEdit ? 'Edit Role' : 'Tambah Role Baru', `
    <div class="form-row form-row-2">
      <div class="form-group">
        <label class="form-label">Nama Role (Label) *</label>
        <input type="text" class="form-control" id="fr_label"
          value="${esc(role?.label||'')}">
      </div>
      <div class="form-group">
        <label class="form-label">Slug (kode unik) *</label>
        <input type="text" class="form-control" id="fr_slug"
          value="${esc(role?.slug||'')}" ${isEdit?'disabled':''}
          placeholder="contoh: supervisor">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Warna Badge</label>
      <div style="display:flex;align-items:center;gap:8px">
        <input type="color" id="fr_warna" value="${role?.warna||'#6b7280'}"
          style="width:40px;height:32px;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer">
        <span style="font-size:12px;color:#6b7280">Warna label role di tampilan tabel</span>
      </div>
    </div>
    ${isSystem ? `<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;
      padding:10px 14px;font-size:12px;color:#1e40af">
      ℹ️ Role sistem — slug tidak dapat diubah, namun label, warna, dan permission dapat diedit.</div>` : ''}
  `, [
    { label: 'Batal',  cls: 'btn-secondary', action: closeModal },
    { label: 'Simpan', cls: 'btn-primary',   action: () => saveRole(isEdit ? role.id : null) },
  ]);
}

async function saveRole(id) {
  const data = {
    slug:  document.getElementById('fr_slug')?.value?.trim(),
    label: document.getElementById('fr_label')?.value?.trim(),
    warna: document.getElementById('fr_warna')?.value,
  };
  if (!data.label) { showToast('Nama role wajib diisi.', 'error'); return; }
  if (!id && !data.slug) { showToast('Slug wajib diisi.', 'error'); return; }

  const res = id ? await apiPut(`roles/${id}`, data) : await apiPost('roles', data);
  if (res?.status) {
    showToast(id ? 'Role diperbarui.' : 'Role dibuat.');
    closeModal();
    await _loadRolesCache();
    _renderRolesPanel();
    usersLoadInternal(); // refresh tabel agar dropdown role ikut update
  } else showToast(res?.message || 'Gagal menyimpan.', 'error');
}

async function roleDelete(id, label) {
  openModal('Hapus Role', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">🗑️</div>
      <div style="font-size:14px;font-weight:700;margin-bottom:6px">Hapus role <strong>${esc(label)}</strong>?</div>
      <div style="font-size:13px;color:#6b7280">Role yang masih dipakai pengguna tidak dapat dihapus.</div>
    </div>`, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: 'Hapus', cls: 'btn-danger', action: async () => {
      const res = await apiDelete(`roles/${id}`);
      if (!res?.status) { showToast(res?.message||'Gagal.','error'); return; }
      closeModal(); showToast(`Role ${label} dihapus.`);
      await _loadRolesCache(); _renderRolesPanel(); usersLoadInternal();
    }},
  ]);
}

// ════════════════════════════════════════════════════════════
// Helpers
// ════════════════════════════════════════════════════════════
function _roleLabel(slug) {
  const r = _rolesCache.find(r => r.slug === slug);
  return r ? r.label : roleLabel(slug);
}

function _roleWarna(slug) {
  const r = _rolesCache.find(r => r.slug === slug);
  return r ? r.warna : '#6b7280';
}
