<?php
$pageTitle = 'Pengembalian';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$dendaPerHari = 2000;

$stmt = $db->query("
    SELECT loans.*, users.nama AS nama_peminjam,
           GROUP_CONCAT(CONCAT(books.judul, ' (', loan_details.jumlah, ' buku)') ORDER BY books.judul SEPARATOR ', ') AS judul_buku
    FROM loans
    LEFT JOIN users ON users.id = loans.user_id
    LEFT JOIN loan_details ON loan_details.loan_id = loans.id
    LEFT JOIN books ON books.id = loan_details.book_id
    WHERE loans.status = 'dipinjam'
    GROUP BY loans.id
    ORDER BY loans.tanggal_jatuh_tempo ASC, loans.id ASC
");
$activeLoans = $stmt->fetchAll();
$today = new DateTime('today');
?>

<div class="panel">
    <div class="panel-heading">
        <div>
            <h2>Peminjaman Belum Dikembalikan (<?= count($activeLoans) ?>)</h2>
            <p class="form-hint">Pengembalian wajib dicatat dengan kondisi buku. Bukti foto dapat digunakan sebagai bukti serah-terima.</p>
        </div>
        <span class="form-hint">Denda keterlambatan: Rp<?= number_format($dendaPerHari, 0, ',', '.') ?>/hari</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>No</th><th>Kode</th><th>Peminjam</th><th>Buku</th><th>Jatuh Tempo</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php if (empty($activeLoans)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada buku yang sedang dipinjam.</td></tr>
            <?php else: ?>
                <?php foreach ($activeLoans as $i => $loan): ?>
                    <?php
                    $jatuhTempo = new DateTime($loan['tanggal_jatuh_tempo']);
                    $telat = $today > $jatuhTempo;
                    $hariTelat = $telat ? $today->diff($jatuhTempo)->days : 0;
                    $estimasiDenda = $hariTelat * $dendaPerHari;
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['nama_peminjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($telat): ?>
                                <span class="badge-pill badge-terlambat">Telat <?= $hariTelat ?> hari · Rp<?= number_format($estimasiDenda, 0, ',', '.') ?></span>
                            <?php else: ?>
                                <span class="badge-pill badge-dipinjam">Dipinjam</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="index.php?return=<?= (int) $loan['id'] ?>" class="btn-brand">
                                <i class="fas fa-rotate-left"></i> Proses Kembali
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$returnId = filter_input(INPUT_GET, 'return', FILTER_VALIDATE_INT);
$returnLoan = null;

if ($returnId) {
    $stmtReturn = $db->prepare("
        SELECT loans.*, users.nama AS nama_peminjam,
               GROUP_CONCAT(CONCAT(books.judul, ' (', loan_details.jumlah, ' buku)') ORDER BY books.judul SEPARATOR ', ') AS judul_buku
        FROM loans
        LEFT JOIN users ON users.id = loans.user_id
        LEFT JOIN loan_details ON loan_details.loan_id = loans.id
        LEFT JOIN books ON books.id = loan_details.book_id
        WHERE loans.id = ? AND loans.status = 'dipinjam'
        GROUP BY loans.id
        LIMIT 1
    ");
    $stmtReturn->execute([$returnId]);
    $returnLoan = $stmtReturn->fetch() ?: null;
}

if ($returnId && !$returnLoan):
?>
    <div class="alert-flash alert-error mt-4">
        <i class="fas fa-circle-exclamation"></i> Data pengembalian tidak ditemukan atau sudah diproses.
    </div>
<?php endif; ?>

<?php if ($returnLoan): ?>
    <?php
    $due = new DateTime($returnLoan['tanggal_jatuh_tempo']);
    $hariTelatForm = $today > $due ? $today->diff($due)->days : 0;
    $dendaTelatForm = $hariTelatForm * $dendaPerHari;
    ?>
    <div class="panel mt-4">
        <div class="panel-heading">
            <div>
                <h2>Proses Pengembalian — <?= htmlspecialchars($returnLoan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="form-hint"><?= htmlspecialchars($returnLoan['nama_peminjam'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($returnLoan['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <form action="proses.php" method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int) $returnLoan['id'] ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tanggal Kembali</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($today->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" readonly>
                    <p class="form-hint">Tanggal dibuat otomatis oleh sistem.</p>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Kondisi Buku</label>
                    <select name="kondisi_buku" class="form-control" required>
                        <option value="baik">Baik</option>
                        <option value="rusak_ringan">Rusak Ringan</option>
                        <option value="rusak_berat">Rusak Berat</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Denda Keterlambatan</label>
                    <input type="text" class="form-control" value="Rp<?= number_format($dendaTelatForm, 0, ',', '.') ?>" readonly>
                    <input type="hidden" name="denda_keterlambatan" value="<?= (int) $dendaTelatForm ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Denda Kerusakan</label>
                    <input type="number" name="denda_kerusakan" class="form-control" min="0" step="1000" value="0">
                    <p class="form-hint">Isi 0 jika buku dalam kondisi baik atau tidak dikenakan denda kerusakan.</p>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Bukti Foto Pengembalian</label>
                    <input type="file" name="bukti_pengembalian" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
                    <p class="form-hint">Wajib untuk dokumentasi serah-terima. Maksimal 5MB.</p>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn-brand" onclick="return confirm('Konfirmasi pengembalian buku ini?');">
                    <i class="fas fa-check"></i> Konfirmasi Pengembalian
                </button>
                <a href="index.php" class="btn-outline">Batal</a>
            </div>
        </form>
    </div>
<?php endif; ?>


<?php
$unpaidFines = $db->query("
    SELECT loans.id, loans.kode_peminjaman, loans.tanggal_kembali,
           loans.kondisi_buku, loans.denda_keterlambatan, loans.denda_kerusakan, loans.denda_total,
           users.nama AS nama_peminjam
    FROM loans
    INNER JOIN users ON users.id = loans.user_id
    WHERE loans.denda_total > 0 AND loans.denda_dibayar = 0
    ORDER BY loans.tanggal_kembali ASC, loans.id ASC
")->fetchAll();

$returnHistory = $db->query("
    SELECT loans.id, loans.kode_peminjaman, loans.tanggal_kembali, loans.kondisi_buku,
           loans.denda_total, loans.denda_dibayar, loans.bukti_pengembalian,
           users.nama AS nama_peminjam,
           GROUP_CONCAT(books.judul ORDER BY books.judul SEPARATOR ', ') AS judul_buku
    FROM loans
    INNER JOIN users ON users.id = loans.user_id
    LEFT JOIN loan_details ON loan_details.loan_id = loans.id
    LEFT JOIN books ON books.id = loan_details.book_id
    WHERE loans.status IN ('dikembalikan', 'terlambat')
    GROUP BY loans.id
    ORDER BY loans.tanggal_kembali DESC, loans.id DESC
    LIMIT 20
")->fetchAll();
?>

<div class="panel mt-4">
    <div class="panel-heading">
        <div>
            <h2>Denda Belum Lunas (<?= count($unpaidFines) ?>)</h2>
            <p class="form-hint">Peminjam dengan denda yang belum lunas akan diblokir dari pengajuan peminjaman baru.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>No</th><th>Kode</th><th>Peminjam</th><th>Denda Keterlambatan</th><th>Denda Kerusakan</th><th>Total</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (empty($unpaidFines)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada denda yang belum lunas.</td></tr>
            <?php else: ?>
                <?php foreach ($unpaidFines as $i => $fine): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($fine['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($fine['nama_peminjam'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>Rp<?= number_format((float) $fine['denda_keterlambatan'], 0, ',', '.') ?></td>
                        <td>Rp<?= number_format((float) $fine['denda_kerusakan'], 0, ',', '.') ?></td>
                        <td><strong>Rp<?= number_format((float) $fine['denda_total'], 0, ',', '.') ?></strong></td>
                        <td>
                            <form action="proses.php" method="POST" onsubmit="return confirm('Tandai denda ini sebagai lunas?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="bayar_denda">
                                <input type="hidden" name="loan_id" value="<?= (int) $fine['id'] ?>">
                                <button type="submit" class="btn-brand"><i class="fas fa-check"></i> Tandai Lunas</button>
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
    <div class="panel-heading">
        <div>
            <h2>Riwayat Pengembalian Terbaru</h2>
            <p class="form-hint">Bukti foto, kondisi buku, dan status denda tersimpan pada transaksi.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>No</th><th>Kode</th><th>Peminjam</th><th>Buku</th><th>Tgl Kembali</th><th>Kondisi</th><th>Denda</th><th>Bukti</th></tr></thead>
            <tbody>
            <?php if (empty($returnHistory)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada riwayat pengembalian.</td></tr>
            <?php else: ?>
                <?php foreach ($returnHistory as $i => $history): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($history['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($history['nama_peminjam'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($history['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($history['tanggal_kembali'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $history['kondisi_buku'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            Rp<?= number_format((float) $history['denda_total'], 0, ',', '.') ?>
                            <?php if ((int) $history['denda_total'] > 0): ?>
                                <br><small class="text-muted"><?= (int) $history['denda_dibayar'] === 1 ? 'Lunas' : 'Belum lunas' ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($history['bukti_pengembalian'])): ?>
                                <a href="<?= baseUrlPath() . '/' . htmlspecialchars($history['bukti_pengembalian'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Lihat foto</a>
                            <?php else: ?>
                                -
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
