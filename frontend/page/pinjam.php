<?php

require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();
cekAksesPeminjam();

if (!sudahLogin()) {
    setFlash('error', 'Silakan masuk dulu buat mengajukan peminjaman.');
    header('Location: ../../login/pages/login.php');
    exit;
}

$me = currentUser();

if (($me['role'] ?? '') !== 'peminjam') {
    http_response_code(403);
    exit('Akses ditolak.');
}

$db = (new Database())->connect();

$bookId = (int) ($_GET['id'] ?? 0);

if ($bookId <= 0) {
    setFlash('error', 'Buku tidak ditemukan.');
    header('Location: ../../index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil data buku
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        books.*,
        categories.nama_kategori
    FROM books
    LEFT JOIN categories
        ON categories.id = books.category_id
    WHERE books.id = ?
      AND books.status = 'aktif'
    LIMIT 1
");

$stmt->execute([$bookId]);

$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    setFlash(
        'error',
        'Buku tidak ditemukan atau sudah tidak aktif.'
    );

    header('Location: ../../index.php');
    exit;
}

$stokTersedia = (int) $book['stok_tersedia'];

$flash = getFlash();

$tanggalPengajuan = date('Y-m-d');
$tanggalJatuhTempo = date(
    'Y-m-d',
    strtotime('+7 days')
);

?>
<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Ajukan Peminjaman - Perpustakaan Digital
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        :root {
            --green: #198754;
            --green-dark: #146c43;
            --green-soft: #eaf7ef;
            --green-light: #f3fbf6;

            --text: #17221b;
            --muted: #6c757d;

            --bg: #f5f7f6;
            --white: #ffffff;

            --border: #e3e9e5;

            --danger: #dc3545;
            --danger-soft: #fff0f1;

            --shadow:
                0 18px 50px rgba(25, 135, 84, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;

            font-family: 'Inter', sans-serif;

            color: var(--text);

            background:
                radial-gradient(
                    circle at top right,
                    rgba(25, 135, 84, 0.08),
                    transparent 35%
                ),
                var(--bg);
        }

        a {
            text-decoration: none;
        }

        .page {
            width: 100%;
            max-width: 760px;

            margin: 0 auto;

            padding:
                35px 20px
                60px;
        }

        /*
        |--------------------------------------------------------------------------
        | Back
        |--------------------------------------------------------------------------
        */

        .back-link {
            display: inline-flex;

            align-items: center;

            gap: 9px;

            color: var(--muted);

            font-size: 14px;

            font-weight: 600;

            margin-bottom: 20px;

            transition: 0.2s;
        }

        .back-link:hover {
            color: var(--green);
        }

        /*
        |--------------------------------------------------------------------------
        | Main Card
        |--------------------------------------------------------------------------
        */

        .card {
            background: var(--white);

            border:
                1px solid var(--border);

            border-radius: 20px;

            box-shadow: var(--shadow);

            overflow: hidden;
        }

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .card-header {
            padding: 28px 30px 24px;

            border-bottom:
                1px solid var(--border);
        }

        .header-icon {
            width: 48px;
            height: 48px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: var(--green-soft);

            color: var(--green);

            border-radius: 13px;

            font-size: 20px;

            margin-bottom: 15px;
        }

        .card-header h1 {
            margin: 0 0 7px;

            font-family: 'Poppins', sans-serif;

            font-size: 23px;

            line-height: 1.35;
        }

        .card-header p {
            margin: 0;

            color: var(--muted);

            font-size: 14px;

            line-height: 1.6;
        }

        /*
        |--------------------------------------------------------------------------
        | Book
        |--------------------------------------------------------------------------
        */

        .book-box {
            margin: 25px 30px;

            padding: 20px;

            display: flex;

            gap: 17px;

            background: var(--green-light);

            border:
                1px solid #dcefe3;

            border-radius: 15px;
        }

        .book-icon {
            flex: 0 0 55px;

            width: 55px;
            height: 68px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: var(--green);

            color: white;

            border-radius: 9px;

            font-size: 23px;

            box-shadow:
                0 8px 20px
                rgba(25, 135, 84, 0.18);
        }

        .book-info {
            min-width: 0;
        }

        .book-title {
            margin: 0 0 6px;

            font-size: 17px;

            font-weight: 700;

            line-height: 1.4;
        }

        .book-author {
            margin: 0 0 10px;

            color: var(--muted);

            font-size: 13px;
        }

        .book-details {
            display: flex;

            flex-wrap: wrap;

            gap: 7px;
        }

        .book-badge {
            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding: 6px 9px;

            border-radius: 7px;

            background: white;

            border:
                1px solid #dce9e1;

            color: #526057;

            font-size: 11px;

            font-weight: 600;
        }

        .book-badge.stock {
            color: var(--green);
        }

        /*
        |--------------------------------------------------------------------------
        | Alert
        |--------------------------------------------------------------------------
        */

        .alert {
            margin:
                0 30px
                20px;

            padding: 13px 15px;

            border-radius: 10px;

            font-size: 13px;

            line-height: 1.5;
        }

        .alert-error {
            color: #a52834;

            background: var(--danger-soft);

            border:
                1px solid #f3c9ce;
        }

        .alert-success {
            color: var(--green-dark);

            background: var(--green-soft);

            border:
                1px solid #ccebd8;
        }

        /*
        |--------------------------------------------------------------------------
        | Process Info
        |--------------------------------------------------------------------------
        */

        .process-box {
            margin:
                0 30px
                25px;

            padding: 18px;

            border:
                1px solid var(--border);

            border-radius: 13px;

            background: #fafcfb;
        }

        .process-title {
            display: flex;

            align-items: center;

            gap: 9px;

            margin-bottom: 13px;

            font-size: 14px;

            font-weight: 700;
        }

        .process-title i {
            color: var(--green);
        }

        .process-list {
            margin: 0;

            padding-left: 20px;

            color: var(--muted);

            font-size: 12.5px;

            line-height: 1.7;
        }

        .process-list li {
            padding-left: 3px;

            margin-bottom: 5px;
        }

        .process-list strong {
            color: var(--text);
        }

        /*
        |--------------------------------------------------------------------------
        | Form
        |--------------------------------------------------------------------------
        */

        .form-area {
            padding:
                0 30px
                30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;

            margin-bottom: 8px;

            font-size: 13px;

            font-weight: 700;
        }

        .form-control {
            width: 100%;

            height: 46px;

            padding:
                0 13px;

            border:
                1px solid var(--border);

            border-radius: 9px;

            background: white;

            color: var(--text);

            font-family: inherit;

            font-size: 14px;

            outline: none;

            transition:
                border-color 0.2s,
                box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: var(--green);

            box-shadow:
                0 0 0 3px
                rgba(25, 135, 84, 0.10);
        }

        .form-hint {
            margin: 7px 0 0;

            color: var(--muted);

            font-size: 11.5px;
        }

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        .summary {
            margin-top: 5px;

            border-top:
                1px solid var(--border);
        }

        .summary-row {
            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 20px;

            padding: 13px 0;

            border-bottom:
                1px dashed var(--border);

            font-size: 13px;
        }

        .summary-row span {
            color: var(--muted);
        }

        .summary-row strong {
            text-align: right;

            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | Submit
        |--------------------------------------------------------------------------
        */

        .btn-submit {
            width: 100%;

            height: 48px;

            display: flex;

            align-items: center;
            justify-content: center;

            gap: 9px;

            margin-top: 22px;

            border: 0;

            border-radius: 10px;

            background: var(--green);

            color: white;

            font-family: inherit;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            transition:
                background 0.2s,
                transform 0.15s,
                box-shadow 0.2s;
        }

        .btn-submit:hover {
            background: var(--green-dark);

            box-shadow:
                0 8px 20px
                rgba(25, 135, 84, 0.20);

            transform: translateY(-1px);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /*
        |--------------------------------------------------------------------------
        | Disabled / Empty Stock
        |--------------------------------------------------------------------------
        */

        .empty-stock {
            margin:
                0 30px
                30px;

            padding: 20px;

            text-align: center;

            background: var(--danger-soft);

            border:
                1px solid #f3c9ce;

            border-radius: 12px;

            color: #a52834;

            font-size: 13px;
        }

        .empty-stock i {
            display: block;

            margin-bottom: 8px;

            font-size: 24px;
        }

        /*
        |--------------------------------------------------------------------------
        | Footer note
        |--------------------------------------------------------------------------
        */

        .footer-note {
            margin-top: 18px;

            text-align: center;

            color: var(--muted);

            font-size: 11px;

            line-height: 1.6;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 600px) {

            .page {
                padding:
                    20px 13px
                    40px;
            }

            .card-header {
                padding: 23px 20px;
            }

            .book-box {
                margin:
                    20px;
            }

            .process-box {
                margin:
                    0 20px
                    20px;
            }

            .form-area {
                padding:
                    0 20px
                    25px;
            }

            .alert {
                margin:
                    0 20px
                    18px;
            }

            .empty-stock {
                margin:
                    0 20px
                    25px;
            }

            .book-box {
                align-items: flex-start;
            }

            .book-icon {
                flex-basis: 48px;

                width: 48px;
                height: 60px;
            }

            .card-header h1 {
                font-size: 20px;
            }
        }

    </style>

</head>

<body>

<div class="page">

    <a
        href="../../index.php#katalog"
        class="back-link"
    >
        <i class="fas fa-arrow-left"></i>
        Kembali ke katalog
    </a>

    <div class="card">

        <!-- HEADER -->

        <div class="card-header">

            <div class="header-icon">
                <i class="fas fa-book-open"></i>
            </div>

            <h1>
                Ajukan Peminjaman
            </h1>

            <p>
                Lengkapi jumlah buku yang ingin kamu pinjam.
                Pengajuan akan diperiksa oleh admin atau petugas
                sebelum peminjaman disetujui.
            </p>

        </div>


        <!-- DATA BUKU -->

        <div class="book-box">

            <div class="book-icon">
                <i class="fas fa-book"></i>
            </div>

            <div class="book-info">

                <h2 class="book-title">
                    <?= htmlspecialchars(
                        $book['judul'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h2>

                <p class="book-author">
                    Oleh
                    <strong>
                        <?= htmlspecialchars(
                            $book['penulis'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>
                </p>

                <div class="book-details">

                    <span class="book-badge">
                        <i class="fas fa-layer-group"></i>

                        <?= htmlspecialchars(
                            $book['nama_kategori'] ?? '-',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                    <span class="book-badge stock">
                        <i class="fas fa-box"></i>

                        Stok:
                        <?= $stokTersedia ?>
                    </span>

                </div>

            </div>

        </div>


        <!-- FLASH -->

        <?php if ($flash): ?>

            <div
                class="alert alert-<?= $flash['type'] === 'success'
                    ? 'success'
                    : 'error' ?>"
            >

                <?= htmlspecialchars(
                    $flash['message'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <!-- ALUR -->

        <div class="process-box">

            <div class="process-title">

                <i class="fas fa-circle-info"></i>

                Cara kerja peminjaman

            </div>

            <ol class="process-list">

                <li>
                    Tentukan jumlah buku yang ingin dipinjam.
                </li>

                <li>
                    Klik
                    <strong>
                        Ajukan Peminjaman
                    </strong>.
                </li>

                <li>
                    Pengajuan akan berstatus
                    <strong>
                        Menunggu Konfirmasi
                    </strong>.
                </li>

                <li>
                    Admin atau petugas akan memeriksa
                    pengajuan kamu.
                </li>

                <li>
                    Buku baru berstatus
                    <strong>
                        Dipinjam
                    </strong>
                    setelah pengajuan dikonfirmasi.
                </li>

            </ol>

        </div>


        <?php if ($stokTersedia <= 0): ?>

            <!-- STOK HABIS -->

            <div class="empty-stock">

                <i class="fas fa-box-open"></i>

                <strong>
                    Stok buku sedang habis.
                </strong>

                <br>

                Buku ini belum dapat diajukan untuk peminjaman.

            </div>

        <?php else: ?>

            <!-- FORM -->

            <div class="form-area">

                <form
                    action="pinjam_proses.php"
                    method="POST"
                >

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="book_id"
                        value="<?= (int) $book['id'] ?>"
                    >


                    <div class="form-group">

                        <label
                            for="jumlah"
                            class="form-label"
                        >
                            Jumlah Eksemplar
                        </label>

                        <input
                            type="number"
                            id="jumlah"
                            name="jumlah"
                            class="form-control"
                            min="1"
                            max="<?= $stokTersedia ?>"
                            value="1"
                            required
                        >

                        <p class="form-hint">

                            Maksimal
                            <strong>
                                <?= $stokTersedia ?>
                            </strong>
                            eksemplar sesuai stok yang tersedia.

                        </p>

                    </div>


                    <!-- RINGKASAN -->

                    <div class="summary">

                        <div class="summary-row">

                            <span>
                                Tanggal pengajuan
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $tanggalPengajuan,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Perkiraan masa pinjam
                            </span>

                            <strong>
                                Maksimal 7 hari
                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Status setelah diajukan
                            </span>

                            <strong style="color: var(--green);">
                                Menunggu Konfirmasi
                            </strong>

                        </div>

                    </div>


                    <button
                        type="submit"
                        class="btn-submit"
                    >

                        <i class="fas fa-paper-plane"></i>

                        Ajukan Peminjaman

                    </button>

                    <div class="footer-note">

                        Pengajuan belum dianggap sebagai
                        peminjaman aktif sampai dikonfirmasi
                        oleh admin atau petugas.

                    </div>

                </form>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>