// ============================================================
// schedule.js — Modul Schedule (FullCalendar 6 + Google Calendar)
// ============================================================
'use strict'

// ── FullCalendar CDN loader ─────────────────────────────────
let _fcLoaded = false
async function _ensureFullCalendar() {
  if (_fcLoaded || (typeof FullCalendar !== 'undefined')) { _fcLoaded = true; return }
  await new Promise((res, rej) => {
    const s = document.createElement('script')
    s.src   = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'
    s.onload  = () => { _fcLoaded = true; res() }
    s.onerror = rej
    document.head.appendChild(s)
  })
}

// ── Kategori warna (fallback jika API belum muat) ──────────
const SCH_CAT_COLORS = {
  internal:   '#3b82f6',
  rapat:      '#8b5cf6',
  on_budget:  '#10b981',
  off_budget: '#f59e0b',
  telaah:     '#ef4444',
  libur:      '#9ca3af',
  lainnya:    '#6366f1',
}

let _schCategories  = []
let _calendarInst   = null   // FullCalendar instance
let _calendarUsers  = []     // Daftar users untuk dropdown PIC/Peserta

// ──────────────────────────────────────────────────────────────
// HALAMAN UTAMA — Dashboard Schedule dengan FullCalendar
// ──────────────────────────────────────────────────────────────
async function renderSchedule() {
  const content = document.getElementById('mainContent')
  const isAdminTu = ['admin','tu'].includes(currentUser?.role)
  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:flex-start;
                margin-bottom:16px;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-size:18px;font-weight:700;color:var(--gray-800)">Schedule Kegiatan</div>
        <div style="font-size:12px;color:#6b7280;margin-top:2px">Kalender terintegrasi Google Calendar</div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <!-- Filter Kategori -->
        <select id="schFilterCat" class="form-control" style="width:170px;font-size:13px" onchange="schRefreshCalendar()">
          <option value="">Semua Kategori</option>
        </select>
        <!-- Search -->
        <input type="text" id="schSearch" class="form-control" placeholder="Cari agenda…"
               style="width:180px;font-size:13px" oninput="schRefreshCalendar()">
        ${isAdminTu ? `
        <button class="btn btn-sm" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0"
                onclick="schShowPullModal()" title="Import jadwal dari Google Calendar ke MKA">
          ⬇ Import dari Google
        </button>` : ''}
        <button class="btn btn-primary btn-sm" onclick="schShowForm()">
          <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
          Tambah Agenda
        </button>
      </div>
    </div>

    <!-- Legenda warna — diisi ulang setelah kategori dimuat dari API -->
    <div id="schCatLegend" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;font-size:11px"></div>

    <!-- FullCalendar container -->
    <div class="card" style="padding:16px">
      <div id="schCalendarEl" style="min-height:520px"></div>
    </div>

    <!-- Widget stats bawah -->
    <div id="schWidgets" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-top:16px">
      <div class="spinner-sm" style="grid-column:1/-1;margin:8px auto"></div>
    </div>`

  // Load kategori & users secara paralel
  await Promise.allSettled([_schLoadCategories(), _schLoadUsers()])

  // Render legenda warna dari data API (bukan hardcode)
  const legendEl = document.getElementById('schCatLegend')
  if (legendEl) {
    if (_schCategories.length) {
      legendEl.innerHTML = _schCategories.map(c =>
        `<span style="display:flex;align-items:center;gap:4px">
          <span style="width:10px;height:10px;border-radius:50%;background:${esc(c.warna||'#6366f1')};flex-shrink:0"></span>
          <span style="color:#374151">${esc(c.nama)}</span>
        </span>`
      ).join('')
    } else {
      // Fallback ke hardcode jika API gagal
      legendEl.innerHTML = Object.entries(SCH_CAT_COLORS).map(([k,c]) =>
        `<span style="display:flex;align-items:center;gap:4px">
          <span style="width:10px;height:10px;border-radius:50%;background:${c};flex-shrink:0"></span>
          <span style="color:#374151">${_schCatLabel(k)}</span>
        </span>`
      ).join('')
    }
  }

  // Render FullCalendar
  await _ensureFullCalendar()
  _schInitCalendar()

  // Load widget stats
  _schLoadWidgets()
}

// ──────────────────────────────────────────────────────────────
// Inisialisasi FullCalendar
// ──────────────────────────────────────────────────────────────
function _schInitCalendar() {
  const el = document.getElementById('schCalendarEl')
  if (!el) return

  if (_calendarInst) { _calendarInst.destroy(); _calendarInst = null }

  _calendarInst = new FullCalendar.Calendar(el, {
    locale:          'id',
    initialView:     'dayGridMonth',
    headerToolbar: {
      left:   'prev,next today',
      center: 'title',
      right:  'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
    },
    buttonText: {
      today:     'Hari Ini',
      month:     'Bulan',
      week:      'Minggu',
      day:       'Hari',
      list:      'Daftar',
    },
    height:          'auto',
    editable:        true,
    selectable:      true,
    nowIndicator:    true,
    dayMaxEvents:    3,

    // Load events dari API
    events: async (info, successCb, failureCb) => {
      try {
        const catId = document.getElementById('schFilterCat')?.value || ''
        const q     = document.getElementById('schSearch')?.value    || ''
        let url = `schedule/events?start=${info.startStr.slice(0,10)}&end=${info.endStr.slice(0,10)}`
        if (catId) url += `&category_id=${catId}`
        if (q)     url += `&q=${encodeURIComponent(q)}`
        const res = await apiGet(url)
        successCb(res?.data || [])
      } catch(e) {
        failureCb(e)
      }
    },

    // Klik event → tampilkan detail
    eventClick: (info) => {
      schShowDetail(info.event)
    },

    // Klik tanggal kosong → form tambah dengan tanggal terisi
    dateClick: (info) => {
      schShowForm(null, info.dateStr)
    },

    // Drag & drop → update tanggal
    eventDrop: async (info) => {
      const id    = info.event.id
      const start = info.event.startStr.slice(0, 10)
      const end   = (info.event.endStr || info.event.startStr).slice(0, 10)
      try {
        await apiPut(`schedule/${id}`, { tgl_mulai: start, tgl_selesai: end })
        showToast('Jadwal dipindahkan.')
      } catch(e) {
        showToast(e.message, 'error')
        info.revert()
      }
    },

    // Resize event → update tanggal selesai
    eventResize: async (info) => {
      const id  = info.event.id
      const end = info.event.endStr?.slice(0, 10) || info.event.startStr.slice(0, 10)
      try {
        await apiPut(`schedule/${id}`, { tgl_selesai: end })
        showToast('Durasi agenda diperbarui.')
      } catch(e) {
        showToast(e.message, 'error')
        info.revert()
      }
    },

    // Tooltip saat hover
    eventDidMount: (info) => {
      const p = info.event.extendedProps
      info.el.title = [
        p.kode,
        p.lokasi   ? '📍 ' + p.lokasi   : '',
        p.pic_nama ? '👤 ' + p.pic_nama : '',
        p.sync_status === 'synced' ? '🔄 Google Calendar' : '',
      ].filter(Boolean).join('\n')
    },
  })

  _calendarInst.render()
}

function schRefreshCalendar() {
  _calendarInst?.refetchEvents()
}

// ──────────────────────────────────────────────────────────────
// Load kategori untuk dropdown filter + form
// ──────────────────────────────────────────────────────────────
async function _schLoadCategories() {
  try {
    const res = await apiGet('schedule/categories')
    _schCategories = res.data?.items || []

    // Isi dropdown filter
    const sel = document.getElementById('schFilterCat')
    if (sel) {
      sel.innerHTML = '<option value="">Semua Kategori</option>' +
        _schCategories.map(c => `<option value="${c.id}">${esc(c.nama)}</option>`).join('')
    }
  } catch(e) {}
}

async function _schLoadUsers() {
  try {
    const res = await apiGet('users?limit=100')
    _calendarUsers = res.data?.items || res.data || []
  } catch(e) {}
}

// ──────────────────────────────────────────────────────────────
// Widget Stats
// ──────────────────────────────────────────────────────────────
async function _schLoadWidgets() {
  const el = document.getElementById('schWidgets')
  if (!el) return
  try {
    const res = await apiGet('schedule/widget-stats')
    const d   = res.data || {}
    el.innerHTML = `
      ${_schStatCard('Agenda Hari Ini',  d.hari_ini    ?? 0, '#3b82f6', '📅')}
      ${_schStatCard('Minggu Ini',       d.minggu_ini  ?? 0, '#10b981', '📆')}
      ${_schStatCard('Bulan Ini',        d.bulan_ini   ?? 0, '#6366f1', '🗓️')}
      ${_schStatCard('Deadline Telaah',  d.deadline_telaah ?? 0, '#ef4444', '⏰')}`
  } catch(e) {}
}

function _schStatCard(label, value, color, icon) {
  return `<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;text-align:center">
    <div style="font-size:20px;margin-bottom:4px">${icon}</div>
    <div style="font-size:22px;font-weight:700;color:${color}">${value}</div>
    <div style="font-size:11px;color:#6b7280;margin-top:2px">${label}</div>
  </div>`
}

// ──────────────────────────────────────────────────────────────
// FORM TAMBAH / EDIT AGENDA — desain ulang
// ──────────────────────────────────────────────────────────────
async function schShowForm(id = null, defaultDate = null) {
  _schAttendeeIdx = 0   // reset counter setiap kali form dibuka
  const isEdit = !!id
  let existing = {}

  if (isEdit) {
    openModal('Memuat…', '<div class="loading-page"><div class="spinner-lg"></div></div>')
    try {
      const res = await apiGet(`schedule/${id}`)
      existing  = res.data || {}
    } catch(e) { openModal('Error', `<div class="alert alert-danger">${e.message}</div>`); return }
  }

  const catOptions = _schCategories.map(c =>
    `<option value="${c.id}" ${existing.category_id == c.id ? 'selected' : ''}>${esc(c.nama)}</option>`
  ).join('')

  const picOptions = _calendarUsers.map(u =>
    `<option value="${u.id}" ${existing.pic_user_id == u.id ? 'selected' : ''}>
      ${esc(u.nama)}${u.jabatan ? ' — ' + esc(u.jabatan) : ''}
    </option>`
  ).join('')

  // Daftar user sebagai opsi peserta (kecuali yang sudah jadi PIC)
  const pesertaUserOptions = '<option value="">— Pilih dari daftar pengguna —</option>' +
    _calendarUsers.map(u =>
      `<option value="${u.id}" data-nama="${esc(u.nama)}" data-email="${esc(u.email||'')}">
        ${esc(u.nama)}${u.jabatan ? ' — ' + esc(u.jabatan) : ''}
      </option>`
    ).join('')

  // Render baris peserta yang sudah ada (mode edit)
  const existingAttendeesHtml = (existing.attendees || []).map((a, i) =>
    _schAttendeRow(i, a.user_id, a.user_nama || a.nama, a.email, a.status_rsvp)
  ).join('')

  const today    = defaultDate || existing.tgl_mulai    || new Date().toISOString().slice(0,10)
  const todayEnd = existing.tgl_selesai || today

  // Chip warna — warna aktif ditandai outline tebal + checkmark
  const WARNAS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#6366f1','#ec4899','#9ca3af']
  const warnaChips = WARNAS.map(c => {
    const aktif = (existing.warna||'') === c
    return `<label style="cursor:pointer;position:relative" title="${c}">
      <input type="radio" name="schWarna" value="${c}" ${aktif?'checked':''} style="display:none"
             onchange="_schWarnaSelect(this)">
      <span style="display:flex;align-items:center;justify-content:center;width:26px;height:26px;
                   border-radius:50%;background:${c};
                   outline:${aktif?'3px solid #1f2328':'3px solid transparent'};
                   outline-offset:2px;transition:outline .15s;font-size:13px">
        ${aktif ? '✓' : ''}
      </span>
    </label>`
  }).join('')

  const syncStatus = existing.google_sync_status
  const syncBadgeHtml = syncStatus === 'synced'
    ? `<span style="font-size:10px;background:#dcfce7;color:#166534;border-radius:99px;
                    padding:2px 8px;font-weight:600;margin-left:auto">✅ Sudah sinkron</span>`
    : syncStatus === 'failed'
    ? `<span style="font-size:10px;background:#fee2e2;color:#b91c1c;border-radius:99px;
                    padding:2px 8px;font-weight:600;margin-left:auto">❌ Gagal sinkron</span>`
    : ''

  const body = `
    <div style="display:flex;flex-direction:column;gap:0">

      <!-- ── Identitas ── -->
      <div style="background:#f8fafc;border-radius:10px;padding:14px 16px;margin-bottom:12px;
                  border:1px solid #e5e7eb">
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;
                    letter-spacing:.6px;margin-bottom:10px">Informasi Utama</div>

        <div class="form-group" style="margin-bottom:10px">
          <label class="form-label" style="font-size:12px;font-weight:600">
            Judul Kegiatan <span class="required">*</span>
          </label>
          <input type="text" class="form-control" id="schJudul"
                 value="${esc(existing.judul||'')}"
                 placeholder="Nama agenda atau kegiatan"
                 style="font-size:13px">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0">
            <label class="form-label" style="font-size:12px;font-weight:600">
              Kategori <span class="required">*</span>
            </label>
            <select class="form-control" id="schCategory" style="font-size:13px">
              ${catOptions || '<option value="">— Belum ada kategori —</option>'}
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label" style="font-size:12px;font-weight:600">PIC / Penanggung Jawab</label>
            <select class="form-control" id="schPic" style="font-size:13px">
              <option value="">— Tidak ada —</option>
              ${picOptions}
            </select>
          </div>
        </div>
      </div>

      <!-- ── Waktu ── -->
      <div style="background:#f8fafc;border-radius:10px;padding:14px 16px;margin-bottom:12px;
                  border:1px solid #e5e7eb">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
          <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;
                      letter-spacing:.6px">
            Waktu Pelaksanaan
          </div>
          <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;
                         color:#374151;background:#fff;border:1px solid #d1d5db;border-radius:6px;
                         padding:4px 10px 4px 8px;user-select:none">
            <input type="checkbox" id="schAllDay" ${existing.all_day ? 'checked' : ''}
                   onchange="_schToggleAllDay(this.checked)"
                   style="width:14px;height:14px;margin:0;flex-shrink:0;cursor:pointer">
            <span style="white-space:nowrap">Seharian (All Day)</span>
          </label>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group" style="margin:0">
            <label class="form-label" style="font-size:12px;font-weight:600">
              Tanggal Mulai <span class="required">*</span>
            </label>
            <input type="date" class="form-control" id="schTglMulai"
                   value="${today}" style="font-size:13px"
                   onchange="if(document.getElementById('schTglSelesai').value<this.value)document.getElementById('schTglSelesai').value=this.value">
          </div>
          <div class="form-group" style="margin:0" id="schJamMulaiWrap">
            <label class="form-label" style="font-size:12px;font-weight:600">Jam Mulai</label>
            <input type="time" class="form-control" id="schJamMulai"
                   value="${existing.jam_mulai?.slice(0,5)||'08:00'}" style="font-size:13px">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
          <div class="form-group" style="margin:0">
            <label class="form-label" style="font-size:12px;font-weight:600">
              Tanggal Selesai <span class="required">*</span>
            </label>
            <input type="date" class="form-control" id="schTglSelesai"
                   value="${todayEnd}" style="font-size:13px">
          </div>
          <div class="form-group" style="margin:0" id="schJamSelesaiWrap">
            <label class="form-label" style="font-size:12px;font-weight:600">Jam Selesai</label>
            <input type="time" class="form-control" id="schJamSelesai"
                   value="${existing.jam_selesai?.slice(0,5)||'09:00'}" style="font-size:13px">
          </div>
        </div>
      </div>

      <!-- ── Lokasi & Deskripsi ── -->
      <div style="background:#f8fafc;border-radius:10px;padding:14px 16px;margin-bottom:12px;
                  border:1px solid #e5e7eb">
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;
                    letter-spacing:.6px;margin-bottom:10px">Keterangan</div>

        <div class="form-group" style="margin-bottom:10px">
          <label class="form-label" style="font-size:12px;font-weight:600">
            <svg viewBox="0 0 24 24" fill="#6b7280" width="12" height="12" style="margin-right:3px">
              <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
            </svg>
            Lokasi
          </label>
          <input type="text" class="form-control" id="schLokasi"
                 value="${esc(existing.lokasi||'')}"
                 placeholder="Ruang rapat, URL meeting (Zoom/Meet), atau alamat"
                 style="font-size:13px">
        </div>

        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:12px;font-weight:600">Deskripsi / Catatan</label>
          <textarea class="form-control" id="schDeskripsi" rows="3"
                    placeholder="Agenda, tujuan, atau catatan tambahan untuk kegiatan ini"
                    style="font-size:13px;resize:vertical">${esc(existing.deskripsi||'')}</textarea>
        </div>
      </div>

      <!-- ── Peserta ── -->
      <div style="background:#f8fafc;border-radius:10px;padding:14px 16px;margin-bottom:12px;
                  border:1px solid #e5e7eb">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
          <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;
                      letter-spacing:.6px">Peserta Kegiatan</div>
          <span style="font-size:11px;color:#9ca3af">opsional</span>
        </div>

        <!-- Daftar peserta -->
        <div id="schAttendeeList" style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px">
          ${existingAttendeesHtml ||
            `<div id="schAttendeeEmpty" style="font-size:12px;color:#9ca3af;padding:6px 0">
               Belum ada peserta ditambahkan.
             </div>`}
        </div>

        <!-- Tambah peserta -->
        <div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end">
          <div>
            <label class="form-label" style="font-size:11px;font-weight:600;margin-bottom:4px">
              Tambah dari Pengguna
            </label>
            <select class="form-control" id="schAddAttendeeSel" style="font-size:12px">
              ${pesertaUserOptions}
            </select>
          </div>
          <button type="button" class="btn btn-sm btn-secondary"
                  style="height:36px;padding:0 14px;font-size:12px"
                  onclick="_schAddAttendeeFromSelect()">+ Tambah</button>
        </div>

        <!-- Atau tambah manual (email eksternal) -->
        <div style="margin-top:8px">
          <label class="form-label" style="font-size:11px;font-weight:600;margin-bottom:4px">
            Atau masukkan nama/email manual
          </label>
          <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:6px;align-items:end">
            <input type="text" class="form-control" id="schAttendeeManualNama"
                   placeholder="Nama peserta" style="font-size:12px">
            <input type="email" class="form-control" id="schAttendeeManualEmail"
                   placeholder="email@domain.go.id" style="font-size:12px">
            <button type="button" class="btn btn-sm btn-secondary"
                    style="height:36px;padding:0 14px;font-size:12px"
                    onclick="_schAddAttendeeManual()">+ Tambah</button>
          </div>
        </div>
      </div>

      <!-- ── Tampilan & Sinkronisasi ── -->
      <div style="background:#f8fafc;border-radius:10px;padding:14px 16px;
                  border:1px solid #e5e7eb">
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;
                    letter-spacing:.6px;margin-bottom:12px">Tampilan & Sinkronisasi</div>

        <!-- Warna -->
        <div style="margin-bottom:14px">
          <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:8px">
            Warna Event
            <span style="font-weight:400;color:#9ca3af;margin-left:4px">— opsional, override warna kategori</span>
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            ${warnaChips}
            <label style="cursor:pointer;display:flex;align-items:center;gap:4px;
                           font-size:11px;color:#6b7280;margin-left:4px" title="Ikuti warna kategori otomatis">
              <input type="radio" name="schWarna" value="" ${!existing.warna?'checked':''} style="display:none"
                     onchange="_schWarnaSelect(this)">
              <span style="width:26px;height:26px;border-radius:50%;background:#f3f4f6;
                           display:flex;align-items:center;justify-content:center;font-size:10px;
                           outline:${!existing.warna?'3px solid #1f2328':'3px solid transparent'};
                           outline-offset:2px;transition:outline .15s">A</span>
            </label>
          </div>
        </div>

        <!-- Sync Google Calendar -->
        <div style="border-top:1px solid #e5e7eb;padding-top:12px">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;
                         background:${(existing.sync_google??1)?'#eff6ff':'#f9fafb'};
                         border:1px solid ${(existing.sync_google??1)?'#bfdbfe':'#e5e7eb'};
                         border-radius:8px;padding:10px 14px;transition:background .2s;
                         overflow:hidden">
            <input type="checkbox" id="schSyncGoogle" ${(existing.sync_google ?? 1) ? 'checked' : ''}
                   style="flex-shrink:0;width:15px;height:15px;cursor:pointer"
                   onchange="this.closest('label').style.background=this.checked?'#eff6ff':'#f9fafb';
                             this.closest('label').style.borderColor=this.checked?'#bfdbfe':'#e5e7eb'">
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;font-weight:600;color:#1e40af;white-space:nowrap;
                          overflow:hidden;text-overflow:ellipsis">
                🔄 Sinkronkan ke Google Calendar
              </div>
              <div style="font-size:11px;color:#6b7280;margin-top:1px">
                Event akan otomatis ditambahkan / diperbarui di Google Calendar
              </div>
            </div>
            ${syncBadgeHtml}
          </label>
        </div>
      </div>

    </div>`

  openModal(isEdit ? `✏️ Edit Agenda — ${esc(existing.kode||'')}` : '➕ Tambah Agenda Baru', body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: isEdit ? 'Simpan Perubahan' : 'Simpan Agenda', cls: 'btn-primary', action: async () => {
      const judul = document.getElementById('schJudul')?.value.trim()
      const catId = document.getElementById('schCategory')?.value
      const tglM  = document.getElementById('schTglMulai')?.value
      const tglS  = document.getElementById('schTglSelesai')?.value
      if (!judul)  { showToast('Judul kegiatan wajib diisi.', 'error'); return }
      if (!catId)  { showToast('Kategori wajib dipilih.', 'error'); return }
      if (!tglM || !tglS) { showToast('Tanggal mulai dan selesai wajib diisi.', 'error'); return }
      if (tglM > tglS) { showToast('Tanggal selesai tidak boleh sebelum tanggal mulai.', 'error'); return }

      const warna     = document.querySelector('input[name="schWarna"]:checked')?.value || null
      const allDay    = document.getElementById('schAllDay')?.checked
      const attendees = _schCollectAttendees()
      const payload   = {
        judul,
        category_id:  parseInt(catId),
        tgl_mulai:    tglM,
        jam_mulai:    allDay ? null : (document.getElementById('schJamMulai')?.value  || null),
        tgl_selesai:  tglS,
        jam_selesai:  allDay ? null : (document.getElementById('schJamSelesai')?.value || null),
        all_day:      allDay ? 1 : 0,
        lokasi:       document.getElementById('schLokasi')?.value.trim()    || null,
        pic_user_id:  document.getElementById('schPic')?.value              || null,
        deskripsi:    document.getElementById('schDeskripsi')?.value.trim() || null,
        warna:        warna || null,
        sync_google:  document.getElementById('schSyncGoogle')?.checked ? 1 : 0,
        attendees,
      }
      try {
        const res = isEdit
          ? await apiPut(`schedule/${id}`, payload)
          : await apiPost('schedule', payload)
        closeModal()
        const warn = res.data?.sync_warning
        showToast((res.message || 'Agenda berhasil disimpan.') + (warn ? ` ⚠️ ${warn}` : ''))
        schRefreshCalendar()
        _schLoadWidgets()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])

  // Terapkan state all-day saat buka
  if (existing.all_day) setTimeout(() => _schToggleAllDay(true), 50)
}

// Helper: toggle field jam saat all-day dicentang
function _schToggleAllDay(isAllDay) {
  const jamM = document.getElementById('schJamMulaiWrap')
  const jamS = document.getElementById('schJamSelesaiWrap')
  if (!jamM || !jamS) return
  const opacity = isAllDay ? '0.35' : '1'
  jamM.style.opacity        = opacity
  jamS.style.opacity        = opacity
  jamM.querySelector('input').disabled = isAllDay
  jamS.querySelector('input').disabled = isAllDay
}

// Helper: update outline chip warna setelah klik
function _schWarnaSelect(radio) {
  document.querySelectorAll('input[name="schWarna"]').forEach(r => {
    const span = r.parentElement.querySelector('span')
    if (!span) return
    const isMe = r === radio
    span.style.outline = isMe ? '3px solid #1f2328' : '3px solid transparent'
    span.textContent   = (isMe && r.value) ? '✓' : (r.value ? '' : 'A')
  })
}

// ──────────────────────────────────────────────────────────────
// PESERTA — helpers
// ──────────────────────────────────────────────────────────────

let _schAttendeeIdx = 0   // counter baris peserta

// Render satu baris peserta
function _schAttendeRow(idx, userId, nama, email, rsvp) {
  _schAttendeeIdx = Math.max(_schAttendeeIdx, idx + 1)
  const labelRsvp = { undangan:'Diundang', hadir:'Hadir', tidak_hadir:'Tidak Hadir', tentatif:'Tentatif' }
  const colorRsvp = { undangan:'#6b7280', hadir:'#16a34a', tidak_hadir:'#dc2626', tentatif:'#d97706' }
  const r = rsvp || 'undangan'
  return `
    <div id="schAttRow_${idx}" data-idx="${idx}"
         style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center;
                background:#fff;border:1px solid #e5e7eb;border-radius:7px;
                padding:8px 10px">
      <div style="min-width:0">
        <div style="font-size:12px;font-weight:600;color:#1f2328;
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          ${esc(nama || '—')}
        </div>
        <div style="font-size:11px;color:#6b7280;margin-top:1px;
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          ${email ? esc(email) : '<span style="color:#d1d5db">—</span>'}
          &nbsp;·&nbsp;
          <span style="color:${colorRsvp[r]}">${labelRsvp[r]||r}</span>
        </div>
      </div>
      <button type="button" onclick="_schRemoveAttendee(${idx})"
              style="background:#fee2e2;border:none;border-radius:5px;
                     color:#b91c1c;font-size:16px;line-height:1;
                     width:26px;height:26px;cursor:pointer;flex-shrink:0"
              title="Hapus peserta">×</button>
      <!-- hidden inputs -->
      <input type="hidden" name="att_uid_${idx}"   value="${userId  || ''}">
      <input type="hidden" name="att_nama_${idx}"  value="${esc(nama || '')}">
      <input type="hidden" name="att_email_${idx}" value="${esc(email || '')}">
      <input type="hidden" name="att_rsvp_${idx}"  value="${r}">
    </div>`
}

// Tambah peserta dari dropdown pengguna
function _schAddAttendeeFromSelect() {
  const sel  = document.getElementById('schAddAttendeeSel')
  if (!sel || !sel.value) { showToast('Pilih pengguna terlebih dahulu.', 'error'); return }
  const opt  = sel.selectedOptions[0]
  const uid  = sel.value
  const nama = opt.dataset.nama || opt.text.split(' — ')[0].trim()
  const email= opt.dataset.email || ''

  // Cek duplikat
  const list = document.getElementById('schAttendeeList')
  if (list.querySelector(`input[name^="att_uid_"][value="${uid}"]`)) {
    showToast('Pengguna ini sudah ditambahkan.', 'error'); return
  }

  _schAppendAttendee(uid, nama, email, 'undangan')
  sel.value = ''
}

// Tambah peserta manual (nama + email)
function _schAddAttendeeManual() {
  const namaEl  = document.getElementById('schAttendeeManualNama')
  const emailEl = document.getElementById('schAttendeeManualEmail')
  const nama    = namaEl?.value.trim()
  const email   = emailEl?.value.trim()
  if (!nama && !email) { showToast('Masukkan nama atau email peserta.', 'error'); return }

  _schAppendAttendee('', nama || email, email, 'undangan')
  if (namaEl)  namaEl.value  = ''
  if (emailEl) emailEl.value = ''
}

// Append baris ke DOM
function _schAppendAttendee(userId, nama, email, rsvp) {
  const list  = document.getElementById('schAttendeeList')
  if (!list) return
  // Hapus teks "belum ada"
  const empty = document.getElementById('schAttendeeEmpty')
  if (empty) empty.remove()

  const idx  = _schAttendeeIdx++
  const div  = document.createElement('div')
  div.innerHTML = _schAttendeRow(idx, userId, nama, email, rsvp)
  list.appendChild(div.firstElementChild)
}

// Hapus baris peserta
function _schRemoveAttendee(idx) {
  document.getElementById(`schAttRow_${idx}`)?.remove()
  const list = document.getElementById('schAttendeeList')
  if (list && !list.querySelector('[id^="schAttRow_"]')) {
    list.innerHTML = `<div id="schAttendeeEmpty" style="font-size:12px;color:#9ca3af;padding:6px 0">
      Belum ada peserta ditambahkan.</div>`
  }
}

// Kumpulkan semua peserta dari DOM
function _schCollectAttendees() {
  const list = document.getElementById('schAttendeeList')
  if (!list) return []
  const rows = list.querySelectorAll('[id^="schAttRow_"]')
  return Array.from(rows).map(row => {
    const idx = row.dataset.idx
    return {
      user_id:     row.querySelector(`input[name="att_uid_${idx}"]`)?.value   || null,
      nama:        row.querySelector(`input[name="att_nama_${idx}"]`)?.value  || null,
      email:       row.querySelector(`input[name="att_email_${idx}"]`)?.value || null,
      status_rsvp: row.querySelector(`input[name="att_rsvp_${idx}"]`)?.value  || 'undangan',
    }
  }).filter(a => a.user_id || a.nama || a.email)
}

// ──────────────────────────────────────────────────────────────
// DETAIL AGENDA (saat event di FullCalendar diklik)
// ──────────────────────────────────────────────────────────────
async function schShowDetail(fcEvent) {
  const id = fcEvent.id
  openModal('Detail Agenda', '<div class="spinner-sm"></div>')
  try {
    const res = await apiGet(`schedule/${id}`)
    const d   = res.data || {}

    const syncBadge = {
      synced:     '<span class="status-badge status-selesai" style="font-size:11px">✅ Synced</span>',
      pending:    '<span class="status-badge status-berjalan" style="font-size:11px">⏳ Pending</span>',
      failed:     '<span class="status-badge status-terlambat" style="font-size:11px">❌ Gagal Sync</span>',
      not_synced: '<span class="status-badge status-draft" style="font-size:11px">— Tidak Sync</span>',
    }

    // Tampilkan pesan error jika sync gagal
    const syncErrorBlock = d.google_sync_status === 'failed' && d.google_sync_error
      ? `<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 12px;
                      font-size:12px;color:#dc2626;margin-top:0">
           <div style="font-weight:700;margin-bottom:4px">⚠️ Keterangan Error:</div>
           <div style="word-break:break-word">${esc(d.google_sync_error)}</div>
         </div>`
      : ''

    const body = `
      <div style="display:grid;gap:12px">
        <!-- Header -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
          <div>
            <div style="font-size:15px;font-weight:700;color:#1f2328">${esc(d.judul)}</div>
            <div style="font-size:11px;color:#6b7280;margin-top:3px">${esc(d.kode)} · ${esc(d.cat_nama||'—')}</div>
          </div>
          <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
            ${syncBadge[d.google_sync_status] || ''}
          </div>
        </div>

        ${syncErrorBlock}

        <hr style="margin:4px 0">

        <!-- Info Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px">
          <div>
            <div style="font-weight:600;color:#374151">📅 Mulai</div>
            <div>${d.tgl_mulai}${d.jam_mulai ? ' ' + d.jam_mulai.slice(0,5) : ''}</div>
          </div>
          <div>
            <div style="font-weight:600;color:#374151">🏁 Selesai</div>
            <div>${d.tgl_selesai}${d.jam_selesai ? ' ' + d.jam_selesai.slice(0,5) : ''}</div>
          </div>
          ${d.lokasi ? `
          <div style="grid-column:1/-1">
            <div style="font-weight:600;color:#374151">📍 Lokasi</div>
            <div>${esc(d.lokasi)}</div>
          </div>` : ''}
          ${d.pic_nama ? `
          <div>
            <div style="font-weight:600;color:#374151">👤 PIC</div>
            <div>${esc(d.pic_nama)}</div>
          </div>` : ''}
          ${d.google_event_id ? `
          <div>
            <div style="font-weight:600;color:#374151">🔗 Google Event</div>
            <div style="font-size:11px;word-break:break-all;color:#3b82f6">${esc(d.google_event_id)}</div>
          </div>` : ''}
          ${d.google_synced_at ? `
          <div>
            <div style="font-weight:600;color:#374151">🕐 Terakhir Sync</div>
            <div style="font-size:12px">${d.google_synced_at.slice(0,16)}</div>
          </div>` : ''}
        </div>

        ${d.deskripsi ? `
        <div style="background:#f9fafb;border-radius:8px;padding:10px 12px;font-size:13px">
          <div style="font-weight:600;margin-bottom:4px">📝 Deskripsi</div>
          <div style="line-height:1.6;color:#374151">${esc(d.deskripsi)}</div>
        </div>` : ''}

        ${d.attendees?.length ? `
        <div>
          <div style="font-weight:600;font-size:13px;margin-bottom:6px">👥 Peserta (${d.attendees.length})</div>
          <div style="display:flex;flex-wrap:wrap;gap:6px">
            ${d.attendees.map(a =>
              `<span style="background:#f1f5f9;border-radius:99px;padding:3px 10px;font-size:12px">
                ${esc(a.user_nama || a.nama || a.email || '—')}
              </span>`
            ).join('')}
          </div>
        </div>` : ''}
      </div>`

    // Tombol aksi — tambah Re-Sync jika status failed atau belum ada google_event_id & sync aktif
    const canResync = !!+d.sync_google && (d.google_sync_status === 'failed' || d.google_sync_status === 'pending' || (!d.google_event_id && d.google_sync_status !== 'not_synced'))
    const actions = [
      { label: 'Tutup',    cls: 'btn-secondary', action: closeModal },
      { label: '✏ Edit',  cls: 'btn-primary',   action: () => { closeModal(); schShowForm(id) } },
    ]
    if (canResync) {
      actions.push({ label: '🔄 Re-Sync', cls: 'btn-warning', action: async () => {
        closeModal()
        showToast('Menyinkronkan agenda ke Google Calendar…', 'info')
        try {
          const r = await apiPost(`schedule/${id}/resync`, {})
          showToast(r?.message || 'Re-sync berhasil!')
          schRefreshCalendar?.()
        } catch(e) { showToast('Re-sync gagal: ' + e.message, 'error') }
      }})
    }
    actions.push({ label: '🗑 Hapus', cls: 'btn-danger', action: () => { closeModal(); schHapus(id, d.judul) } })

    openModal(`Detail — ${esc(d.kode)}`, body, actions)
  } catch(e) { openModal('Error', `<div class="alert alert-danger">${e.message}</div>`) }
}

// ──────────────────────────────────────────────────────────────
// HAPUS AGENDA
// ──────────────────────────────────────────────────────────────
function schHapus(id, judul) {
  openModal('Hapus Agenda', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">🗑️</div>
      <div style="font-size:14px;font-weight:700;margin-bottom:6px">Hapus Agenda?</div>
      <div style="font-size:13px;color:#6b7280">
        <strong>${esc(judul)}</strong><br>
        <span style="color:#ef4444">Event Google Calendar juga akan dihapus.</span>
      </div>
    </div>`, [
    { label: 'Batal',  cls: 'btn-secondary', action: closeModal },
    { label: '🗑 Hapus', cls: 'btn-danger',  action: async () => {
      try {
        await apiDelete(`schedule/${id}`)
        closeModal()
        showToast('Agenda berhasil dihapus.')
        schRefreshCalendar()
        _schLoadWidgets()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ──────────────────────────────────────────────────────────────
// HALAMAN PENGATURAN GOOGLE CALENDAR
// ──────────────────────────────────────────────────────────────
async function renderScheduleGoogle() {
  const content = document.getElementById('mainContent')
  content.innerHTML = `
    <div class="page-header" style="margin-bottom:20px">
      <div style="font-size:18px;font-weight:700">Pengaturan Google Calendar</div>
      <div style="font-size:12px;color:#6b7280;margin-top:3px">Integrasi MKA System ↔ Google Calendar API</div>
    </div>

    <!-- ════════════════════════════════════════════════════════
         PANDUAN TATA CARA PENGATURAN
         ════════════════════════════════════════════════════════ -->
    <div class="card" style="margin-bottom:16px;border:1px solid #bfdbfe;background:#f0f7ff">
      <div style="padding:14px 20px;display:flex;justify-content:space-between;align-items:center;cursor:pointer;user-select:none"
           onclick="schToggleGuide(this)">
        <div style="display:flex;align-items:center;gap:10px">
          <span style="font-size:18px">📖</span>
          <div>
            <div style="font-weight:700;font-size:14px;color:#1e40af">Panduan Tata Cara Pengaturan Google Calendar</div>
            <div style="font-size:11px;color:#3b82f6;margin-top:2px">Klik untuk membuka / menutup panduan</div>
          </div>
        </div>
        <svg id="schGuideChevron" viewBox="0 0 24 24" fill="#3b82f6" width="18" height="18"
             style="transition:transform .25s;flex-shrink:0">
          <path d="M7 10l5 5 5-5z"/>
        </svg>
      </div>

      <div id="schGuideBody" style="display:none;padding:0 20px 20px">

        <!-- ── Garis waktu / alur ── -->
        <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:20px;padding:12px 14px;
                    background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;font-size:12px;color:#1e40af">
          <span style="font-size:16px;flex-shrink:0">ℹ️</span>
          <div>
            Ikuti <strong>8 langkah</strong> berikut secara berurutan. Seluruh proses membutuhkan akses ke
            <strong>Google Cloud Console</strong> dan akun Google yang Calendar-nya akan digunakan.
            Konfigurasi cukup dilakukan <strong>satu kali</strong> — sistem kemudian menyimpan token dan
            memperbarui akses secara otomatis.
          </div>
        </div>

        <!-- ══ LANGKAH 1 ══════════════════════════════════════ -->
        <div style="margin-bottom:20px;border-left:3px solid #bfdbfe;padding-left:14px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <span style="background:#1d4ed8;color:#fff;border-radius:50%;width:26px;height:26px;
                         display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">1</span>
            <div style="font-weight:700;font-size:13px;color:#1f2328">Buat Project di Google Cloud Console</div>
            <a href="https://console.cloud.google.com" target="_blank"
               style="margin-left:auto;font-size:11px;background:#1d4ed8;color:#fff;padding:3px 10px;border-radius:4px;text-decoration:none;white-space:nowrap;flex-shrink:0">
              Buka Google Cloud ↗
            </a>
          </div>
          <div style="padding-left:36px">
            <ol style="margin:0;padding-left:18px;font-size:13px;color:#374151;line-height:1.9">
              <li>Login ke <a href="https://console.cloud.google.com" target="_blank" style="color:#1d4ed8">console.cloud.google.com</a> dengan akun Google yang Calendar-nya akan dipakai.</li>
              <li>Klik dropdown project di kiri atas → <strong>New Project</strong>.</li>
              <li>Beri nama project, misal: <code style="background:#f3f4f6;border-radius:3px;padding:1px 5px">MKA-System-Calendar</code> → klik <strong>Create</strong>.</li>
              <li>Pastikan project baru sudah aktif (terlihat di dropdown kiri atas).</li>
            </ol>
          </div>
        </div>

        <!-- ══ LANGKAH 2 ══════════════════════════════════════ -->
        <div style="margin-bottom:20px;border-left:3px solid #bfdbfe;padding-left:14px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <span style="background:#1d4ed8;color:#fff;border-radius:50%;width:26px;height:26px;
                         display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">2</span>
            <div style="font-weight:700;font-size:13px;color:#1f2328">Aktifkan Google Calendar API</div>
            <a href="https://console.cloud.google.com/apis/library/calendar-json.googleapis.com" target="_blank"
               style="margin-left:auto;font-size:11px;background:#1d4ed8;color:#fff;padding:3px 10px;border-radius:4px;text-decoration:none;white-space:nowrap;flex-shrink:0">
              Buka API Library ↗
            </a>
          </div>
          <div style="padding-left:36px">
            <ol style="margin:0;padding-left:18px;font-size:13px;color:#374151;line-height:1.9">
              <li>Di Google Cloud Console, pastikan project yang benar sudah aktif.</li>
              <li>Buka menu kiri: <strong>APIs &amp; Services</strong> → <strong>Library</strong>.</li>
              <li>Cari <strong>"Google Calendar API"</strong> → klik hasilnya.</li>
              <li>Klik tombol <strong>Enable</strong> (jika sudah enable, abaikan).</li>
            </ol>
          </div>
        </div>

        <!-- ══ LANGKAH 3 ══════════════════════════════════════ -->
        <div style="margin-bottom:20px;border-left:3px solid #bfdbfe;padding-left:14px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <span style="background:#1d4ed8;color:#fff;border-radius:50%;width:26px;height:26px;
                         display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">3</span>
            <div style="font-weight:700;font-size:13px;color:#1f2328">Konfigurasi OAuth Consent Screen</div>
            <a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank"
               style="margin-left:auto;font-size:11px;background:#1d4ed8;color:#fff;padding:3px 10px;border-radius:4px;text-decoration:none;white-space:nowrap;flex-shrink:0">
              Buka OAuth Consent ↗
            </a>
          </div>
          <div style="padding-left:36px">
            <ol style="margin:0;padding-left:18px;font-size:13px;color:#374151;line-height:1.9">
              <li>Di menu kiri: <strong>APIs &amp; Services</strong> → <strong>OAuth consent screen</strong>.</li>
              <li>Pilih <strong>User Type</strong>:
                <ul style="margin-top:4px;padding-left:18px;line-height:1.8;font-size:12px">
                  <li><strong>Internal</strong> — hanya akun dalam organisasi Google Workspace (lebih aman, tidak perlu verifikasi)</li>
                  <li><strong>External</strong> — akun Google biasa (gmail.com); perlu mendaftarkan Test Users selama masa pengujian</li>
                </ul>
              </li>
              <li>Isi <strong>App name</strong> (misal: <em>MKA System</em>), <strong>User support email</strong>, dan <strong>Developer contact</strong>.</li>
              <li>Klik <strong>Save and Continue</strong> hingga selesai (skip bagian Scopes jika ditanya).</li>
              <li>Jika memilih <strong>External</strong>, tambahkan akun Google pemilik Calendar sebagai <strong>Test users</strong> agar dapat diotorisasi.</li>
            </ol>
          </div>
        </div>

        <!-- ══ LANGKAH 4 ══════════════════════════════════════ -->
        <div style="margin-bottom:20px;border-left:3px solid #bfdbfe;padding-left:14px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <span style="background:#1d4ed8;color:#fff;border-radius:50%;width:26px;height:26px;
                         display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">4</span>
            <div style="font-weight:700;font-size:13px;color:#1f2328">Buat OAuth Client ID</div>
            <a href="https://console.cloud.google.com/apis/credentials" target="_blank"
               style="margin-left:auto;font-size:11px;background:#1d4ed8;color:#fff;padding:3px 10px;border-radius:4px;text-decoration:none;white-space:nowrap;flex-shrink:0">
              Buka Credentials ↗
            </a>
          </div>
          <div style="padding-left:36px">
            <ol style="margin:0;padding-left:18px;font-size:13px;color:#374151;line-height:1.9">
              <li>Di menu kiri: <strong>APIs &amp; Services</strong> → <strong>Credentials</strong>.</li>
              <li>Klik <strong>+ Create Credentials</strong> → pilih <strong>OAuth client ID</strong>.</li>
              <li>Pilih <strong>Application type: Web application</strong>.</li>
              <li>Beri nama bebas, misal: <code style="background:#f3f4f6;border-radius:3px;padding:1px 5px">MKA Web Client</code>.</li>
              <li>Di bagian <strong>Authorized redirect URIs</strong>, tambahkan URL berikut:
                <div style="margin-top:8px;background:#1e293b;border-radius:6px;padding:10px 14px;display:flex;align-items:center;gap:10px">
                  <code style="font-family:monospace;font-size:12px;color:#86efac;word-break:break-all;flex:1">
                    ${window.location.origin}/google-oauth-callback
                  </code>
                  <button onclick="navigator.clipboard.writeText('${window.location.origin}/google-oauth-callback').then(()=>showToast('URL disalin!'))"
                          style="flex-shrink:0;background:#3b82f6;color:#fff;border:none;border-radius:5px;padding:4px 10px;font-size:11px;cursor:pointer">
                    📋 Salin
                  </button>
                </div>
                <div style="font-size:11px;color:#dc2626;margin-top:4px">⚠️ URL harus sama persis, termasuk http/https dan path — tidak boleh ada spasi/karakter tambahan.</div>
              </li>
              <li>Klik <strong>Create</strong> → popup muncul dengan <strong>Client ID</strong> dan <strong>Client Secret</strong>. <span style="color:#dc2626;font-weight:600">Salin keduanya segera — Secret hanya tampil sekali.</span></li>
            </ol>
          </div>
        </div>

        <!-- ══ LANGKAH 5 ══════════════════════════════════════ -->
        <div style="margin-bottom:20px;border-left:3px solid #bfdbfe;padding-left:14px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <span style="background:#1d4ed8;color:#fff;border-radius:50%;width:26px;height:26px;
                         display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">5</span>
            <div style="font-weight:700;font-size:13px;color:#1f2328">Masukkan Credential ke Form Konfigurasi</div>
          </div>
          <div style="padding-left:36px">
            <ol style="margin:0;padding-left:18px;font-size:13px;color:#374151;line-height:1.9">
              <li>Scroll ke bawah ke panel <strong>"🔑 Konfigurasi OAuth2 Credentials"</strong> di halaman ini.</li>
              <li>Isi field-field berikut:
                <table style="margin-top:8px;border-collapse:collapse;width:100%;font-size:12px">
                  <thead>
                    <tr style="background:#f3f4f6">
                      <th style="border:1px solid #e5e7eb;padding:6px 10px;text-align:left">Field</th>
                      <th style="border:1px solid #e5e7eb;padding:6px 10px;text-align:left">Sumber / Nilai</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr><td style="border:1px solid #e5e7eb;padding:6px 10px"><strong>Google Client ID</strong></td><td style="border:1px solid #e5e7eb;padding:6px 10px">Dari popup OAuth Client ID Google (format: <code>xxx.apps.googleusercontent.com</code>)</td></tr>
                    <tr style="background:#fafafa"><td style="border:1px solid #e5e7eb;padding:6px 10px"><strong>Google Client Secret</strong></td><td style="border:1px solid #e5e7eb;padding:6px 10px">Dari popup OAuth Client ID Google</td></tr>
                    <tr><td style="border:1px solid #e5e7eb;padding:6px 10px"><strong>Google Project ID</strong></td><td style="border:1px solid #e5e7eb;padding:6px 10px">Terlihat di dropdown project Google Cloud Console</td></tr>
                    <tr style="background:#fafafa"><td style="border:1px solid #e5e7eb;padding:6px 10px"><strong>Calendar ID</strong></td><td style="border:1px solid #e5e7eb;padding:6px 10px">Gunakan <code>primary</code> untuk kalender utama, atau ID kalender khusus</td></tr>
                    <tr><td style="border:1px solid #e5e7eb;padding:6px 10px"><strong>Redirect URL</strong></td><td style="border:1px solid #e5e7eb;padding:6px 10px">Sudah terisi otomatis — jangan diubah kecuali domain aplikasi berubah</td></tr>
                  </tbody>
                </table>
              </li>
              <li>Klik <strong>💾 Simpan Konfigurasi</strong> setelah semua field terisi.</li>
            </ol>
            <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:6px;padding:8px 12px;font-size:12px;color:#78350f;margin-top:10px">
              🔒 <strong>Keamanan:</strong> Credential disimpan di database server, terenkripsi. Tidak pernah ditampilkan di frontend secara penuh. Nilai Client Secret hanya tampil sebagai placeholder di form setelah disimpan.
            </div>
          </div>
        </div>

        <!-- ══ LANGKAH 6 ══════════════════════════════════════ -->
        <div style="margin-bottom:20px;border-left:3px solid #bfdbfe;padding-left:14px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <span style="background:#1d4ed8;color:#fff;border-radius:50%;width:26px;height:26px;
                         display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">6</span>
            <div style="font-weight:700;font-size:13px;color:#1f2328">Hubungkan Google Calendar (OAuth2)</div>
          </div>
          <div style="padding-left:36px">
            <ol style="margin:0;padding-left:18px;font-size:13px;color:#374151;line-height:1.9">
              <li>Setelah konfigurasi disimpan, klik tombol <strong>🔗 Hubungkan Google Calendar</strong> di panel <em>Status Koneksi</em> di atas.</li>
              <li>Browser membuka <strong>tab baru</strong> ke halaman persetujuan Google.</li>
              <li>Pilih akun Google yang Calendar-nya ingin digunakan → klik <strong>Allow / Izinkan</strong>.</li>
              <li>Tab callback akan memproses otorisasi otomatis → menampilkan status berhasil.</li>
              <li>Kembali ke tab MKA — status akan berubah menjadi <span style="background:#dcfce7;color:#166534;border-radius:4px;padding:1px 7px;font-size:11px;font-weight:600">✅ Terhubung</span>.</li>
            </ol>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:8px 12px;font-size:12px;color:#166534;margin-top:10px">
              ✅ Setelah berhasil, sistem menyimpan <strong>refresh token</strong> untuk memperbarui akses secara otomatis tanpa perlu login Google ulang.
            </div>
          </div>
        </div>

        <!-- ══ LANGKAH 7 ══════════════════════════════════════ -->
        <div style="margin-bottom:20px;border-left:3px solid #bfdbfe;padding-left:14px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <span style="background:#1d4ed8;color:#fff;border-radius:50%;width:26px;height:26px;
                         display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">7</span>
            <div style="font-weight:700;font-size:13px;color:#1f2328">Tes Koneksi</div>
          </div>
          <div style="padding-left:36px">
            <ol style="margin:0;padding-left:18px;font-size:13px;color:#374151;line-height:1.9">
              <li>Klik tombol <strong>🔌 Test Koneksi</strong> di panel Status Koneksi.</li>
              <li>Sistem akan mencoba mengakses Google Calendar API dan memverifikasi:
                <ul style="margin-top:4px;padding-left:18px;line-height:1.8;font-size:12px">
                  <li>Credential valid ✓</li>
                  <li>Token aktif ✓</li>
                  <li>Calendar ID dapat diakses ✓</li>
                  <li>Permission mencukupi ✓</li>
                </ul>
              </li>
              <li>Hasil akan ditampilkan sebagai notifikasi:
                <ul style="margin-top:4px;padding-left:18px;line-height:1.8;font-size:12px">
                  <li><span style="color:#166534">✅ Berhasil</span> — menampilkan nama Calendar yang terhubung</li>
                  <li><span style="color:#dc2626">❌ Gagal</span> — menampilkan penyebab (credential, permission, atau koneksi)</li>
                </ul>
              </li>
            </ol>
          </div>
        </div>

        <!-- ══ LANGKAH 8 ══════════════════════════════════════ -->
        <div style="margin-bottom:20px;border-left:3px solid #bfdbfe;padding-left:14px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <span style="background:#1d4ed8;color:#fff;border-radius:50%;width:26px;height:26px;
                         display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0">8</span>
            <div style="font-weight:700;font-size:13px;color:#1f2328">Tes Sinkronisasi Agenda</div>
          </div>
          <div style="padding-left:36px">
            <ol style="margin:0;padding-left:18px;font-size:13px;color:#374151;line-height:1.9">
              <li>Buka halaman <strong>Schedule</strong> dari menu sidebar.</li>
              <li>Buat agenda baru dengan opsi <strong>"Sinkronkan ke Google Calendar"</strong> diaktifkan.</li>
              <li>Simpan agenda — sistem secara otomatis mengirimkan event ke Google Calendar.</li>
              <li>Cek Google Calendar Anda — event seharusnya sudah muncul.</li>
              <li>Untuk agenda yang belum/gagal sinkronisasi, gunakan tombol <strong>🔄 Re-Sync Semua</strong> di bawah halaman ini, atau klik <strong>🔄 Re-Sync</strong> di detail agenda.</li>
            </ol>
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:8px 12px;font-size:12px;color:#1e40af;margin-top:10px">
              💡 <strong>Catatan:</strong> Database MKA adalah source of truth. Penghapusan agenda di MKA akan menghapus event di Google Calendar. Sinkronisasi satu arah: MKA → Google Calendar.
            </div>
          </div>
        </div>

        <!-- ══ MENCARI CALENDAR ID ═══════════════════════════ -->
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:14px 16px;margin-bottom:16px">
          <div style="font-weight:700;font-size:12px;color:#c2410c;margin-bottom:8px">📌 Cara Mendapatkan Calendar ID (selain "primary")</div>
          <ol style="margin:0;padding-left:18px;font-size:12px;color:#7c2d12;line-height:1.9">
            <li>Buka <a href="https://calendar.google.com" target="_blank" style="color:#c2410c">calendar.google.com</a>.</li>
            <li>Di sidebar kiri, arahkan kursor ke kalender yang ingin digunakan → klik ikon <strong>⋮</strong> (tiga titik).</li>
            <li>Pilih <strong>Settings and sharing</strong>.</li>
            <li>Scroll ke bawah ke bagian <strong>"Integrate calendar"</strong>.</li>
            <li>Salin nilai <strong>Calendar ID</strong> (biasanya berformat: <code style="background:#fff;border-radius:3px;padding:1px 5px">xxx@group.calendar.google.com</code>).</li>
          </ol>
        </div>

        <!-- ══ TROUBLESHOOTING ════════════════════════════════ -->
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:14px 16px">
          <div style="font-weight:700;font-size:12px;color:#991b1b;margin-bottom:8px">⚠️ Troubleshooting Umum</div>
          <table style="width:100%;border-collapse:collapse;font-size:12px">
            <thead>
              <tr style="background:#fee2e2">
                <th style="border:1px solid #fca5a5;padding:6px 10px;text-align:left;color:#991b1b">Masalah</th>
                <th style="border:1px solid #fca5a5;padding:6px 10px;text-align:left;color:#991b1b">Solusi</th>
              </tr>
            </thead>
            <tbody style="color:#7f1d1d">
              <tr>
                <td style="border:1px solid #fca5a5;padding:6px 10px"><code>redirect_uri_mismatch</code></td>
                <td style="border:1px solid #fca5a5;padding:6px 10px">Pastikan Redirect URL di form sama persis dengan yang didaftarkan di Google Cloud Console, termasuk <code>http/https</code> dan path.</td>
              </tr>
              <tr style="background:#fff5f5">
                <td style="border:1px solid #fca5a5;padding:6px 10px"><code>access_denied</code></td>
                <td style="border:1px solid #fca5a5;padding:6px 10px">Tambahkan akun Google Anda sebagai <strong>Test User</strong> di OAuth Consent Screen (untuk mode External).</td>
              </tr>
              <tr>
                <td style="border:1px solid #fca5a5;padding:6px 10px">Token expired / dicabut</td>
                <td style="border:1px solid #fca5a5;padding:6px 10px">Klik <strong>Putuskan Koneksi</strong> → hubungkan ulang dari awal. Token lama sudah tidak valid.</td>
              </tr>
              <tr style="background:#fff5f5">
                <td style="border:1px solid #fca5a5;padding:6px 10px">Sync gagal (<code>failed</code>)</td>
                <td style="border:1px solid #fca5a5;padding:6px 10px">Klik detail agenda → tombol <strong>🔄 Re-Sync</strong>, atau gunakan <strong>🔄 Re-Sync Semua</strong> di bawah halaman ini.</td>
              </tr>
              <tr>
                <td style="border:1px solid #fca5a5;padding:6px 10px">Google Calendar API not enabled</td>
                <td style="border:1px solid #fca5a5;padding:6px 10px">Kembali ke Google Cloud Console → APIs &amp; Services → Library → aktifkan <strong>Google Calendar API</strong>.</td>
              </tr>
              <tr style="background:#fff5f5">
                <td style="border:1px solid #fca5a5;padding:6px 10px">Agenda dibuat tapi tidak muncul di Google Calendar</td>
                <td style="border:1px solid #fca5a5;padding:6px 10px">Pastikan agenda dibuat dengan opsi <em>Sinkronkan ke Google Calendar</em> aktif. Cek status di detail agenda.</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div><!-- /schGuideBody -->
    </div><!-- /card panduan -->

    <!-- ════════════════════════════════════════════════════════
         STATUS KONEKSI + FORM OAUTH2 (satu kolom penuh)
         ════════════════════════════════════════════════════════ -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

      <!-- Panel Koneksi -->
      <div class="card" style="padding:20px">
        <div style="font-weight:700;font-size:14px;margin-bottom:14px">🔗 Status Koneksi</div>
        <div id="schGoogleStatus"><div class="spinner-sm"></div></div>
      </div>

      <!-- Panel Konfigurasi -->
      <div class="card" style="padding:20px">
        <div style="font-weight:700;font-size:14px;margin-bottom:14px">⚙️ Reminder Default</div>
        <div id="schReminderForm"><div class="spinner-sm"></div></div>
      </div>
    </div>

    <!-- Form Konfigurasi OAuth -->
    <div class="card" style="padding:20px;margin-bottom:16px">
      <div style="font-weight:700;font-size:14px;margin-bottom:14px">🔑 Konfigurasi OAuth2 Credentials</div>
      <div id="schOauthForm"><div class="spinner-sm"></div></div>
    </div>

    <!-- Import dari Google Calendar -->
    <div class="card" style="padding:20px;margin-bottom:16px;border:1px solid #bbf7d0;background:#f0fdf4">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <div>
          <div style="font-weight:700;font-size:14px;color:#166534">⬇ Import dari Google Calendar</div>
          <div style="font-size:12px;color:#16a34a;margin-top:2px">
            Tarik jadwal yang sudah ada di Google Calendar ke dalam MKA System
          </div>
        </div>
        <button class="btn btn-sm" style="background:#166534;color:#fff;border:none"
                onclick="schShowPullModal()">Import Sekarang</button>
      </div>
      <div style="font-size:12px;color:#15803d;background:#dcfce7;border-radius:6px;padding:8px 12px">
        💡 <strong>Catatan:</strong> Jadwal yang sudah ada di MKA (berdasarkan Google Event ID) akan dilewati otomatis
        — tidak ada duplikat. Setelah diimport, kategori dapat diubah dari halaman detail agenda.
      </div>
    </div>

    <!-- Sync Logs -->
    <div class="card" style="padding:20px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div style="font-weight:700;font-size:14px">📋 Log Sinkronisasi</div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-sm btn-warning" onclick="schResyncAll()">🔄 Re-Sync Semua</button>
          <button class="btn btn-sm btn-secondary" onclick="schLoadSyncLogs()">↻ Refresh</button>
        </div>
      </div>
      <div id="schSyncLogs"><div class="spinner-sm"></div></div>
    </div>`

  schLoadGoogleStatus()
  schLoadSyncLogs()

  // Auto-refresh status jika tab callback Google berhasil
  window.addEventListener('storage', function _gcalConnectListener(e) {
    if (e.key === 'mka_gcal_connected') {
      window.removeEventListener('storage', _gcalConnectListener)
      showToast('Google Calendar berhasil terhubung! Memuat ulang status…', 'success')
      setTimeout(schLoadGoogleStatus, 800)
    }
  })
}

// Toggle collapse/expand panduan
function schToggleGuide(headerEl) {
  const body    = document.getElementById('schGuideBody')
  const chevron = document.getElementById('schGuideChevron')
  if (!body) return
  const isOpen = body.style.display !== 'none'
  body.style.display    = isOpen ? 'none' : 'block'
  chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)'
}

// ══════════════════════════════════════════════════════════════
// IMPORT (PULL) DARI GOOGLE CALENDAR → MKA
// ══════════════════════════════════════════════════════════════

async function schShowPullModal() {
  // Default rentang: bulan ini s/d 6 bulan ke depan
  const today     = new Date()
  const firstDay  = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0,10)
  const sixMonths = new Date(today.getFullYear(), today.getMonth() + 6, 0).toISOString().slice(0,10)

  // Skeleton body sementara — kalender dimuat secara async setelah modal terbuka
  const body = `
    <div style="font-size:13px;color:#374151;margin-bottom:14px">
      Tarik jadwal dari Google Calendar pada rentang tanggal yang dipilih ke dalam MKA System.<br>
      <span style="color:#6b7280;font-size:12px">Jadwal yang sudah ada (berdasarkan Google Event ID) tidak akan digandakan.</span>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
      <div class="form-group">
        <label class="form-label">Dari Tanggal <span class="required">*</span></label>
        <input type="date" class="form-control" id="pullDari" value="${firstDay}">
      </div>
      <div class="form-group">
        <label class="form-label">Sampai Tanggal <span class="required">*</span></label>
        <input type="date" class="form-control" id="pullSampai" value="${sixMonths}">
      </div>
    </div>

    <div style="margin-bottom:10px">
      <label class="form-label" style="font-weight:600;margin-bottom:6px;display:block">
        Kalender yang Diimport
        <span id="pullCalCheckAll" style="font-weight:400;font-size:11px;color:#3b82d4;cursor:pointer;margin-left:8px">(Pilih Semua)</span>
      </label>
      <div id="pullCalList" style="border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;background:#f9fafb;min-height:48px">
        <span style="color:#9ca3af;font-size:12px">⏳ Memuat daftar kalender…</span>
      </div>
    </div>

    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 13px;font-size:12px;color:#92400e">
      ⚠️ Semua event yang diimport akan masuk kategori <strong>"Lainnya"</strong> secara default.
      Anda bisa mengubah kategorinya dari halaman detail agenda.
    </div>`

  openModal('⬇ Import dari Google Calendar', body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: '⬇ Mulai Import', cls: 'btn-success', action: async () => {
      const dari   = document.getElementById('pullDari')?.value
      const sampai = document.getElementById('pullSampai')?.value
      if (!dari || !sampai) { showToast('Pilih rentang tanggal terlebih dahulu.', 'error'); return }
      if (dari > sampai) { showToast('Tanggal "dari" tidak boleh lebih besar dari "sampai".', 'error'); return }

      // Kumpulkan calendar_ids yang dicentang
      const checks = document.querySelectorAll('#pullCalList input[type=checkbox]:checked')
      const calIds = Array.from(checks).map(c => c.value)
      // Jika tidak ada kalender dimuat (API gagal) atau semua dicentang → kirim kosong (server ambil semua)
      const allChecks = document.querySelectorAll('#pullCalList input[type=checkbox]')
      const calParam  = (calIds.length === 0 || calIds.length === allChecks.length) ? [] : calIds

      closeModal()
      await schDoPull(dari, sampai, calParam)
    }},
  ])

  // Muat daftar kalender secara async setelah modal terbuka
  _schLoadPullCalendars()
}

async function _schLoadPullCalendars() {
  const listEl    = document.getElementById('pullCalList')
  const checkAll  = document.getElementById('pullCalCheckAll')
  if (!listEl) return

  try {
    const res = await apiGet('schedule/google/calendars')
    const cals = res.data?.items ?? []

    if (!cals.length) {
      listEl.innerHTML = '<span style="color:#9ca3af;font-size:12px">Tidak ada kalender ditemukan.</span>'
      return
    }

    listEl.innerHTML = cals.map(c => {
      const warna = c.warna || '#3b82d4'
      const badge = c.primary
        ? `<span style="background:#dcfce7;color:#15803d;font-size:10px;padding:1px 5px;border-radius:4px;margin-left:4px">Utama</span>`
        : ''
      return `
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;cursor:pointer;font-size:13px">
          <input type="checkbox" value="${esc(c.id)}" checked
                 style="width:15px;height:15px;accent-color:${esc(warna)};cursor:pointer">
          <span style="width:11px;height:11px;border-radius:50%;background:${esc(warna)};flex-shrink:0"></span>
          <span>${esc(c.summary)}${badge}</span>
        </label>`
    }).join('')

    // Pilih Semua / Batal Semua toggle
    if (checkAll) {
      checkAll.onclick = () => {
        const all   = listEl.querySelectorAll('input[type=checkbox]')
        const anyUnchecked = Array.from(all).some(c => !c.checked)
        all.forEach(c => c.checked = anyUnchecked)
        checkAll.textContent = anyUnchecked ? '(Batal Semua)' : '(Pilih Semua)'
      }
    }

  } catch (e) {
    listEl.innerHTML = `<span style="color:#dc2626;font-size:12px">⚠️ Gagal memuat kalender: ${esc(e.message)}. Semua kalender akan diimport.</span>`
  }
}

async function schDoPull(dari, sampai, calIds = []) {
  showToast('Mengambil data dari Google Calendar…', 'info')
  try {
    // Bangun query string — kalau calIds ada, tambahkan calendar_ids[]
    let qs = `dari=${dari}&sampai=${sampai}`
    if (calIds.length) qs += '&' + calIds.map(id => `calendar_ids[]=${encodeURIComponent(id)}`).join('&')
    const res = await apiPost(`schedule/google/pull?${qs}`, {})
    const d   = res.data || {}

    const berhasil = d.imported ?? 0
    const dilewati = d.skipped  ?? 0
    const total    = d.total    ?? 0
    const errors   = d.errors   || []

    // Refresh kalender jika ada yang diimport
    if (berhasil > 0) {
      schRefreshCalendar?.()
      _schLoadWidgets?.()
    }

    // Tampilkan ringkasan hasil
    const errorHtml = errors.length
      ? `<div style="margin-top:12px;font-size:12px;color:#dc2626">
           <div style="font-weight:600;margin-bottom:4px">⚠️ Error (${errors.length}):</div>
           <ul style="margin:0;padding-left:16px">${errors.map(e => `<li>${esc(e)}</li>`).join('')}</ul>
         </div>`
      : ''

    openModal('Hasil Import Google Calendar', `
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px">
        <div style="text-align:center;background:#f0fdf4;border-radius:8px;padding:14px">
          <div style="font-size:28px;font-weight:700;color:#16a34a">${berhasil}</div>
          <div style="font-size:11px;color:#15803d;margin-top:2px">Berhasil Diimport</div>
        </div>
        <div style="text-align:center;background:#fff7ed;border-radius:8px;padding:14px">
          <div style="font-size:28px;font-weight:700;color:#d97706">${dilewati}</div>
          <div style="font-size:11px;color:#b45309;margin-top:2px">Dilewati / Duplikat</div>
        </div>
        <div style="text-align:center;background:#f3f4f6;border-radius:8px;padding:14px">
          <div style="font-size:28px;font-weight:700;color:#374151">${total}</div>
          <div style="font-size:11px;color:#6b7280;margin-top:2px">Total dari Google</div>
        </div>
      </div>
      ${berhasil > 0
        ? `<div style="background:#dcfce7;border-radius:6px;padding:8px 12px;font-size:12px;color:#166534">
             ✅ ${berhasil} agenda berhasil diimport dan sudah tampil di kalender.
           </div>`
        : `<div style="background:#f3f4f6;border-radius:6px;padding:8px 12px;font-size:12px;color:#6b7280">
             Tidak ada agenda baru yang diimport pada rentang tanggal tersebut.
           </div>`}
      ${errorHtml}`,
      [{ label: 'Tutup', cls: 'btn-secondary', action: closeModal }]
    )

    // Refresh sync logs jika di halaman pengaturan
    const syncLogsEl = document.getElementById('schSyncLogs')
    if (syncLogsEl) schLoadSyncLogs()

  } catch(e) {
    showToast('Import gagal: ' + e.message, 'error')
  }
}

// ── Status Koneksi & Form OAuth2 ────────────────────────────
async function schLoadGoogleStatus() {
  const el = document.getElementById('schGoogleStatus')
  if (!el) return
  try {
    const res = await apiGet('schedule/google/settings')
    const d   = res.data || {}
    const connected = !!+d.is_connected   // cast "0"/"1" string → boolean

    // ── Panel kiri: Status + Reminder ───────────────────────
    el.innerHTML = `
      <div style="display:flex;flex-direction:column;gap:14px">

        <!-- Status card -->
        <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:10px;
                    background:${connected ? '#f0fdf4' : '#fef2f2'};
                    border:1px solid ${connected ? '#bbf7d0' : '#fecaca'}">
          <span style="font-size:26px;flex-shrink:0">${connected ? '✅' : '❌'}</span>
          <div style="flex:1;min-width:0">
            <div style="font-weight:700;font-size:13px;color:${connected ? '#166534' : '#991b1b'}">
              ${connected ? 'Google Calendar Terhubung' : 'Belum Terhubung ke Google Calendar'}
            </div>
            <div style="font-size:11px;color:${connected ? '#15803d' : '#dc2626'};margin-top:2px;
                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              ${connected
                ? `${esc(d.connected_email||'—')} &nbsp;·&nbsp; Calendar: <code style="font-size:10px">${esc(d.calendar_id||'primary')}</code>`
                : 'Lengkapi konfigurasi OAuth2 di bawah, lalu klik Hubungkan.'}
            </div>
          </div>
        </div>

        <!-- Tombol aksi -->
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          ${connected ? `
            <button class="btn btn-sm btn-secondary" onclick="schTestConnection()">🔌 Test Koneksi</button>
            <button class="btn btn-sm" onclick="schDisconnect()"
                    style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5">Putuskan Koneksi</button>
          ` : `
            <button class="btn btn-sm btn-primary" onclick="schConnect()" style="padding:7px 20px">
              🔗 Hubungkan Google Calendar
            </button>`}
        </div>

        <!-- Reminder Default -->
        <div style="border-top:1px solid #e5e7eb;padding-top:14px">
          <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;
                      letter-spacing:.6px;margin-bottom:12px">Reminder Default</div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px">
            ${[
              { id:'gcRH7', label:'H-7', val: d.reminder_h7||10080 },
              { id:'gcRH3', label:'H-3', val: d.reminder_h3||4320  },
              { id:'gcRH1', label:'H-1', val: d.reminder_h1||1440  },
            ].map(r => {
              const menit = r.val
              const hari  = Math.round(menit / 60 / 24)
              return `
              <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;
                          padding:10px 8px;text-align:center">
                <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:6px">${r.label}</div>
                <div style="font-size:15px;font-weight:700;color:#1f2328;margin-bottom:2px">${hari}</div>
                <div style="font-size:10px;color:#9ca3af;margin-bottom:8px">hari sebelum</div>
                <input type="hidden" id="${r.id}" value="${menit}">
              </div>`
            }).join('')}
          </div>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;
                         background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;
                         padding:8px 12px;user-select:none">
            <input type="checkbox" id="gcRAktif" ${d.reminder_aktif ? 'checked' : ''}
                   style="flex-shrink:0;width:15px;height:15px">
            <span>Aktifkan reminder otomatis untuk semua agenda baru</span>
          </label>
        </div>

      </div>`

    // ── Panel kanan: Form OAuth2 ─────────────────────────────
    const oauthEl = document.getElementById('schOauthForm')
    if (oauthEl) {
      oauthEl.innerHTML = `
        <div style="display:flex;flex-direction:column;gap:14px">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">

            <div>
              <label class="form-label" style="font-size:12px;font-weight:600">
                Google Client ID <span class="required">*</span>
              </label>
              <input type="text" class="form-control" id="gcClientId"
                     value="${esc(d.client_id||'')}" placeholder="xxx.apps.googleusercontent.com"
                     style="font-size:12px;font-family:monospace">
            </div>

            <div>
              <label class="form-label" style="font-size:12px;font-weight:600">
                Google Client Secret <span class="required">*</span>
              </label>
              <div style="position:relative">
                <input type="password" class="form-control" id="gcClientSecret"
                       value="${esc(d.client_secret||'')}"
                       placeholder="${d.client_secret ? 'Isi untuk mengganti nilai lama' : 'Belum diisi'}"
                       style="font-size:12px;font-family:monospace;padding-right:40px">
                <button type="button" onclick="_gcToggleSecret(this)"
                        style="position:absolute;right:9px;top:50%;transform:translateY(-50%);
                               background:none;border:none;cursor:pointer;font-size:15px;
                               color:#6b7280;padding:0;line-height:1" title="Tampilkan/Sembunyikan">👁</button>
              </div>
            </div>

            <div>
              <label class="form-label" style="font-size:12px;font-weight:600">Google Project ID</label>
              <input type="text" class="form-control" id="gcProjectId"
                     value="${esc(d.project_id||'')}" placeholder="mka-system-calendar-123"
                     style="font-size:12px;font-family:monospace">
            </div>

            <div>
              <label class="form-label" style="font-size:12px;font-weight:600">Calendar ID</label>
              <input type="text" class="form-control" id="gcCalendarId"
                     value="${esc(d.calendar_id||'primary')}" placeholder="primary"
                     style="font-size:12px;font-family:monospace">
              <div style="font-size:10.5px;color:#6b7280;margin-top:3px">
                Gunakan <code>primary</code> untuk kalender utama akun yang terhubung
              </div>
            </div>
          </div>

          <div>
            <label class="form-label" style="font-size:12px;font-weight:600">
              Redirect URL (OAuth Callback)
            </label>
            <div style="display:flex;gap:8px">
              <input type="text" class="form-control" id="gcRedirectUrl"
                     value="${esc(d.redirect_url || (window.location.origin + '/google-oauth-callback'))}"
                     style="font-size:12px;font-family:monospace;flex:1">
              <button type="button"
                      onclick="navigator.clipboard.writeText(document.getElementById('gcRedirectUrl').value).then(()=>showToast('URL disalin ke clipboard.'))"
                      class="btn btn-sm btn-secondary" style="white-space:nowrap;flex-shrink:0">
                📋 Salin
              </button>
            </div>
            <div style="font-size:10.5px;color:#6b7280;margin-top:3px">
              Daftarkan URL ini di <strong>Google Cloud Console → Credentials → Authorized redirect URIs</strong>
            </div>
          </div>

          <div style="display:flex;justify-content:flex-end;border-top:1px solid #e5e7eb;padding-top:12px">
            <button class="btn btn-primary" onclick="schSaveOauth()" style="padding:8px 22px;font-size:13px">
              💾 Simpan Konfigurasi
            </button>
          </div>

        </div>`
    }

  } catch(e) {
    el.innerHTML = `<div class="alert alert-danger">${e.message}</div>`
  }
}

// Helper: toggle password visibility pada field Client Secret
function _gcToggleSecret(btn) {
  const inp = btn.previousElementSibling
  if (!inp) return
  inp.type = inp.type === 'password' ? 'text' : 'password'
  btn.textContent = inp.type === 'password' ? '👁' : '🙈'
}

async function schSaveOauth() {
  try {
    const payload = {
      client_id:    document.getElementById('gcClientId')?.value.trim()     || null,
      client_secret:document.getElementById('gcClientSecret')?.value.trim() || null,
      project_id:   document.getElementById('gcProjectId')?.value.trim()    || null,
      calendar_id:  document.getElementById('gcCalendarId')?.value.trim()   || null,
      redirect_url: document.getElementById('gcRedirectUrl')?.value.trim()  || null,
      reminder_h7:  parseInt(document.getElementById('gcRH7')?.value)  || 10080,
      reminder_h3:  parseInt(document.getElementById('gcRH3')?.value)  || 4320,
      reminder_h1:  parseInt(document.getElementById('gcRH1')?.value)  || 1440,
      reminder_aktif: document.getElementById('gcRAktif')?.checked ? 1 : 0,
    }
    const res = await apiPut('schedule/google/settings', payload)
    showToast(res.message || 'Konfigurasi disimpan.')
    schLoadGoogleStatus()
  } catch(e) { showToast(e.message, 'error') }
}

async function schConnect() {
  try {
    showToast('Memproses URL otorisasi…', 'info')
    const res = await apiGet('schedule/google/auth-url')
    if (!res?.data?.auth_url) { showToast('Gagal mendapatkan URL.', 'error'); return }
    // Buka di tab baru
    window.open(res.data.auth_url, '_blank')
    showToast('Tab baru dibuka — otorisasi Google Calendar. Kembali ke sini setelah selesai.', 'info')
    // Polling status setiap 3 detik selama 2 menit
    let attempts = 0
    const poll = setInterval(async () => {
      attempts++
      if (attempts > 40) { clearInterval(poll); return }
      const chk = await apiGet('schedule/google/settings').catch(() => null)
      if (chk?.data?.is_connected) {
        clearInterval(poll)
        showToast('Google Calendar berhasil terhubung!')
        schLoadGoogleStatus()
      }
    }, 3000)
  } catch(e) { showToast(e.message, 'error') }
}

async function schDisconnect() {
  openModal('Putuskan Google Calendar', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">🔌</div>
      <div style="font-size:14px;font-weight:700;margin-bottom:6px">Putuskan Google Calendar?</div>
      <div style="font-size:13px;color:#6b7280">Token akses akan dihapus. Sinkronisasi tidak akan berjalan.</div>
    </div>`, [
    { label: 'Batal',    cls: 'btn-secondary', action: closeModal },
    { label: '🔌 Putuskan', cls: 'btn-danger', action: async () => {
      try {
        const res = await apiPost('schedule/google/disconnect', {})
        closeModal(); showToast(res.message); schLoadGoogleStatus()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

async function schTestConnection() {
  try {
    showToast('Testing koneksi…', 'info')
    const res = await apiPost('schedule/google/test', {})
    if (res?.status) {
      showToast(`✅ Koneksi OK! Calendar: "${res.data?.summary || res.data?.id}"`)
    } else {
      showToast(res?.message || 'Test gagal.', 'error')
    }
  } catch(e) { showToast(e.message, 'error') }
}

async function schResyncAll() {
  openModal('Re-Sync Semua Agenda', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">🔄</div>
      <div style="font-size:13px;color:#6b7280">
        Semua agenda yang belum/gagal sinkronisasi akan di-push ulang ke Google Calendar.
      </div>
    </div>`, [
    { label: 'Batal',   cls: 'btn-secondary', action: closeModal },
    { label: '🔄 Mulai Re-Sync', cls: 'btn-primary', action: async () => {
      closeModal()
      showToast('Re-sync dimulai…', 'info')
      try {
        const res = await apiPost('schedule/google/resync-all', {})
        showToast(res.message || 'Re-sync selesai.')
        schLoadSyncLogs()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

// ── Sync Logs ───────────────────────────────────────────────
async function schLoadSyncLogs() {
  const el = document.getElementById('schSyncLogs')
  if (!el) return
  el.innerHTML = '<div class="spinner-sm"></div>'
  try {
    const res  = await apiGet('schedule/google/sync-logs')
    const rows = res.data?.items || []
    if (!rows.length) {
      el.innerHTML = '<div style="color:#6b7280;font-size:13px;text-align:center;padding:16px">Belum ada log sinkronisasi.</div>'
      return
    }
    const statusBadge = { success:'status-selesai', failed:'status-terlambat', skipped:'status-draft' }
    el.innerHTML = `<div style="overflow-x:auto">
      <table class="table" style="font-size:12px">
        <thead><tr><th>Waktu</th><th>Aksi</th><th>Agenda</th><th>Status</th><th>Pesan</th></tr></thead>
        <tbody>${rows.map(r => `
          <tr>
            <td style="white-space:nowrap">${r.created_at?.slice(0,16) || '—'}</td>
            <td><span class="status-badge status-draft" style="font-size:10px">${r.aksi}</span></td>
            <td>${esc(r.schedule_kode || '—')}<br><span style="color:#6b7280">${esc(r.schedule_judul || '').slice(0,40)}</span></td>
            <td><span class="status-badge ${statusBadge[r.status]||'status-draft'}" style="font-size:10px">${r.status}</span></td>
            <td style="max-width:200px;word-break:break-word">${esc(r.pesan || '—')}</td>
          </tr>`).join('')}
        </tbody>
      </table>
    </div>`
  } catch(e) {
    el.innerHTML = `<div class="alert alert-danger">${e.message}</div>`
  }
}

// ──────────────────────────────────────────────────────────────
// Helper: label kategori
// ──────────────────────────────────────────────────────────────
function _schCatLabel(kode) {
  const map = {
    internal:'Internal', rapat:'Rapat', on_budget:'On Budget',
    off_budget:'Off Budget', telaah:'Telaah', libur:'Libur', lainnya:'Lainnya',
  }
  return map[kode] || kode
}

// ══════════════════════════════════════════════════════════════
// KATEGORI SCHEDULE — Halaman manajemen kategori (Admin)
// Halaman ini hanya bisa diakses oleh admin.
// ══════════════════════════════════════════════════════════════
async function renderScheduleCategories() {
  const content = document.getElementById('mainContent')
  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <div>
        <div style="font-size:18px;font-weight:700;color:var(--gray-800)">Kategori Schedule</div>
        <div style="font-size:12px;color:#6b7280;margin-top:2px">Kelola kategori agenda / kegiatan</div>
      </div>
      <button class="btn btn-primary btn-sm" onclick="schShowCatForm()">+ Tambah Kategori</button>
    </div>
    <div class="card" style="padding:0;overflow:hidden">
      <div id="schCatBody"><div class="loading-page"><div class="spinner-lg"></div></div></div>
    </div>`
  schLoadCatList()
}

async function schLoadCatList() {
  const el = document.getElementById('schCatBody')
  if (!el) return
  try {
    const res   = await apiGet('schedule/categories?all=1')
    const items = res.data?.items || []

    if (!items.length) {
      el.innerHTML = '<div style="text-align:center;padding:40px;color:#6b7280;font-size:13px">Belum ada kategori.</div>'
      return
    }

    el.innerHTML = `
      <table class="table" style="font-size:13px;margin:0">
        <thead>
          <tr>
            <th style="width:40px">#</th>
            <th>Nama</th>
            <th>Kode</th>
            <th style="width:80px">Warna</th>
            <th style="width:80px">Status</th>
            <th style="width:120px">Aksi</th>
          </tr>
        </thead>
        <tbody>
          ${items.map((c, i) => `
            <tr style="opacity:${c.is_aktif == 1 ? 1 : 0.5}">
              <td style="color:#9ca3af">${i + 1}</td>
              <td><strong>${esc(c.nama)}</strong></td>
              <td><code style="background:#f3f4f6;border-radius:4px;padding:2px 6px;font-size:11px">${esc(c.kode)}</code></td>
              <td>
                <div style="display:flex;align-items:center;gap:6px">
                  <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:${esc(c.warna||'#ccc')};border:1px solid #e5e7eb;flex-shrink:0"></span>
                  <span style="font-size:11px;color:#6b7280">${esc(c.warna||'—')}</span>
                </div>
              </td>
              <td>
                <span class="status-badge ${c.is_aktif == 1 ? 'status-selesai' : 'status-draft'}" style="font-size:10px">
                  ${c.is_aktif == 1 ? 'Aktif' : 'Nonaktif'}
                </span>
              </td>
              <td>
                <div style="display:flex;gap:4px">
                  <button class="btn btn-sm" style="background:#eff6ff;color:#1d4ed8;padding:3px 10px;font-size:11px"
                          onclick="schShowCatForm(${c.id})">Edit</button>
                  ${c.is_aktif == 1
                    ? `<button class="btn btn-sm" style="background:#fee2e2;color:#b91c1c;padding:3px 10px;font-size:11px"
                              onclick="schDeleteCat(${c.id},'${esc(c.nama)}')">Nonaktif</button>`
                    : `<button class="btn btn-sm" style="background:#f0fdf4;color:#166534;padding:3px 10px;font-size:11px"
                              onclick="schToggleCatAktif(${c.id},1,'${esc(c.nama)}')">Aktifkan</button>`
                  }
                </div>
              </td>
            </tr>`).join('')}
        </tbody>
      </table>`
  } catch(e) {
    el.innerHTML = `<div class="alert alert-danger" style="margin:16px">${e.message}</div>`
  }
}

async function schShowCatForm(id = null) {
  const isEdit = !!id
  let existing = { nama:'', kode:'', warna:'#6366f1', is_aktif: 1 }

  if (isEdit) {
    openModal('Edit Kategori', '<div class="spinner-sm"></div>')
    try {
      const res   = await apiGet('schedule/categories?all=1')
      const items = res.data?.items || []
      const found = items.find(c => c.id == id)
      if (found) existing = found
    } catch(e) { openModal('Error', `<div class="alert alert-danger">${e.message}</div>`); return }
  }

  const PRESET_COLORS = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#6366f1','#06b6d4','#9ca3af','#ec4899','#84cc16']

  const body = `
    <div class="form-group">
      <label class="form-label">Nama Kategori <span class="required">*</span></label>
      <input type="text" class="form-control" id="catNama" value="${esc(existing.nama)}" placeholder="Contoh: Pelatihan">
    </div>
    <div class="form-group">
      <label class="form-label">Kode <span class="required">*</span>
        <span style="font-size:11px;font-weight:400;color:#9ca3af">— huruf kecil, angka, underscore saja</span>
      </label>
      <input type="text" class="form-control" id="catKode" value="${esc(existing.kode)}"
             placeholder="contoh: pelatihan" ${isEdit ? 'style="background:#f9fafb"' : ''}>
    </div>
    <div class="form-group">
      <label class="form-label">Warna</label>
      <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
        ${PRESET_COLORS.map(c => `
          <label style="cursor:pointer" title="${c}">
            <input type="radio" name="catWarna" value="${c}" ${existing.warna === c ? 'checked' : ''} style="display:none"
                   onchange="document.getElementById('catWarnaHex').value=this.value">
            <span style="display:inline-block;width:22px;height:22px;border-radius:50%;background:${c};
              border:2.5px solid ${existing.warna === c ? '#1f2328':'transparent'};
              transition:border .15s"
              onclick="this.style.border='2.5px solid #1f2328';document.querySelectorAll('[name=catWarna]').forEach(r=>{if(r.value!==this.parentElement.querySelector('input').value)r.parentElement.querySelector('span').style.border='2.5px solid transparent'})"></span>
          </label>`).join('')}
      </div>
      <input type="text" class="form-control" id="catWarnaHex" value="${esc(existing.warna||'#6366f1')}"
             placeholder="#6366f1" style="width:120px;font-family:monospace;font-size:13px"
             oninput="document.querySelectorAll('[name=catWarna]').forEach(r=>r.parentElement.querySelector('span').style.border='2.5px solid transparent')">
    </div>
    ${isEdit ? `
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
        <input type="checkbox" id="catAktif" ${existing.is_aktif == 1 ? 'checked' : ''}> Aktif
      </label>
    </div>` : ''}`

  openModal(isEdit ? `Edit Kategori — ${esc(existing.nama)}` : 'Tambah Kategori Baru', body, [
    { label: 'Batal', cls: 'btn-secondary', action: closeModal },
    { label: isEdit ? '💾 Simpan' : '➕ Tambah', cls: 'btn-primary', action: async () => {
      const nama  = document.getElementById('catNama')?.value.trim()
      const kode  = document.getElementById('catKode')?.value.trim()
      const warna = document.getElementById('catWarnaHex')?.value.trim() ||
                    document.querySelector('input[name="catWarna"]:checked')?.value || '#6366f1'
      if (!nama || !kode) { showToast('Nama dan kode wajib diisi.', 'error'); return }
      if (!/^[a-z0-9_]+$/.test(kode)) { showToast('Kode hanya boleh huruf kecil, angka, dan underscore.', 'error'); return }

      const payload = { nama, kode, warna }
      if (isEdit) payload.is_aktif = document.getElementById('catAktif')?.checked ? 1 : 0

      try {
        if (isEdit) {
          await apiPut(`schedule/categories/${id}`, payload)
        } else {
          await apiPost('schedule/categories', payload)
        }
        closeModal()
        showToast(isEdit ? 'Kategori berhasil diperbarui.' : 'Kategori berhasil ditambahkan.')
        schLoadCatList()
        // Refresh daftar kategori untuk kalender juga
        await _schLoadCategories()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

async function schDeleteCat(id, nama) {
  openModal('Nonaktifkan Kategori', `
    <div style="text-align:center;padding:8px 0">
      <div style="font-size:32px;margin-bottom:8px">🏷️</div>
      <div style="font-size:14px;font-weight:700;color:#1f2328;margin-bottom:6px">Nonaktifkan Kategori?</div>
      <div style="font-size:13px;color:#6b7280">
        Kategori <strong>${esc(nama)}</strong> akan dinonaktifkan.<br>
        Agenda yang sudah ada tidak akan terpengaruh.
      </div>
    </div>`, [
    { label: 'Batal',         cls: 'btn-secondary', action: closeModal },
    { label: '🚫 Nonaktifkan', cls: 'btn-danger',    action: async () => {
      try {
        await apiDelete(`schedule/categories/${id}`)
        closeModal()
        showToast('Kategori berhasil dinonaktifkan.')
        schLoadCatList()
      } catch(e) { showToast(e.message, 'error') }
    }},
  ])
}

async function schToggleCatAktif(id, aktif, nama) {
  try {
    await apiPut(`schedule/categories/${id}`, { is_aktif: aktif })
    showToast(`Kategori "${nama}" berhasil ${aktif ? 'diaktifkan' : 'dinonaktifkan'}.`)
    schLoadCatList()
  } catch(e) { showToast(e.message, 'error') }
}
