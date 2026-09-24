<?php
$pageTitle = 'Peminjaman';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$members = $db->query("SELECT id, nama, username FROM users WHERE role = 'peminjam' ORDER BY nama ASC")->fetchAll();
$availableBooks = $db->query("SELECT id, judul, kode_buku, stok_tersedia FROM books WHERE status = 'aktif' AND stok_tersedia > 0 ORDER BY judul ASC")->fetchAll();

$stmt = $db->query("
    SELECT loans.*, users.nama AS nama_peminjam,
           GROUP_CONCAT(books.judul SEPARATOR ', ') AS judul_buku
    FROM loans
    LEFT JOIN users ON users.id = loans.user_id
    LEFT JOIN loan_details ON loan_details.loan_id = loans.id
    LEFT JOIN books ON books.id = loan_details.book_id
    GROUP BY loans.id
    ORDER BY loans.id DESC
");
$loans = $stmt->fetchAll();
?>

<div class="panel mb-4">
    <div class="panel-heading"><h2>Catat Peminjaman Baru</h2></div>
    <form action="proses.php" method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Peminjam</label>
                <select name="user_id" class="form-control" required>
                    <option value="">-- Pilih Anggota --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"><?= htmlspecialchars($m['nama'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($m['username'], ENT_QUOTES, 'UTF-8') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Buku</label>
                <select name="book_id" class="form-control" required>
                    <option value="">-- Pilih Buku --</option>
                    <?php foreach ($availableBooks as $b): ?>
                        <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['judul'], ENT_QUOTES, 'UTF-8') ?> (stok: <?= (int) $b['stok_tersedia'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tgl Pinjam</label>
                <input type="date" name="tanggal_pinjam" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Jatuh Tempo</label>
                <input type="date" name="tanggal_jatuh_tempo" class="form-control" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Catatan</label>
                <input type="text" name="catatan" class="form-control">
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn-brand"><i class="fas fa-plus"></i> Catat Peminjaman</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading"><h2>Riwayat Peminjaman (<?= count($loans) ?>)</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Kode</th><th>Peminjam</th><th>Buku</th><th>Pinjam</th><th>Jatuh Tempo</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (empty($loans)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada peminjaman.</td></tr>
                <?php else: ?>
                    <?php foreach ($loans as $loan): ?>
                        <tr>
                            <td><?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['nama_peminjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['tanggal_pinjam'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge-pill badge-<?= htmlspecialchars($loan['status'], ENT_QUOTES, 'UTF-8') ?>"><?= ucfirst($loan['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
