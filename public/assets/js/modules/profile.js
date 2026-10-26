/* ============================================================
   profile.js — Slide-over profile panel (all roles)
   ============================================================ */
'use strict';

// ---------------------------------------------------------------
// Open / Close
// ---------------------------------------------------------------

/** Normal open — info tab */
function openProfilePanel() {
  const u = currentUser;
  if (!u) return;

  // Populate avatar initials
  const initials = (u.nama || 'U').split(' ').slice(0, 2).map(w => w[0].toUpperCase()).join('');
  document.getElementById('profileAvatar').textContent = initials;

  // Sub-header
  document.getElementById('profilePanelSub').textContent = roleLabel(u.role);

  // Fill form fields
  document.getElementById('profileNama').value    = u.nama    || '';
  document.getElementById('profileJabatan').value = u.jabatan || '';
  document.getElementById('profileEmail').value   = u.email   || '';
  document.getElementById('profileRole').value    = roleLabel(u.role);

  // Reset password form
  document.getElementById('passwordForm').reset();

  // Show info tab by default
  switchProfileTab('info');

  // Show/hide close button based on must_change_password
  const closeBtn = document.getElementById('profilePanelClose');
  if (closeBtn) {
    closeBtn.style.display = parseInt(currentUser.must_change_password) === 1 ? 'none' : '';
  }

  // Show panel
  document.getElementById('profileOverlay').classList.add('active');
  document.getElementById('profilePanel').classList.add('active');
  _profileUpdateWarningBanner();
}

/** Forced open — password tab with mandatory-change warning */
function openProfilePanelForcePassword() {
  openProfilePanel();
  switchProfileTab('password');
}

function closeProfilePanel() {
  // Blokir penutupan jika user wajib ganti password
  if (parseInt(currentUser && currentUser.must_change_password) === 1) {
    showError('Anda harus mengganti password terlebih dahulu sebelum melanjutkan.');
    return;
  }
  document.getElementById('profileOverlay').classList.remove('active');
  document.getElementById('profilePanel').classList.remove('active');
}

function switchProfileTab(tab) {
  document.querySelectorAll('.profile-tab').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tab);
  });
  document.querySelectorAll('.profile-tab-content').forEach(el => {
    el.classList.toggle('active', el.id === `profileTab${tab.charAt(0).toUpperCase() + tab.slice(1)}`);
  });
}

// ---------------------------------------------------------------
// Init — wire up all listeners once DOM is ready
// ---------------------------------------------------------------
(function initProfile() {
  // Trigger: click on sidebar user-info area
  document.getElementById('userInfo').addEventListener('click', openProfilePanel);

  // Close button
  document.getElementById('profilePanelClose').addEventListener('click', closeProfilePanel);

  // Overlay click → close
  document.getElementById('profileOverlay').addEventListener('click', closeProfilePanel);

  // Tab switching
  document.querySelectorAll('.profile-tab').forEach(btn => {
    btn.addEventListener('click', () => switchProfileTab(btn.dataset.tab));
  });

  // Show/hide password (eye icons)
  document.querySelectorAll('.input-eye-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      input.type  = input.type === 'password' ? 'text' : 'password';
    });
  });

  // ---- Form: Update Profile ----
  document.getElementById('profileForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('profileSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Menyimpan…';

    const payload = {
      nama:    document.getElementById('profileNama').value.trim(),
      jabatan: document.getElementById('profileJabatan').value.trim(),
      email:   document.getElementById('profileEmail').value.trim(),
    };

    const res = await apiPut('auth/profile', payload);
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z"/></svg> Simpan Perubahan';

    if (!res || !res.status) {
      showError(res?.message || 'Gagal menyimpan profil.');
      return;
    }

    // Update local state + localStorage
    const updated = res.data;
    currentUser.nama    = updated.nama;
    currentUser.jabatan = updated.jabatan;
    currentUser.email   = updated.email;
    localStorage.setItem('mka_user', JSON.stringify(currentUser));

    // Refresh sidebar user info display
    document.getElementById('userInfo').innerHTML = `
      <div class="user-name">${esc(currentUser.nama)}</div>
      <div class="user-role">${roleLabel(currentUser.role)}</div>
    `;

    // Refresh panel sub-header + avatar
    document.getElementById('profilePanelSub').textContent = roleLabel(currentUser.role);
    const initials = (currentUser.nama || 'U').split(' ').slice(0, 2).map(w => w[0].toUpperCase()).join('');
    document.getElementById('profileAvatar').textContent = initials;

    showSuccess('Profil berhasil diperbarui.');
  });

  // ---- Form: Change Password ----
  document.getElementById('passwordForm').addEventListener('submit', async e => {
    e.preventDefault();

    const np  = document.getElementById('newPassword').value;
    const npc = document.getElementById('newPasswordConfirm').value;
    if (np !== npc) { showError('Konfirmasi password tidak cocok.'); return; }

    const btn = document.getElementById('passwordSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Menyimpan…';

    const res = await apiPost('auth/change-password', {
      current_password:     document.getElementById('currentPassword').value,
      new_password:         np,
      new_password_confirm: npc,
    });

    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M18 8h-1V6A5 5 0 0 0 7 6v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zm-6 9a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm3.1-9H8.9V6A3.1 3.1 0 0 1 12 2.9 3.1 3.1 0 0 1 15.1 6v2z"/></svg> Ubah Password';

    if (!res || !res.status) {
      showError(res?.message || 'Gagal mengubah password.');
      return;
    }

    document.getElementById('passwordForm').reset();

    // Hapus flag must_change_password dari localStorage dan state
    currentUser.must_change_password = 0;
    localStorage.setItem('mka_user', JSON.stringify(currentUser));
    _profileUpdateWarningBanner();

    showSuccess('Password berhasil diubah.');
  });
})();

// ---------------------------------------------------------------
// Warning banner — shown when must_change_password = 1
// ---------------------------------------------------------------
function _profileUpdateWarningBanner() {
  const banner = document.getElementById('profileMustChangeBanner');
  if (!banner) return;
  const must = parseInt(currentUser.must_change_password) === 1;
  banner.style.display = must ? 'flex' : 'none';
}
