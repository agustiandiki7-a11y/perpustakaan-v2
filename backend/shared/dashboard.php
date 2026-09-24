<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$totalBuku = (int) ($db->query("SELECT COUNT(*) c FROM books WHERE status = 'aktif'")->fetch()['c'] ?? 0);
$totalKategori = (int) ($db->query("SELECT COUNT(*) c FROM categories WHERE status = 'aktif'")->fetch()['c'] ?? 0);
$totalDipinjam = (int) ($db->query("SELECT COUNT(*) c FROM loans WHERE status = 'dipinjam'")->fetch()['c'] ?? 0);
$totalAnggota = (int) ($db->query("SELECT COUNT(*) c FROM users WHERE role = 'peminjam'")->fetch()['c'] ?? 0);

$stmtRecent = $db->query("
    SELECT loans.kode_peminjaman, loans.status, loans.tanggal_pinjam, loans.tanggal_jatuh_tempo, users.nama AS nama_peminjam
    FROM loans
    LEFT JOIN users ON users.id = loans.user_id
    ORDER BY loans.id DESC
    LIMIT 8
");
$recentLoans = $stmtRecent ? $stmtRecent->fetchAll() : [];
?>

<section class="dashboard-welcome">
    <h2>Selamat datang, <?= htmlspecialchars($me['nama'] ?: $me['username'], ENT_QUOTES, 'UTF-8') ?>.</h2>
    <p>Kelola koleksi dan aktivitas perpustakaan dari satu halaman. Kamu masuk sebagai <strong><?= htmlspecialchars(ucfirst($me['role']), ENT_QUOTES, 'UTF-8') ?></strong>.</p>
    <div class="quick-actions">
        <a class="quick-action" href="<?= $backendBase ?>/buku/index.php"><i class="fas fa-book"></i> Kelola Buku</a>
        <a class="quick-action" href="<?= $backendBase ?>/peminjaman/index.php"><i class="fas fa-arrow-right-arrow-left"></i> Peminjaman</a>
        <a class="quick-action" href="<?= $backendBase ?>/pengembalian/index.php"><i class="fas fa-rotate-left"></i> Pengembalian</a>
        <a class="quick-action" href="<?= $backendBase ?>/laporan/index.php"><i class="fas fa-file-lines"></i> Laporan</a>
        <?php if (($me['role'] ?? '') === 'admin'): ?>
            <a class="quick-action" href="<?= $backendBase ?>/pengguna/index.php"><i class="fas fa-users"></i> Pengguna</a>
        <?php endif; ?>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <i class="fas fa-book"></i>
            <span class="stat-number"><?= number_format($totalBuku) ?></span>
            <span class="stat-label">Buku aktif</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <i class="fas fa-tags"></i>
            <span class="stat-number"><?= number_format($totalKategori) ?></span>
            <span class="stat-label">Kategori aktif</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <i class="fas fa-right-left"></i>
            <span class="stat-number"><?= number_format($totalDipinjam) ?></span>
            <span class="stat-label">Sedang dipinjam</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <span class="stat-number"><?= number_format($totalAnggota) ?></span>
            <span class="stat-label">Anggota terdaftar</span>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <h2>Peminjaman terbaru</h2>
        <a href="<?= $backendBase ?>/peminjaman/index.php" class="btn-outline">Lihat semua</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Peminjam</th>
                    <th>Tgl Pinjam</th>
                    <th>Batas Kembali</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLoans)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data peminjaman.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentLoans as $loan): ?>
                        <tr>
                            <td><?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['nama_peminjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['tanggal_pinjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge-pill badge-<?= htmlspecialchars($loan['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($loan['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
