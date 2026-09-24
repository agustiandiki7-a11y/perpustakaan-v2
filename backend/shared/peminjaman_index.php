<?php
$pageTitle = 'Peminjaman';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

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

$pendingStmt = $db->query("
    SELECT
        loans.id,
        loans.kode_peminjaman,
        loans.tanggal_pengajuan,
        loans.catatan,
        users.nama AS nama_peminjam,
        GROUP_CONCAT(
            CONCAT(books.judul, ' (', loan_details.jumlah, ')')
            ORDER BY books.judul
            SEPARATOR ', '
        ) AS judul_buku
    FROM loans
    INNER JOIN users ON users.id = loans.user_id
    INNER JOIN loan_details ON loan_details.loan_id = loans.id
    INNER JOIN books ON books.id = loan_details.book_id
    WHERE loans.status = 'menunggu'
    GROUP BY loans.id
    ORDER BY loans.tanggal_pengajuan ASC, loans.id ASC
");
$pendingLoans = $pendingStmt->fetchAll();

$historyStmt = $db->query("
    SELECT
        loans.*,
        users.nama AS nama_peminjam,
        GROUP_CONCAT(
            CONCAT(books.judul, ' (', loan_details.jumlah, ')')
            ORDER BY books.judul
            SEPARATOR ', '
        ) AS judul_buku
    FROM loans
    LEFT JOIN users ON users.id = loans.user_id
    LEFT JOIN loan_details ON loan_details.loan_id = loans.id
    LEFT JOIN books ON books.id = loan_details.book_id
    WHERE loans.status IN ('dipinjam', 'dikembalikan', 'terlambat', 'ditolak')
    GROUP BY loans.id
    ORDER BY loans.id DESC
    LIMIT 100
");
$loans = $historyStmt->fetchAll();

$today = date('Y-m-d');
?>

<?php if ($pendingLoans): ?>
<div class="panel mb-4">
    <div class="panel-heading">
        <div>
            <h2>Pengajuan Menunggu Konfirmasi (<?= count($pendingLoans) ?>)</h2>
            <p class="form-hint">Pengajuan dari halaman anggota belum mengurangi stok sampai dikonfirmasi.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Peminjam</th>
                    <th>Buku</th>
                    <th>Diajukan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingLoans as $loan): ?>
                <tr>
                    <td><?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($loan['nama_peminjam'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($loan['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($loan['tanggal_pengajuan'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                            <form action="proses.php" method="POST" onsubmit="return confirm('Konfirmasi pengajuan <?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?>? Stok buku akan dikurangi.');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="konfirmasi">
                                <input type="hidden" name="loan_id" value="<?= (int) $loan['id'] ?>">
                                <button type="submit" class="btn-brand">
                                    <i class="fas fa-check"></i> Konfirmasi
                                </button>
                            </form>
                            <form action="proses.php" method="POST" onsubmit="return confirm('Tolak pengajuan <?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?>?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="tolak">
                                <input type="hidden" name="loan_id" value="<?= (int) $loan['id'] ?>">
                                <button type="submit" class="btn-danger-outline">
                                    <i class="fas fa-xmark"></i> Tolak
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="panel mb-4">
    <div class="panel-heading">
        <div>
            <h2>Catat Peminjaman Manual</h2>
            <p class="form-hint">Dipakai petugas/admin saat buku dipinjam langsung di perpustakaan.</p>
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
                    <?php foreach ($members as $m): ?>
                        <option value="<?= (int) $m['id'] ?>">
                            <?= htmlspecialchars($m['nama'], ENT_QUOTES, 'UTF-8') ?>
                            (<?= htmlspecialchars($m['username'], ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Buku</label>
                <select name="book_id" class="form-control" required>
                    <option value="">-- Pilih Buku --</option>
                    <?php foreach ($availableBooks as $b): ?>
                        <option value="<?= (int) $b['id'] ?>">
                            <?= htmlspecialchars($b['judul'], ENT_QUOTES, 'UTF-8') ?>
                            (stok: <?= (int) $b['stok_tersedia'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Tgl Pinjam</label>
                <input
                    type="date"
                    id="tanggalPinjam"
                    name="tanggal_pinjam"
                    class="form-control"
                    required
                    min="<?= $today ?>"
                    value="<?= $today ?>"
                >
            </div>

            <div class="col-md-2">
                <label class="form-label">Jatuh Tempo</label>
                <input
                    type="date"
                    id="tanggalKembali"
                    name="tanggal_jatuh_tempo"
                    class="form-control"
                    required
                    min="<?= $today ?>"
                    max="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                    value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                >
            </div>

            <div class="col-md-2">
                <label class="form-label">Catatan</label>
                <input type="text" name="catatan" class="form-control" maxlength="255">
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn-brand">
                <i class="fas fa-plus"></i> Catat Peminjaman
            </button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading">
        <div>
            <h2>Riwayat Peminjaman (<?= count($loans) ?>)</h2>
            <p class="form-hint">Maksimal 100 data terbaru ditampilkan.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
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
            <?php if (!$loans): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Belum ada peminjaman.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($loans as $loan): ?>
                    <tr>
                        <td><?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['nama_peminjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['tanggal_pinjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($loan['tanggal_kembali'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php
                            $status = $loan['status'];
                            $label = match ($status) {
                                'menunggu' => 'Menunggu',
                                'ditolak' => 'Ditolak',
                                'dipinjam' => 'Dipinjam',
                                'dikembalikan' => 'Dikembalikan',
                                'terlambat' => 'Terlambat',
                                default => ucfirst($status),
                            };
                            ?>
                            <span class="badge-pill badge-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const tglPinjam = document.getElementById('tanggalPinjam');
    const tglKembali = document.getElementById('tanggalKembali');
    if (!tglPinjam || !tglKembali) return;

    function addDays(dateString, days) {
        const date = new Date(dateString + 'T00:00:00');
        date.setDate(date.getDate() + days);
        return date.toISOString().slice(0, 10);
    }

    function syncKembaliRange() {
        if (!tglPinjam.value) return;

        const min = tglPinjam.value;
        const max = addDays(min, 7);

        tglKembali.min = min;
        tglKembali.max = max;

        if (!tglKembali.value || tglKembali.value < min || tglKembali.value > max) {
            tglKembali.value = max;
        }
    }

    tglPinjam.addEventListener('change', syncKembaliRange);
    syncKembaliRange();
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
