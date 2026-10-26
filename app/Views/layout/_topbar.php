<div class="main-wrapper">
  <!-- Top Header -->
  <header class="topbar">
    <button class="menu-toggle" id="menuToggle">
      <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
    </button>
    <h2 class="page-title" id="pageTitle">Dashboard</h2>
    <div class="topbar-right">
      <button class="notif-btn" id="notifBtn" title="Notifikasi">
        <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
        <span class="notif-badge" id="notifBadge" style="display:none">0</span>
      </button>
    </div>
  </header>

  <!-- Notification Dropdown -->
  <div class="notif-dropdown" id="notifDropdown" style="display:none">
    <div class="notif-header">
      <span>Notifikasi</span>
      <button id="markAllRead">Tandai semua dibaca</button>
    </div>
    <div id="notifList"></div>
  </div>

  <!-- Page Content -->
  <main class="content" id="mainContent">
    <div class="loading-page" id="loadingPage"><div class="spinner-lg"></div></div>
  </main>
</div>

<!-- Floating: tampilkan sidebar (muncul saat sidebar collapsed) -->
<button id="showSidebarBtn" class="show-sidebar-btn" title="Tampilkan sidebar" style="display:none">
  <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
</button>

<!-- Back to top button -->
<button id="backToTopBtn" class="back-to-top" title="Kembali ke atas" style="display:none">
  <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg>
</button>
