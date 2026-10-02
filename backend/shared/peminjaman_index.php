<?php
$pageTitle = 'Peminjaman';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$today = date('Y-m-d');
$historyPerPage = 10;
$historyPage = max(1, (int) ($_GET['page'] ?? 1));

$members = $db->query("
    SELECT id, nama, username
    FROM users
    WHERE role = 'peminjam'
    ORDER BY nama ASC
")->fetchAll();

$availableBooks = $db->query("
    SELECT id, judul, kode_buku, stok_tersedia
    FROM books
    WHERE status = 'aktif' AND stok_tersedia > 0
    ORDER BY judul ASC
")->fetchAll();

$pendingLoans = $db->query("
    SELECT loans.id, loans.kode_peminjaman, loans.tanggal_pengajuan, loans.catatan,
           users.nama AS nama_peminjam, users.username,
           GROUP_CONCAT(CONCAT(books.judul, ' (', loan_details.jumlah, ' buku)') ORDER BY books.judul SEPARATOR ', ') AS judul_buku
    FROM loans
    INNER JOIN users ON users.id = loans.user_id
    INNER JOIN loan_details ON loan_details.loan_id = loans.id
    INNER JOIN books ON books.id = loan_details.book_id
    WHERE loans.status = 'menunggu'
    GROUP BY loans.id
    ORDER BY loans.tanggal_pengajuan ASC, loans.id ASC
")->fetchAll();

$totalHistory = (int) $db->query("
    SELECT COUNT(*) FROM loans
    WHERE status IN ('dipinjam', 'dikembalikan', 'terlambat', 'ditolak')
")->fetchColumn();

$totalHistoryPages = max(1, (int) ceil($totalHistory / $historyPerPage));
if ($historyPage > $totalHistoryPages) {
    $historyPage = $totalHistoryPages;
}
$historyOffset = ($historyPage - 1) * $historyPerPage;

$stmtHistory = $db->prepare("
    SELECT loans.*, users.nama AS nama_peminjam,
           GROUP_CONCAT(CONCAT(books.judul, ' (', loan_details.jumlah, ' buku)') ORDER BY books.judul SEPARATOR ', ') AS judul_buku
    FROM loans
    LEFT JOIN users ON users.id = loans.user_id
    LEFT JOIN loan_details ON loan_details.loan_id = loans.id
    LEFT JOIN books ON books.id = loan_details.book_id
    WHERE loans.status IN ('dipinjam', 'dikembalikan', 'terlambat', 'ditolak')
    GROUP BY loans.id
    ORDER BY loans.id DESC
    LIMIT ? OFFSET ?
");
$stmtHistory->bindValue(1, $historyPerPage, PDO::PARAM_INT);
$stmtHistory->bindValue(2, $historyOffset, PDO::PARAM_INT);
$stmtHistory->execute();
$loans = $stmtHistory->fetchAll();
?>

<div class="panel mb-4">
    <div class="panel-heading">
        <div>
            <h2>Permintaan Peminjaman <?= !empty($pendingLoans) ? '<span class="badge-pill badge-menunggu">' . count($pendingLoans) . ' Menunggu</span>' : '' ?></h2>
            <p class="form-hint">Pengajuan dari peminjam yang membutuhkan persetujuan admin atau petugas.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>No</th><th>Kode</th><th>Peminjam</th><th>Buku</th><th>Tanggal Pengajuan</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (empty($pendingLoans)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada permintaan peminjaman.</td></tr>
            <?php else: ?>
                <?php foreach ($pendingLoans as $i => $loan): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($loan['nama_peminjam'], ENT_QUOTES, 'UTF-8') ?><br><small class="text-muted">@<?= htmlspecialchars($loan['username'], ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><?= htmlspecialchars($loan['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['tanggal_pengajuan'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge-pill badge-menunggu">Menunggu</span></td>
                        <td>
                            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                                <form action="proses.php" method="POST" onsubmit="return confirm('Setujui peminjaman ini? Tanggal dan stok akan diproses otomatis.');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="konfirmasi">
                                    <input type="hidden" name="loan_id" value="<?= (int) $loan['id'] ?>">
                                    <button type="submit" class="btn-brand"><i class="fas fa-check"></i> Setujui</button>
                                </form>
                                <form action="proses.php" method="POST" onsubmit="return confirm('Tolak peminjaman ini?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="tolak">
                                    <input type="hidden" name="loan_id" value="<?= (int) $loan['id'] ?>">
                                    <button type="submit" class="btn-danger-outline"><i class="fas fa-xmark"></i> Tolak</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel mb-4">
    <div class="panel-heading">
        <div>
            <h2>Catat Peminjaman Manual</h2>
            <p class="form-hint">Tanggal pinjam dan jatuh tempo dikunci oleh sistem. Petugas hanya memilih peminjam, buku, dan jumlah.</p>
        </div>
    </div>

    <form action="proses.php" method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">

        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Peminjam</label>
                <select name="user_id" class="form-control" required>
                    <option value="">-- Pilih Anggota --</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= (int) $member['id'] ?>">
                            <?= htmlspecialchars($member['nama'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($member['username'], ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Buku</label>
                <select name="book_id" class="form-control" required>
                    <option value="">-- Pilih Buku --</option>
                    <?php foreach ($availableBooks as $book): ?>
                        <option value="<?= (int) $book['id'] ?>">
                            <?= htmlspecialchars($book['kode_buku'] . ' — ' . $book['judul'], ENT_QUOTES, 'UTF-8') ?> (stok <?= (int) $book['stok_tersedia'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Jumlah</label>
                <input type="number" name="jumlah" class="form-control" min="1" value="1" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Tanggal Otomatis</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars(date('Y-m-d', strtotime('+7 days')), ENT_QUOTES, 'UTF-8') ?>" readonly>
                <p class="form-hint">Tidak dikirim dari form; backend menentukan tanggalnya.</p>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn-brand"><i class="fas fa-plus"></i> Catat Peminjaman</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading">
        <div>
            <h2>Riwayat Peminjaman</h2>
            <p class="form-hint">Menampilkan <?= $totalHistory ? $historyOffset + 1 : 0 ?>–<?= min($historyOffset + $historyPerPage, $totalHistory) ?> dari <?= $totalHistory ?> transaksi.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>No</th><th>Kode</th><th>Peminjam</th><th>Buku</th><th>Pinjam</th><th>Jatuh Tempo</th><th>Kembali</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($loans)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada riwayat peminjaman.</td></tr>
            <?php else: ?>
                <?php foreach ($loans as $index => $loan): ?>
                    <?php
                    $rowNo = $historyOffset + $index + 1;
                    $status = $loan['status'];
                    $statusLabel = match ($status) {
                        'dipinjam' => 'Dipinjam',
                        'dikembalikan' => 'Dikembalikan',
                        'terlambat' => 'Terlambat',
                        'ditolak' => 'Ditolak',
                        default => ucfirst($status),
                    };
                    ?>
                    <tr>
                        <td><?= $rowNo ?></td>
                        <td><?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['nama_peminjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['tanggal_pinjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['tanggal_kembali'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge-pill badge-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalHistoryPages > 1): ?>
        <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap p-3">
            <?php if ($historyPage > 1): ?>
                <a class="btn-outline" href="?page=<?= $historyPage - 1 ?>">« Sebelumnya</a>
            <?php endif; ?>

            <?php
            $startPage = max(1, $historyPage - 2);
            $endPage = min($totalHistoryPages, $historyPage + 2);
            for ($p = $startPage; $p <= $endPage; $p++):
            ?>
                <a class="<?= $p === $historyPage ? 'btn-brand' : 'btn-outline' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
            <?php endfor; ?>

            <?php if ($historyPage < $totalHistoryPages): ?>
                <a class="btn-outline" href="?page=<?= $historyPage + 1 ?>">Berikutnya »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
