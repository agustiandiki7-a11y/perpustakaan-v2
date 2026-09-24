<?php

$pageTitle = 'Peminjaman';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$today = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| DATA ANGGOTA
|--------------------------------------------------------------------------
*/

$members = $db->query("
    SELECT id, nama, username
    FROM users
    WHERE role = 'peminjam'
    ORDER BY nama ASC
")->fetchAll();

/*
|--------------------------------------------------------------------------
| BUKU TERSEDIA
|--------------------------------------------------------------------------
*/

$availableBooks = $db->query("
    SELECT
        id,
        judul,
        kode_buku,
        stok_tersedia
    FROM books
    WHERE status = 'aktif'
      AND stok_tersedia > 0
    ORDER BY judul ASC
")->fetchAll();

/*
|--------------------------------------------------------------------------
| PERMINTAAN PEMINJAMAN MENUNGGU
|--------------------------------------------------------------------------
*/

$stmtPending = $db->query("
    SELECT
        loans.id,
        loans.kode_peminjaman,
        loans.tanggal_pengajuan,
        loans.catatan,
        users.nama AS nama_peminjam,
        users.username,

        GROUP_CONCAT(
            CONCAT(
                books.judul,
                ' (',
                loan_details.jumlah,
                ' buku)'
            )
            ORDER BY books.judul
            SEPARATOR ', '
        ) AS judul_buku

    FROM loans

    INNER JOIN users
        ON users.id = loans.user_id

    INNER JOIN loan_details
        ON loan_details.loan_id = loans.id

    INNER JOIN books
        ON books.id = loan_details.book_id

    WHERE loans.status = 'menunggu'

    GROUP BY loans.id

    ORDER BY
        loans.tanggal_pengajuan ASC,
        loans.id ASC
");

$pendingLoans = $stmtPending->fetchAll();

/*
|--------------------------------------------------------------------------
| RIWAYAT PEMINJAMAN
|--------------------------------------------------------------------------
*/

$stmtHistory = $db->query("
    SELECT
        loans.*,

        users.nama AS nama_peminjam,

        GROUP_CONCAT(
            CONCAT(
                books.judul,
                ' (',
                loan_details.jumlah,
                ' buku)'
            )
            ORDER BY books.judul
            SEPARATOR ', '
        ) AS judul_buku

    FROM loans

    LEFT JOIN users
        ON users.id = loans.user_id

    LEFT JOIN loan_details
        ON loan_details.loan_id = loans.id

    LEFT JOIN books
        ON books.id = loan_details.book_id

    WHERE loans.status IN (
        'dipinjam',
        'dikembalikan',
        'terlambat',
        'ditolak'
    )

    GROUP BY loans.id

    ORDER BY loans.id DESC

    LIMIT 100
");

$loans = $stmtHistory->fetchAll();

?>

<!-- =========================================================
     REQUEST PEMINJAMAN
========================================================= -->

<div class="panel mb-4">

    <div class="panel-heading">

        <div>

            <h2>
                Permintaan Peminjaman
                <?php if (!empty($pendingLoans)): ?>
                    <span class="badge-pill badge-menunggu">
                        <?= count($pendingLoans) ?> Menunggu
                    </span>
                <?php endif; ?>
            </h2>

            <p class="form-hint">
                Pengajuan dari peminjam yang membutuhkan persetujuan admin atau petugas.
            </p>

        </div>

    </div>


    <div class="table-wrap">

        <table class="data-table">

            <thead>

                <tr>

                    <th>Kode</th>

                    <th>Peminjam</th>

                    <th>Buku</th>

                    <th>Tanggal Pengajuan</th>

                    <th>Catatan</th>

                    <th>Status</th>

                    <th>Aksi</th>

                </tr>

            </thead>


            <tbody>

            <?php if (empty($pendingLoans)): ?>

                <tr>

                    <td
                        colspan="7"
                        class="text-center text-muted py-4"
                    >

                        <i
                            class="fas fa-inbox"
                            style="font-size:2rem;opacity:.35;"
                        ></i>

                        <div style="margin-top:.5rem;">
                            Belum ada permintaan peminjaman.
                        </div>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($pendingLoans as $loan): ?>

                    <tr>

                        <!-- KODE -->

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $loan['kode_peminjaman'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                        </td>


                        <!-- PEMINJAM -->

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $loan['nama_peminjam'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                            <br>

                            <small class="text-muted">

                                @<?= htmlspecialchars(
                                    $loan['username'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </small>

                        </td>


                        <!-- BUKU -->

                        <td>

                            <?= htmlspecialchars(
                                $loan['judul_buku'] ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <!-- TANGGAL -->

                        <td>

                            <?= htmlspecialchars(
                                $loan['tanggal_pengajuan'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <!-- CATATAN -->

                        <td>

                            <?= htmlspecialchars(
                                $loan['catatan'] ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span class="badge-pill badge-menunggu">

                                Menunggu

                            </span>

                        </td>


                        <!-- AKSI -->

                        <td>

                            <div
                                style="
                                    display:flex;
                                    gap:.5rem;
                                    flex-wrap:wrap;
                                "
                            >

                                <!-- SETUJUI -->

                                <form
                                    action="proses.php"
                                    method="POST"
                                    onsubmit="return confirm(
                                        'Setujui peminjaman <?= htmlspecialchars(
                                            $loan['kode_peminjaman'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>? Stok buku akan dikurangi.'
                                    );"
                                >

                                    <?= csrfField() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="konfirmasi"
                                    >

                                    <input
                                        type="hidden"
                                        name="loan_id"
                                        value="<?= (int) $loan['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-brand"
                                    >

                                        <i class="fas fa-check"></i>

                                        Setujui

                                    </button>

                                </form>


                                <!-- TOLAK -->

                                <form
                                    action="proses.php"
                                    method="POST"
                                    onsubmit="return confirm(
                                        'Tolak peminjaman <?= htmlspecialchars(
                                            $loan['kode_peminjaman'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>?'
                                    );"
                                >

                                    <?= csrfField() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="tolak"
                                    >

                                    <input
                                        type="hidden"
                                        name="loan_id"
                                        value="<?= (int) $loan['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-danger-outline"
                                    >

                                        <i class="fas fa-xmark"></i>

                                        Tolak

                                    </button>

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


<!-- =========================================================
     PEMINJAMAN MANUAL
========================================================= -->

<div class="panel mb-4">

    <div class="panel-heading">

        <div>

            <h2>
                Catat Peminjaman Manual
            </h2>

            <p class="form-hint">
                Digunakan admin atau petugas untuk transaksi langsung di perpustakaan.
            </p>

        </div>

    </div>


    <form
        action="proses.php"
        method="POST"
    >

        <?= csrfField() ?>

        <input
            type="hidden"
            name="action"
            value="create"
        >


        <div class="row g-3">


            <!-- PEMINJAM -->

            <div class="col-md-3">

                <label class="form-label">
                    Peminjam
                </label>

                <select
                    name="user_id"
                    class="form-control"
                    required
                >

                    <option value="">
                        -- Pilih Anggota --
                    </option>

                    <?php foreach ($members as $member): ?>

                        <option
                            value="<?= (int) $member['id'] ?>"
                        >

                            <?= htmlspecialchars(
                                $member['nama'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            (
                            <?= htmlspecialchars(
                                $member['username'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                            )

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- BUKU -->

            <div class="col-md-3">

                <label class="form-label">
                    Buku
                </label>

                <select
                    name="book_id"
                    class="form-control"
                    required
                >

                    <option value="">
                        -- Pilih Buku --
                    </option>

                    <?php foreach ($availableBooks as $book): ?>

                        <option
                            value="<?= (int) $book['id'] ?>"
                        >

                            <?= htmlspecialchars(
                                $book['judul'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            — stok
                            <?= (int) $book['stok_tersedia'] ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- TANGGAL PINJAM -->

            <div class="col-md-2">

                <label class="form-label">
                    Tanggal Pinjam
                </label>

                <input
                    type="date"
                    name="tanggal_pinjam"
                    id="tanggalPinjam"
                    class="form-control"
                    value="<?= $today ?>"
                    min="<?= $today ?>"
                    required
                >

            </div>


            <!-- JATUH TEMPO -->

            <div class="col-md-2">

                <label class="form-label">
                    Jatuh Tempo
                </label>

                <input
                    type="date"
                    name="tanggal_jatuh_tempo"
                    id="tanggalKembali"
                    class="form-control"
                    value="<?= date(
                        'Y-m-d',
                        strtotime('+7 days')
                    ) ?>"
                    min="<?= $today ?>"
                    max="<?= date(
                        'Y-m-d',
                        strtotime('+7 days')
                    ) ?>"
                    required
                >

            </div>


            <!-- CATATAN -->

            <div class="col-md-2">

                <label class="form-label">
                    Catatan
                </label>

                <input
                    type="text"
                    name="catatan"
                    class="form-control"
                    maxlength="255"
                >

            </div>

        </div>


        <div class="mt-3">

            <button
                type="submit"
                class="btn-brand"
            >

                <i class="fas fa-plus"></i>

                Catat Peminjaman

            </button>

        </div>

    </form>

</div>


<!-- =========================================================
     RIWAYAT
========================================================= -->

<div class="panel">

    <div class="panel-heading">

        <div>

            <h2>
                Riwayat Peminjaman
                (<?= count($loans) ?>)
            </h2>

            <p class="form-hint">
                Riwayat transaksi peminjaman terbaru.
            </p>

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

            <?php if (empty($loans)): ?>

                <tr>

                    <td
                        colspan="7"
                        class="text-center text-muted py-4"
                    >

                        Belum ada riwayat peminjaman.

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($loans as $loan): ?>

                    <?php

                    $status = $loan['status'];

                    $statusLabel = match ($status) {

                        'dipinjam'
                            => 'Dipinjam',

                        'dikembalikan'
                            => 'Dikembalikan',

                        'terlambat'
                            => 'Terlambat',

                        'ditolak'
                            => 'Ditolak',

                        default
                            => ucfirst($status),

                    };

                    ?>

                    <tr>

                        <td>

                            <?= htmlspecialchars(
                                $loan['kode_peminjaman'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $loan['nama_peminjam'] ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $loan['judul_buku'] ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $loan['tanggal_pinjam'] ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $loan['tanggal_jatuh_tempo'] ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $loan['tanggal_kembali'] ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <span
                                class="badge-pill badge-<?= htmlspecialchars(
                                    $status,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    $statusLabel,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

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

    const tanggalPinjam =
        document.getElementById('tanggalPinjam');

    const tanggalKembali =
        document.getElementById('tanggalKembali');

    if (!tanggalPinjam || !tanggalKembali) {
        return;
    }


    function formatTanggal(date) {

        const year =
            date.getFullYear();

        const month =
            String(date.getMonth() + 1)
                .padStart(2, '0');

        const day =
            String(date.getDate())
                .padStart(2, '0');

        return `${year}-${month}-${day}`;
    }


    function updateTanggalKembali() {

        if (!tanggalPinjam.value) {
            return;
        }

        const pinjam =
            new Date(
                tanggalPinjam.value + 'T00:00:00'
            );

        if (Number.isNaN(pinjam.getTime())) {
            return;
        }


        const maksimal =
            new Date(pinjam);

        maksimal.setDate(
            maksimal.getDate() + 7
        );


        const min =
            formatTanggal(pinjam);

        const max =
            formatTanggal(maksimal);


        tanggalKembali.min = min;
        tanggalKembali.max = max;


        if (
            tanggalKembali.value < min ||
            tanggalKembali.value > max
        ) {

            tanggalKembali.value = max;

        }

    }


    tanggalPinjam.addEventListener(
        'change',
        updateTanggalKembali
    );


    updateTanggalKembali();

})();

</script>