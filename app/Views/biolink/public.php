<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($profile['nama']) ?> — Biolink</title>
  <meta name="description" content="<?= esc($profile['bio'] ?? '') ?>">
  <!-- Open Graph -->
  <meta property="og:title"       content="<?= esc($profile['nama']) ?>">
  <meta property="og:description" content="<?= esc($profile['bio'] ?? '') ?>">
  <?php if ($profile['foto_url']): ?>
  <meta property="og:image"       content="<?= esc($profile['foto_url']) ?>">
  <?php endif; ?>

  <style>
    /* ── Reset ───────────────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }

    body {
      font-family: -apple-system, "Segoe UI", system-ui, Roboto, sans-serif;
      background: linear-gradient(150deg, #e8edf5 0%, #dce4f0 50%, #e4eaf4 100%);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: 40px 16px 60px;
      position: relative;
    }

    /* ── Background image (di belakang card) ────────────────────────── */
    .bl-bg-image {
      position: fixed;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: -1;
    }
    /* overlay gelap tipis agar card tetap terbaca */
    .bl-bg-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .28);
      z-index: -1;
    }

    /* ── Kartu Utama ─────────────────────────────────────────────────── */
    .bl-card {
      width: 100%;
      max-width: 460px;
      background: #ffffff;
      border-radius: 24px;
      box-shadow: 0 8px 40px rgba(0, 0, 0, .10), 0 2px 8px rgba(0,0,0,.06);
      overflow: hidden;
    }

    /* ── Banner Atas (strip warna aksen / cover image) ──────────────── */
    .bl-banner {
      height: 88px;
      background: var(--accent, #1a6fb5);
      position: relative;
      flex-shrink: 0;
      overflow: hidden;
    }
    .bl-banner::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(255,255,255,.12) 0%, transparent 60%);
      z-index: 1;
    }
    .bl-banner-cover {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: 0;
    }

    /* ── Body Kartu ─────────────────────────────────────────────────── */
    .bl-body {
      padding: 0 28px 32px;
    }

    /* ── Avatar ─────────────────────────────────────────────────────── */
    .bl-avatar-wrap {
      display: flex;
      justify-content: center;
      margin-top: -48px;
      margin-bottom: 16px;
      position: relative;
      z-index: 1;
    }
    .bl-avatar,
    .bl-avatar-placeholder {
      width: 96px;
      height: 96px;
      border-radius: 50%;
      border: 4px solid #ffffff;
      box-shadow: 0 4px 16px rgba(0,0,0,.14);
      flex-shrink: 0;
    }
    .bl-avatar {
      object-fit: cover;
      background: #cbd5e1;
    }
    .bl-avatar-placeholder {
      background: var(--accent, #1a6fb5);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 38px;
      font-weight: 700;
      color: #ffffff;
      letter-spacing: -1px;
    }

    /* ── Nama & Bio ──────────────────────────────────────────────────── */
    .bl-info {
      text-align: center;
      margin-bottom: 24px;
    }
    .bl-nama {
      font-size: 1.3rem;
      font-weight: 800;
      color: #0f172a;
      line-height: 1.25;
      letter-spacing: -.3px;
    }
    .bl-bio {
      font-size: 0.85rem;
      color: #64748b;
      margin-top: 7px;
      line-height: 1.65;
    }

    /* ── Divider ─────────────────────────────────────────────────────── */
    .bl-divider {
      height: 1px;
      background: #f1f5f9;
      margin: 0 0 22px;
    }

    /* ── Tombol / Link Items ─────────────────────────────────────────── */
    .bl-links {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .bl-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 14px 20px;
      border-radius: 12px;
      background: #f8fafc;
      color: #1e293b;
      font-size: 0.9375rem;
      font-weight: 600;
      text-decoration: none;
      border: 1.5px solid #e8edf5;
      transition: transform .13s ease, box-shadow .13s ease,
                  background .13s ease, color .13s ease, border-color .13s ease;
      cursor: pointer;
      line-height: 1.3;
      word-break: break-word;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .bl-btn::before {
      content: '';
      position: absolute;
      inset: 0;
      background: var(--accent, #1a6fb5);
      opacity: 0;
      transition: opacity .13s ease;
      border-radius: inherit;
    }
    .bl-btn:hover::before { opacity: 1; }
    .bl-btn:hover {
      color: #ffffff;
      border-color: var(--accent, #1a6fb5);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,.12);
    }
    .bl-btn span { position: relative; z-index: 1; }
    .bl-btn .bl-ikon {
      font-size: 1.15em;
      flex-shrink: 0;
      position: relative;
      z-index: 1;
    }

    /* ── Sosial Media ────────────────────────────────────────────────── */
    .bl-sosmed {
      display: flex;
      justify-content: center;
      gap: 12px;
      margin-top: 24px;
      flex-wrap: wrap;
    }
    .bl-sosmed-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }
    .bl-sosmed a {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #f1f5f9;
      border: 1.5px solid #e2e8f0;
      color: #475569;
      text-decoration: none;
      font-size: 1.05rem;
      transition: background .12s, color .12s, border-color .12s, transform .12s;
    }
    .bl-sosmed a:hover {
      background: var(--accent, #1a6fb5);
      color: #fff;
      border-color: var(--accent, #1a6fb5);
      transform: translateY(-2px);
    }
    .bl-sosmed-label {
      font-size: 0.68rem;
      color: #94a3b8;
    }

    /* ── Empty state ─────────────────────────────────────────────────── */
    .bl-empty {
      text-align: center;
      padding: 24px 0 8px;
      color: #94a3b8;
      font-size: 0.875rem;
    }

    /* ── Footer (di luar kartu) ──────────────────────────────────────── */
    .bl-footer {
      margin-top: 24px;
      font-size: 0.72rem;
      color: #94a3b8;
      text-align: center;
      line-height: 1.7;
    }
    .bl-footer a {
      color: #94a3b8;
      text-decoration: none;
      border-bottom: 1px solid #cbd5e1;
    }
    .bl-footer a:hover { color: var(--accent, #1a6fb5); border-color: var(--accent, #1a6fb5); }

    /* ── Responsif ───────────────────────────────────────────────────── */
    @media (max-width: 480px) {
      body { padding: 24px 12px 48px; }
      .bl-body { padding: 0 20px 28px; }
      .bl-banner { height: 72px; }
      .bl-avatar-wrap { margin-top: -44px; }
    }
  </style>

  <style>:root { --accent: <?= esc($profile['warna_aksen'] ?? '#1a6fb5') ?>; }</style>
</head>
<body>

  <?php if (!empty($profile['bg_url'])): ?>
    <!-- Background image di belakang seluruh halaman -->
    <img src="<?= esc($profile['bg_url']) ?>"
         alt=""
         class="bl-bg-image"
         aria-hidden="true">
    <div class="bl-bg-overlay"></div>
  <?php endif; ?>

  <!-- ── Kartu Utama ──────────────────────────────────────────────── -->
  <div class="bl-card">

    <!-- Banner warna / cover -->
    <div class="bl-banner">
      <?php if (!empty($profile['cover_url'])): ?>
        <img src="<?= esc($profile['cover_url']) ?>"
             alt="Cover <?= esc($profile['nama']) ?>"
             class="bl-banner-cover">
      <?php endif; ?>
    </div>

    <div class="bl-body">

      <!-- Avatar -->
      <div class="bl-avatar-wrap">
        <?php if ($profile['foto_url']): ?>
          <img src="<?= esc($profile['foto_url']) ?>"
               alt="Foto <?= esc($profile['nama']) ?>"
               class="bl-avatar">
        <?php else: ?>
          <div class="bl-avatar-placeholder">
            <?= mb_strtoupper(mb_substr($profile['nama'], 0, 1)) ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Nama & Bio -->
      <div class="bl-info">
        <h1 class="bl-nama"><?= esc($profile['nama']) ?></h1>
        <?php if (!empty($profile['bio'])): ?>
          <p class="bl-bio"><?= nl2br(esc($profile['bio'])) ?></p>
        <?php endif; ?>
      </div>

      <!-- Divider -->
      <div class="bl-divider"></div>

      <!-- Daftar Tautan -->
      <?php if (empty($profile['items'])): ?>
        <div class="bl-empty">Belum ada tautan yang tersedia.</div>
      <?php else: ?>
        <nav class="bl-links" aria-label="Daftar tautan">
          <?php foreach ($profile['items'] as $item): ?>
            <a href="<?= esc($item['url_tujuan']) ?>"
               class="bl-btn"
               target="_blank"
               rel="noopener noreferrer">
              <?php if (!empty($item['ikon'])): ?>
                <span class="bl-ikon"><?= esc($item['ikon']) ?></span>
              <?php endif; ?>
              <span><?= esc($item['judul']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>

      <!-- Media Sosial -->
      <?php if (!empty($profile['sosmed_json'])): ?>
        <?php
          $ikonSosmed = [
            'instagram' => '📸', 'twitter'  => '🐦', 'x'        => '✖',
            'facebook'  => '👤', 'youtube'  => '▶',  'linkedin' => '💼',
            'tiktok'    => '🎵', 'github'   => '🐙', 'website'  => '🌐',
            'email'     => '✉',  'whatsapp' => '💬', 'telegram' => '✈',
          ];
        ?>
        <div class="bl-sosmed" aria-label="Media sosial">
          <?php foreach ($profile['sosmed_json'] as $sm): ?>
            <?php
              $platform = strtolower($sm['platform'] ?? 'website');
              $ikon     = $ikonSosmed[$platform] ?? '🔗';
              $label    = ucfirst($platform);
            ?>
            <div class="bl-sosmed-item">
              <a href="<?= esc($sm['url']) ?>"
                 target="_blank"
                 rel="noopener noreferrer"
                 title="<?= esc($label) ?>"
                 aria-label="<?= esc($label) ?>">
                <?= $ikon ?>
              </a>
              <span class="bl-sosmed-label"><?= esc($label) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div><!-- /.bl-body -->
  </div><!-- /.bl-card -->

  <!-- Footer di luar kartu -->
  <footer class="bl-footer">
    &copy; <?= date('Y') ?> <?= esc($profile['nama']) ?>
  </footer>

</body>
</html>
