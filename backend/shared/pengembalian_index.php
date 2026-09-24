<?php
$pageTitle = 'Pengembalian';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$dendaPerHari = 2000; // Rp per hari keterlambatan

$stmt = $db->query("
    SELECT loans.*, users.nama AS nama_peminjam,
           GROUP_CONCAT(books.judul SEPARATOR ', ') AS judul_buku
    FROM loans
    LEFT JOIN users ON users.id = loans.user_id
    LEFT JOIN loan_details ON loan_details.loan_id = loans.id
    LEFT JOIN books ON books.id = loan_details.book_id
    WHERE loans.status = 'dipinjam'
    GROUP BY loans.id
    ORDER BY loans.tanggal_jatuh_tempo ASC
");
$activeLoans = $stmt->fetchAll();
$today = new DateTime('today');

// Riwayat: buku yang sudah pernah dipinjam & sudah kembali (tepat waktu ataupun telat).
$stmtRiwayat = $db->query("
    SELECT loans.*, users.nama AS nama_peminjam,
           GROUP_CONCAT(books.judul SEPARATOR ', ') AS judul_buku
    FROM loans
    LEFT JOIN users ON users.id = loans.user_id
    LEFT JOIN loan_details ON loan_details.loan_id = loans.id
    LEFT JOIN books ON books.id = loan_details.book_id
    WHERE loans.status IN ('dikembalikan', 'terlambat')
    GROUP BY loans.id
    ORDER BY loans.tanggal_kembali DESC, loans.id DESC
    LIMIT 100
");
$riwayatLoans = $stmtRiwayat->fetchAll();
?>

<div class="panel">
    <div class="panel-heading">
        <h2>Peminjaman Aktif (<?= count($activeLoans) ?>)</h2>
        <span class="form-hint">Denda keterlambatan: Rp<?= number_format($dendaPerHari) ?>/hari</span>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Kode</th><th>Peminjam</th><th>Buku</th><th>Batas Kembali</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php if (empty($activeLoans)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Gak ada peminjaman aktif saat ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($activeLoans as $loan): ?>
                        <?php
                        $jatuhTempo = new DateTime($loan['tanggal_jatuh_tempo']);
                        $telat = $today > $jatuhTempo;
                        $hariTelat = $telat ? $today->diff($jatuhTempo)->days : 0;
                        $estimasiDenda = $hariTelat * $dendaPerHari;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['nama_peminjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($telat): ?>
                                    <span class="badge-pill badge-terlambat">Telat <?= $hariTelat ?> hari</span>
                                <?php else: ?>
                                    <span class="badge-pill badge-dipinjam">Dipinjam</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="proses.php" method="POST" onsubmit="return confirm('Proses pengembalian buku ini<?= $telat ? '? Denda estimasi Rp' . number_format($estimasiDenda) : '' ?>?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $loan['id'] ?>">
                                    <button type="submit" class="btn-brand"><i class="fas fa-check"></i> Kembalikan</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel mt-4">
    <div class="panel-heading"><h2>Riwayat Peminjaman &amp; Pengembalian (<?= count($riwayatLoans) ?>)</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Kode</th><th>Peminjam</th><th>Buku</th><th>Batas Kembali</th><th>Tgl Kembali</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (empty($riwayatLoans)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada riwayat pengembalian.</td></tr>
                <?php else: ?>
                    <?php foreach ($riwayatLoans as $loan): ?>
                        <tr>
                            <td><?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['nama_peminjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['tanggal_kembali'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge-pill badge-<?= htmlspecialchars($loan['status'], ENT_QUOTES, 'UTF-8') ?>"><?= ucfirst($loan['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
