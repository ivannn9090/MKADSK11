<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">
      <svg viewBox="0 0 24 24" fill="white" width="28" height="28"><path d="M3 3h18v18H3V3zm2 2v14h14V5H5zm2 2h10v2H7V7zm0 4h10v2H7v-2zm0 4h6v2H7v-2z"/></svg>
    </div>
    <div class="sidebar-brand">
      <div class="sidebar-title">MKA System</div>
      <div class="sidebar-sub">Monitoring Kegiatan</div>
    </div>
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Sembunyikan sidebar">
      <svg class="icon-collapse" viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
      <svg class="icon-expand" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display:none"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
    </button>
  </div>

  <nav class="sidebar-nav" id="sidebarNav">
    <a href="#" class="nav-item active" data-page="dashboard" data-label="Dashboard">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
      <span>Dashboard</span>
    </a>
    <a href="#" class="nav-item admin-only" data-page="settings" data-label="Pengaturan" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/></svg>
      <span>Pengaturan</span>
    </a>
    <a href="#" class="nav-item" data-page="serasi" data-label="SERASI">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M20 6h-2.18c.07-.44.18-.86.18-1.3C18 2.12 15.88 0 13.3 0c-1.3 0-2.49.56-3.3 1.44C9.19.56 8 0 6.7 0 4.12 0 2 2.12 2 4.7c0 .44.11.86.18 1.3H0v14c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-8.5 12H4V8h7.5v10zm8.5 0h-7V8h7v10zM6.7 6c-.96 0-1.7-.74-1.7-1.7C5 3.47 5.74 2 6.7 2c.96 0 1.63.47 2.3 1.3L9.88 4.4C9.21 5.22 8.17 6 6.7 6zm6.6 0c-1.47 0-2.51-.78-3.18-1.6L11 3.3C11.67 2.47 12.34 2 13.3 2c.96 0 1.7 1.47 1.7 2.3 0 .96-.74 1.7-1.7 1.7z"/></svg>
      <span>SERASI</span>
      <span style="font-size:9px;font-weight:700;background:#f59e0b;color:#fff;border-radius:4px;padding:1px 5px;margin-left:auto;letter-spacing:.5px">SOON</span>
    </a>
    <!-- ── Kegiatan dropdown group ───────────────────────────── -->
    <div class="nav-group" id="navGroupKegiatan">
      <div class="nav-group-header" id="navGroupKegiatanHeader" data-label="Kegiatan">
        <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
        <span>Kegiatan</span>
        <svg class="nav-group-arrow" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M7 10l5 5 5-5z"/></svg>
      </div>
      <div class="nav-group-children" id="navGroupKegiatanChildren">
        <a href="#" class="nav-item nav-sub-item" data-page="kegiatan" data-label="On Budget">
          <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
          <span>On Budget</span>
        </a>
        <a href="#" class="nav-item nav-sub-item" data-page="off_budget" data-label="Off Budget">
          <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M4 6h16v2H4zm2-4h12v2H6zm14 8H4c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2zm-1 9H5v-2h14v2zm0-4H5v-2h14v2z"/></svg>
          <span>Off Budget</span>
        </a>
      </div>
    </div>
    <a href="#" class="nav-item" data-page="laporan" data-label="Laporan">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
      <span>Laporan</span>
    </a>
    <a href="#" class="nav-item users-nav-only" data-page="users" data-label="Pengguna" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
      <span>Pengguna</span>
    </a>
    <a href="#" class="nav-item teams-nav-only" data-page="teams" data-label="Tim" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
      <span>Tim</span>
    </a>
    <a href="#" class="nav-item audit-nav-only" data-page="audit" data-label="Audit Trail" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
      <span>Audit Trail</span>
    </a>
    <a href="#" class="nav-item rpd-only" data-page="rpd" data-label="Rencana Penarikan Dana" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
      <span>Rencana Penarikan Dana</span>
    </a>

    <!-- ── URL Shortener ────────────────────────────────── -->
    <a href="#" class="nav-item" data-page="short_links" data-label="URL Shortener & QR Code">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M17 7h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1 0 1.43-.98 2.63-2.31 2.98l1.46 1.46C20.88 15.61 22 13.87 22 12c0-2.76-2.24-5-5-5zm-1 4h-2.19l2 2H16v-2zM2 4.27l3.11 3.11A4.991 4.991 0 0 0 2 12c0 2.76 2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1 0-1.55 1.14-2.84 2.64-3.06L8.73 11H8v2h2.73l8 8L20 19.73 3.27 3 2 4.27z"/></svg>
      <span>URL Shortener</span>
    </a>

    <!-- ── Biolink Manager ───────────────────────────────── -->
    <a href="#" class="nav-item" data-page="biolink" data-label="Biolink Manager">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7C4.24 7 2 9.24 2 12s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1 0 1.71-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
      <span>Biolink</span>
    </a>

    <!-- ── Schedule ───────────────────────────────────────── -->
    <div class="nav-section-label schedule-only">Schedule</div>

    <a href="#" class="nav-item schedule-only" data-page="schedule" data-label="Dashboard Kalender">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H5V8h14v13zm-7-11a5 5 0 0 0 0 10 5 5 0 0 0 0-10zm-1 3h2v4.5l-2.5 1.5-.5-.86 1-.6V13z"/></svg>
      <span>Schedule</span>
    </a>

    <a href="#" class="nav-item schedule-admin-only" data-page="schedule_google" data-label="Pengaturan Google Calendar" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
      <span>Google Calendar</span>
    </a>

    <a href="#" class="nav-item schedule-admin-only" data-page="schedule_categories" data-label="Kategori Schedule" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M17.63 5.84C17.27 5.33 16.67 5 16 5L5 5.01C3.9 5.01 3 5.9 3 7v10c0 1.1.9 1.99 2 1.99L16 19c.67 0 1.27-.33 1.63-.84L22 12l-4.37-6.16z"/></svg>
      <span>Kategori</span>
    </a>

    <!-- ── Layanan Telaah Konsep ─────────────────────────── -->
    <div class="nav-section-label ltk-only" style="display:none">Telaah Konsep</div>

    <a href="#" class="nav-item ltk-only ltk-tu-only" data-page="ltk_dashboard_tu" data-label="Dashboard Telaah" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
      <span>Dashboard Telaah</span>
    </a>

    <a href="#" class="nav-item ltk-only ltk-pic-only" data-page="ltk_dashboard_pic" data-label="Dashboard Telaah" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
      <span>Dashboard Telaah</span>
    </a>

    <a href="#" class="nav-item ltk-only ltk-klien-only" data-page="ltk_dashboard_klien" data-label="Pengajuan Telaah" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
      <span>Pengajuan Telaah</span>
    </a>

    <a href="#" class="nav-item ltk-only ltk-tu-only" data-page="ltk_permohonan" data-label="Permohonan Telaah" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
      <span>Permohonan</span>
    </a>

    <a href="#" class="nav-item ltk-only ltk-pic-only" data-page="ltk_tugas_pic" data-label="Tugas Saya" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
      <span>Tugas Saya</span>
    </a>

    <a href="#" class="nav-item ltk-only ltk-tu-only" data-page="ltk_antrian" data-label="Antrian Telaah" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M4 6h16v2H4zm4 5h12v2H8zm-4-9h2v2H4zm0 8h2v2H4zm0 4h2v2H4zm4-4h12v2H8zm0 4h8v2H8z"/></svg>
      <span>Antrian</span>
    </a>

    <a href="#" class="nav-item ltk-only ltk-tu-only" data-page="ltk_form_builder" data-label="Form Builder" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
      <span>Form Builder</span>
    </a>

    <a href="#" class="nav-item ltk-only ltk-tu-only" data-page="ltk_laporan" data-label="Laporan Telaah" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
      <span>Laporan Telaah</span>
    </a>

    <a href="#" class="nav-item ltk-only ltk-admin-only" data-page="ltk_admin" data-label="Pengaturan Telaah" style="display:none">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/></svg>
      <span>Pengaturan Telaah</span>
    </a>

  </nav>

  <div class="sidebar-footer">
    <div class="user-info" id="userInfo" title="Klik untuk edit profil" style="cursor:pointer"></div>
    <button class="btn-logout" id="btnLogout">
      <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
      <span>Keluar</span>
    </button>
  </div>
</aside>

<!-- ============================================================
     Profile Slide-Over Panel
     ============================================================ -->
<div class="profile-overlay" id="profileOverlay"></div>
<div class="profile-panel" id="profilePanel">
  <div class="profile-panel-header">
    <div>
      <div class="profile-panel-title">Profil Saya</div>
      <div class="profile-panel-sub" id="profilePanelSub">—</div>
    </div>
    <button class="profile-panel-close" id="profilePanelClose" title="Tutup">
      <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
    </button>
  </div>

  <div class="profile-panel-body">
    <!-- Tab nav -->
    <div class="profile-tabs">
      <button class="profile-tab active" data-tab="info">Informasi</button>
      <button class="profile-tab" data-tab="password">Ubah Password</button>
    </div>

    <!-- Tab: Informasi -->
    <div class="profile-tab-content active" id="profileTabInfo">
      <form id="profileForm" autocomplete="off">
        <div class="profile-avatar" id="profileAvatar"></div>

        <div class="form-group">
          <label class="form-label">Nama Lengkap <span class="required">*</span></label>
          <input type="text" class="form-control" id="profileNama" maxlength="150" required>
        </div>
        <div class="form-group">
          <label class="form-label">Jabatan</label>
          <input type="text" class="form-control" id="profileJabatan" maxlength="200" placeholder="Opsional">
        </div>
        <div class="form-group">
          <label class="form-label">Email <span class="required">*</span></label>
          <input type="email" class="form-control" id="profileEmail" maxlength="150" required>
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <input type="text" class="form-control" id="profileRole" disabled style="background:var(--gray-100)">
        </div>

        <div class="profile-actions">
          <button type="submit" class="btn btn-primary" id="profileSaveBtn">
            <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z"/></svg>
            Simpan Perubahan
          </button>
        </div>
      </form>
    </div>

    <!-- Tab: Ubah Password -->
    <div class="profile-tab-content" id="profileTabPassword">
      <!-- Warning banner: hanya tampil jika must_change_password = 1 -->
      <div id="profileMustChangeBanner" style="display:none;align-items:flex-start;gap:10px;
        background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:12px 14px;
        margin-bottom:16px">
        <svg viewBox="0 0 24 24" fill="#d97706" width="20" height="20" style="flex-shrink:0;margin-top:1px">
          <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
        </svg>
        <div>
          <div style="font-weight:700;font-size:13px;color:#92400e">Password Default Terdeteksi</div>
          <div style="font-size:12px;color:#78350f;margin-top:3px;line-height:1.5">
            Akun Anda baru dibuat oleh admin dan menggunakan password default.<br>
            <strong>Harap ubah password Anda sekarang</strong> agar akun lebih aman.
          </div>
        </div>
      </div>
      <form id="passwordForm" autocomplete="off">
        <div class="form-group">
          <label class="form-label">Password Saat Ini <span class="required">*</span></label>
          <div class="input-eye-wrap">
            <input type="password" class="form-control" id="currentPassword" required>
            <button type="button" class="input-eye-btn" data-target="currentPassword">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
            </button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password Baru <span class="required">*</span></label>
          <div class="input-eye-wrap">
            <input type="password" class="form-control" id="newPassword" minlength="8" required>
            <button type="button" class="input-eye-btn" data-target="newPassword">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
            </button>
          </div>
          <small style="color:var(--gray-500);font-size:11px">Minimal 8 karakter</small>
        </div>
        <div class="form-group">
          <label class="form-label">Konfirmasi Password Baru <span class="required">*</span></label>
          <div class="input-eye-wrap">
            <input type="password" class="form-control" id="newPasswordConfirm" minlength="8" required>
            <button type="button" class="input-eye-btn" data-target="newPasswordConfirm">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
            </button>
          </div>
        </div>

        <div class="profile-actions">
          <button type="submit" class="btn btn-primary" id="passwordSaveBtn">
            <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M18 8h-1V6A5 5 0 0 0 7 6v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zm-6 9a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm3.1-9H8.9V6A3.1 3.1 0 0 1 12 2.9 3.1 3.1 0 0 1 15.1 6v2z"/></svg>
            Ubah Password
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
