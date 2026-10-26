/* ============================================================
   teams.js — Manajemen Tim
   ============================================================ */
'use strict';

async function renderTeams() {
  const canManage = hasPermission('teams.manage');
  const [teamsRes, usersRes] = await Promise.all([
    apiGet('teams'),
    canManage ? apiGet('users') : Promise.resolve({ data: [] }),
  ]);
  const teams = teamsRes?.data || [];
  const users = usersRes?.data || [];

  document.getElementById('mainContent').innerHTML = `
    <div class="section-header">
      <div class="section-title">Manajemen Tim (${teams.length})</div>
      ${canManage ? `<button class="btn btn-primary" onclick="showTeamForm(null, ${JSON.stringify(users).replace(/"/g,'&quot;')})">+ Tambah Tim</button>` : ''}
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nama Tim</th><th>Ketua Tim</th><th>Anggota</th>${canManage ? '<th></th>' : ''}</tr></thead>
        <tbody>
          ${teams.map(t => `
            <tr>
              <td style="font-weight:600">${esc(t.nama_tim)}</td>
              <td>${esc(t.ketua_tim_nama || '—')}</td>
              <td>${t.jumlah_anggota || 0} orang</td>
              ${canManage ? `<td style="display:flex;gap:4px">
                <button class="btn-icon" onclick="showTeamDetail(${t.id})">👁️</button>
                <button class="btn-icon" onclick="showTeamForm(${JSON.stringify(t).replace(/"/g,'&quot;')}, ${JSON.stringify(users).replace(/"/g,'&quot;')})">✏️</button>
                <button class="btn-icon" onclick="confirmDeleteTeam(${t.id},'${esc(t.nama_tim)}')">🗑️</button>
              </td>` : `<td><button class="btn-icon" onclick="showTeamDetail(${t.id})">👁️</button></td>`}
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
}

async function showTeamDetail(id) {
  const res = await apiGet(`teams/${id}`);
  if (!res || !res.status) return;
  const team = res.data;
  openModal(team.nama_tim, `
    <div style="margin-bottom:12px"><b>Ketua:</b> ${esc(team.ketua_tim_nama || '—')}</div>
    <div style="font-weight:600;margin-bottom:8px">Anggota Tim:</div>
    ${team.anggota.map(a => `
      <div style="padding:8px 0;border-bottom:1px solid #f1f5f9">
        <span>${esc(a.nama)}</span>
      </div>
    `).join('')}
  `, [{ label: 'Tutup', cls: 'btn-outline', action: 'closeModal()' }]);
}

function showTeamForm(team, users) {
  const isEdit    = !!team;
  // Hanya pengguna dengan posisi_tim = 'Ketua Tim' yang bisa dipilih sebagai ketua
  const ketuaOpts = users
    .filter(u => u.posisi_tim === 'Ketua Tim' || team?.ketua_tim_id == u.id)
    .map(u => `<option value="${u.id}" ${team?.ketua_tim_id==u.id?'selected':''}>${esc(u.nama)}</option>`).join('');
  const anggOpts  = users.filter(u => u.role !== 'admin').map(u => `<option value="${u.id}">${esc(u.nama)}</option>`).join('');

  openModal(isEdit ? 'Edit Tim' : 'Tambah Tim', `
    <div class="form-group"><label>Nama Tim *</label><input type="text" id="ft_nama" value="${esc(team?.nama_tim || '')}"></div>
    <div class="form-group"><label>Ketua Tim *</label><select id="ft_ketua"><option value="">— Pilih —</option>${ketuaOpts}</select></div>
    <div class="form-group">
      <label>Anggota Tim (Ctrl+Click untuk multi)</label>
      <select id="ft_anggota" multiple style="min-height:120px">${anggOpts}</select>
    </div>
  `, [
    { label: 'Batal', cls: 'btn-outline', action: 'closeModal()' },
    { label: 'Simpan', cls: 'btn-primary', action: `saveTeam(${isEdit ? team.id : 'null'})` },
  ]);
}

async function saveTeam(id) {
  const anggota = Array.from(document.getElementById('ft_anggota').selectedOptions).map(o => o.value);
  const data = {
    nama_tim:     document.getElementById('ft_nama')?.value?.trim(),
    ketua_tim_id: document.getElementById('ft_ketua')?.value,
    anggota,
  };
  if (!data.nama_tim || !data.ketua_tim_id) { showToast('Nama tim dan ketua wajib diisi', 'error'); return; }

  const res = id ? await apiPut(`teams/${id}`, data) : await apiPost('teams', data);
  if (res && res.status) { showToast(id ? 'Tim diperbarui' : 'Tim dibuat', 'success'); closeModal(); renderTeams(); }
  else showToast(res?.message || 'Gagal menyimpan', 'error');
}

async function confirmDeleteTeam(id, nama) {
  if (!confirm(`Hapus tim "${nama}"?`)) return;
  const res = await apiDelete(`teams/${id}`);
  if (res && res.status) { showToast('Tim dihapus', 'success'); renderTeams(); }
  else showToast(res?.message || 'Gagal', 'error');
}
