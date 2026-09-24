<?php
$pageTitle = 'Laporan Peminjaman';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$mulai = trim($_GET['mulai'] ?? '');
$selesai = trim($_GET['selesai'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = [];
$params = [];

if ($mulai !== '') {
    $where[] = 'loans.tanggal_pinjam >= :mulai';
    $params[':mulai'] = $mulai;
}
if ($selesai !== '') {
    $where[] = 'loans.tanggal_pinjam <= :selesai';
    $params[':selesai'] = $selesai;
}
if (in_array($status, ['dipinjam', 'dikembalikan', 'terlambat'], true)) {
    $where[] = 'loans.status = :status';
    $params[':status'] = $status;
}

$sql = "SELECT loans.kode_peminjaman, loans.tanggal_pinjam, loans.tanggal_jatuh_tempo,
               loans.tanggal_kembali, loans.status, loans.catatan,
               users.nama AS nama_peminjam,
               GROUP_CONCAT(books.judul ORDER BY books.judul SEPARATOR ', ') AS judul_buku
        FROM loans
        LEFT JOIN users ON users.id = loans.user_id
        LEFT JOIN loan_details ON loan_details.loan_id = loans.id
        LEFT JOIN books ON books.id = loan_details.book_id";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' GROUP BY loans.id ORDER BY loans.tanggal_pinjam DESC, loans.id DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$total = count($rows);
$aktif = 0;
$kembali = 0;
$terlambat = 0;
foreach ($rows as $row) {
    if ($row['status'] === 'dipinjam') $aktif++;
    if ($row['status'] === 'dikembalikan') $kembali++;
    if ($row['status'] === 'terlambat') $terlambat++;
}
?>

<div class="report-toolbar no-print">
    <div>
        <p class="report-kicker">Laporan perpustakaan</p>
        <h2>Riwayat Peminjaman</h2>
        <p class="report-description">Gunakan filter untuk menyusun laporan sesuai periode dan status, lalu cetak atau simpan sebagai PDF.</p>
    </div>
    <div class="report-actions">
        <button type="button" class="btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Cetak / PDF</button>
        <a class="btn-brand" href="<?= htmlspecialchars($backendBase, ENT_QUOTES, 'UTF-8') ?>/laporan/index.php"><i class="fas fa-rotate-left"></i> Reset</a>
    </div>
</div>

<div class="panel no-print mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="mulai">Dari tanggal</label>
            <input class="form-control" type="date" id="mulai" name="mulai" value="<?= htmlspecialchars($mulai, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="selesai">Sampai tanggal</label>
            <input class="form-control" type="date" id="selesai" name="selesai" value="<?= htmlspecialchars($selesai, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="status">Status</label>
            <select class="form-control" id="status" name="status">
                <option value="">Semua status</option>
                <option value="dipinjam" <?= $status === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                <option value="dikembalikan" <?= $status === 'dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
                <option value="terlambat" <?= $status === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn-brand w-100" type="submit"><i class="fas fa-filter"></i> Tampilkan Laporan</button>
        </div>
    </form>
</div>

<div class="report-sheet">
    <div class="report-header">
        <div class="report-brand"><i class="fas fa-book-open"></i><span>Perpustakaan Digital</span></div>
        <div class="report-meta">
            <strong>LAPORAN PEMINJAMAN</strong>
            <span>Dicetak: <?= date('d/m/Y H:i') ?></span>
            <span>Petugas: <?= htmlspecialchars($me['nama'] ?: $me['username'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <div class="report-summary">
        <div><span>Total</span><strong><?= $total ?></strong></div>
        <div><span>Dipinjam</span><strong><?= $aktif ?></strong></div>
        <div><span>Dikembalikan</span><strong><?= $kembali ?></strong></div>
        <div><span>Terlambat</span><strong><?= $terlambat ?></strong></div>
    </div>

    <div class="table-wrap">
        <table class="data-table report-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Peminjam</th>
                    <th>Buku</th>
                    <th>Pinjam</th>
                    <th>Jatuh Tempo</th>
                    <th>Kembali</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="text-center py-4">Tidak ada data untuk filter yang dipilih.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $row): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($row['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['nama_peminjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['tanggal_pinjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['tanggal_jatuh_tempo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['tanggal_kembali'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge-pill badge-<?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($row['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="report-signature">
        <div></div>
        <div>
            <p><?= htmlspecialchars($me['nama'] ?: $me['username'], ENT_QUOTES, 'UTF-8') ?></p>
            <span><?= htmlspecialchars(ucfirst($me['role']), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
