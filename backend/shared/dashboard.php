<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$totalBuku = (int) ($db->query("SELECT COUNT(*) FROM books WHERE status = 'aktif'")->fetchColumn() ?? 0);
$totalKategori = (int) ($db->query("SELECT COUNT(*) FROM categories WHERE status = 'aktif'")->fetchColumn() ?? 0);
$totalDipinjam = (int) ($db->query("SELECT COUNT(*) FROM loans WHERE status = 'dipinjam'")->fetchColumn() ?? 0);
$totalAnggota = (int) ($db->query("SELECT COUNT(*) FROM users WHERE role = 'peminjam'")->fetchColumn() ?? 0);

$stmtOverdue = $db->query("
    SELECT COUNT(*)
    FROM loans
    WHERE status = 'dipinjam'
      AND tanggal_jatuh_tempo < CURDATE()
");
$totalTerlambat = (int) $stmtOverdue->fetchColumn();

$stmtActive = $db->query("
    SELECT loans.id, loans.kode_peminjaman, loans.tanggal_jatuh_tempo,
           users.nama AS nama_peminjam,
           GROUP_CONCAT(books.judul ORDER BY books.judul SEPARATOR ', ') AS judul_buku
    FROM loans
    INNER JOIN users ON users.id = loans.user_id
    LEFT JOIN loan_details ON loan_details.loan_id = loans.id
    LEFT JOIN books ON books.id = loan_details.book_id
    WHERE loans.status = 'dipinjam'
    GROUP BY loans.id
    ORDER BY
        CASE WHEN loans.tanggal_jatuh_tempo < CURDATE() THEN 0 ELSE 1 END,
        loans.tanggal_jatuh_tempo ASC,
        loans.id DESC
    LIMIT 10
");
$activeLoans = $stmtActive ? $stmtActive->fetchAll() : [];
$today = date('Y-m-d');
?>

<section class="dashboard-welcome">
    <h2>Selamat datang, <?= htmlspecialchars($me['nama'] ?: $me['username'], ENT_QUOTES, 'UTF-8') ?>.</h2>
    <p>Dashboard menampilkan aktivitas yang masih berjalan. Data peminjaman yang sudah dikembalikan tidak ditampilkan di sini.</p>
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
    <div class="col-6 col-lg-3"><div class="stat-card"><i class="fas fa-book"></i><span class="stat-number"><?= number_format($totalBuku) ?></span><span class="stat-label">Buku aktif</span></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><i class="fas fa-tags"></i><span class="stat-number"><?= number_format($totalKategori) ?></span><span class="stat-label">Kategori aktif</span></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><i class="fas fa-right-left"></i><span class="stat-number"><?= number_format($totalDipinjam) ?></span><span class="stat-label">Belum dikembalikan</span></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><i class="fas fa-triangle-exclamation"></i><span class="stat-number"><?= number_format($totalTerlambat) ?></span><span class="stat-label">Terlambat</span></div></div>
</div>

<div class="panel">
    <div class="panel-heading">
        <div>
            <h2>Peminjaman yang Belum Dikembalikan</h2>
            <p class="form-hint">Urutan memprioritaskan transaksi yang sudah melewati jatuh tempo.</p>
        </div>
        <a href="<?= $backendBase ?>/pengembalian/index.php" class="btn-outline">Proses Pengembalian</a>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>No</th><th>Kode</th><th>Peminjam</th><th>Buku</th><th>Jatuh Tempo</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($activeLoans)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada peminjaman aktif.</td></tr>
            <?php else: ?>
                <?php foreach ($activeLoans as $i => $loan): ?>
                    <?php
                    $isLate = $loan['tanggal_jatuh_tempo'] < $today;
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['nama_peminjam'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($isLate): ?>
                                <span class="badge-pill badge-terlambat">Terlambat</span>
                            <?php else: ?>
                                <span class="badge-pill badge-dipinjam">Belum kembali</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
