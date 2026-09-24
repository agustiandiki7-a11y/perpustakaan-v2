<?php $current = basename(dirname($_SERVER['SCRIPT_NAME'])); ?>
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-book-open"></i>
        <span>Perpustakaan</span>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= $backendBase ?>/index.php" class="sidebar-link <?= basename($_SERVER['SCRIPT_NAME']) === 'index.php' && $current === $me['role'] ? 'active' : '' ?>">
            <i class="fas fa-gauge"></i> Dashboard
        </a>
        <a href="<?= $backendBase ?>/kategori/index.php" class="sidebar-link <?= $current === 'kategori' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i> Kategori
        </a>
        <a href="<?= $backendBase ?>/buku/index.php" class="sidebar-link <?= $current === 'buku' ? 'active' : '' ?>">
            <i class="fas fa-book"></i> Buku
        </a>
        <a href="<?= $backendBase ?>/peminjaman/index.php" class="sidebar-link <?= $current === 'peminjaman' ? 'active' : '' ?>">
            <i class="fas fa-right-left"></i> Peminjaman
        </a>
        <a href="<?= $backendBase ?>/pengembalian/index.php" class="sidebar-link <?= $current === 'pengembalian' ? 'active' : '' ?>">
            <i class="fas fa-rotate-left"></i> Pengembalian
        </a>
        <?php if (($me['role'] ?? '') === 'admin'): ?>
            <a href="<?= $backendBase ?>/pengguna/index.php" class="sidebar-link <?= $current === 'pengguna' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Pengguna
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= baseUrlPath() ?>/index.php" class="sidebar-link">
            <i class="fas fa-house"></i> Lihat Situs
        </a>
        <a href="<?= baseUrlPath() ?>/backend/logout.php" class="sidebar-link sidebar-logout">
            <i class="fas fa-right-from-bracket"></i> Keluar
        </a>
    </div>
</aside>

<div class="admin-main">
    <header class="admin-topbar">
        <button class="sidebar-toggle" type="button" onclick="document.querySelector('.admin-shell').classList.toggle('sidebar-open')">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="topbar-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="topbar-user">
            <span class="topbar-name"><?= htmlspecialchars($me['nama'] ?: $me['username'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="topbar-role"><?= htmlspecialchars(ucfirst($me['role']), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </header>

    <main class="admin-content">
        <?php if ($flash): ?>
            <div class="alert-flash alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <i class="fas <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
