<?php

require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();
cekAksesPeminjam();

if (!sudahLogin()) {
    setFlash('error', 'Silakan masuk terlebih dahulu.');
    header('Location: ../../login/pages/login.php');
    exit;
}

$me = currentUser();

if (($me['role'] ?? '') !== 'peminjam') {
    http_response_code(403);
    exit('Akses ditolak.');
}

/*
|--------------------------------------------------------------------------
| Ambil kode peminjaman
|--------------------------------------------------------------------------
*/

$kodePeminjaman = trim($_GET['kode'] ?? '');

if ($kodePeminjaman === '') {
    header('Location: ../../index.php');
    exit;
}

$db = (new Database())->connect();

/*
|--------------------------------------------------------------------------
| Ambil data pengajuan
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        l.id,
        l.kode_peminjaman,
        l.tanggal_pengajuan,
        l.status,
        ld.jumlah,
        b.judul,
        b.penulis
    FROM loans l
    INNER JOIN loan_details ld
        ON ld.loan_id = l.id
    INNER JOIN books b
        ON b.id = ld.book_id
    WHERE l.kode_peminjaman = ?
      AND l.user_id = ?
    LIMIT 1
");

$stmt->execute([
    $kodePeminjaman,
    $me['id']
]);

$peminjaman = $stmt->fetch();

if (!$peminjaman) {
    setFlash('error', 'Data peminjaman tidak ditemukan.');
    header('Location: ../../index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Pastikan halaman ini hanya untuk pengajuan yang menunggu
|--------------------------------------------------------------------------
*/

if (($peminjaman['status'] ?? '') !== 'menunggu') {
    header(
        'Location: riwayat.php'
    );
    exit;
}

$namaBuku = htmlspecialchars(
    $peminjaman['judul'],
    ENT_QUOTES,
    'UTF-8'
);

$penulis = htmlspecialchars(
    $peminjaman['penulis'] ?? '-',
    ENT_QUOTES,
    'UTF-8'
);

$kode = htmlspecialchars(
    $peminjaman['kode_peminjaman'],
    ENT_QUOTES,
    'UTF-8'
);

$jumlah = (int) $peminjaman['jumlah'];

$tanggalPengajuan = date(
    'd F Y',
    strtotime($peminjaman['tanggal_pengajuan'])
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Peminjaman Sedang Diproses - Perpustakaan Digital
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --primary: #198754;
            --primary-dark: #146c43;
            --primary-light: #eaf6ef;

            --sidebar: #173b2c;

            --bg: #f5f7f6;
            --white: #ffffff;

            --text: #202a25;
            --text-soft: #718078;

            --border: #e1e8e4;

            --warning: #d98c00;
            --warning-bg: #fff8e6;

            --shadow:
                0 20px 50px rgba(23, 59, 44, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 30px 18px;

            background:
                radial-gradient(circle at top left,
                    rgba(25, 135, 84, 0.07),
                    transparent 35%),
                var(--bg);

            color: var(--text);

            font-family: 'Inter', sans-serif;
        }

        .page {
            width: 100%;
            max-width: 570px;
        }

        /*
        |--------------------------------------------------------------------------
        | Card
        |--------------------------------------------------------------------------
        */

        .card {
            overflow: hidden;

            background: var(--white);

            border: 1px solid var(--border);
            border-radius: 18px;

            box-shadow: var(--shadow);
        }

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .card-header {
            padding: 28px 28px 20px;

            text-align: center;
        }

        .success-icon {
            width: 70px;
            height: 70px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 18px;

            background: var(--primary-light);

            border: 1px solid #cce8d8;
            border-radius: 50%;

            color: var(--primary);

            font-size: 28px;
        }

        .card-header h1 {
            margin: 0 0 8px;

            color: var(--text);

            font-family: 'Poppins', sans-serif;

            font-size: 22px;
            font-weight: 700;
        }

        .card-header p {
            max-width: 430px;

            margin: 0 auto;

            color: var(--text-soft);

            font-size: 13px;

            line-height: 1.7;
        }

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .status-box {
            display: flex;
            align-items: center;
            gap: 13px;

            margin: 0 28px 20px;

            padding: 14px 16px;

            background: var(--warning-bg);

            border: 1px solid #f0dfb1;
            border-radius: 10px;
        }

        .status-icon {
            width: 38px;
            height: 38px;

            flex: 0 0 38px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #fff1c9;

            border-radius: 9px;

            color: var(--warning);

            font-size: 15px;
        }

        .status-content strong {
            display: block;

            margin-bottom: 3px;

            color: #8a6100;

            font-size: 13px;
        }

        .status-content span {
            color: #98772c;

            font-size: 12px;

            line-height: 1.5;
        }

        /*
        |--------------------------------------------------------------------------
        | Content
        |--------------------------------------------------------------------------
        */

        .card-body {
            padding: 0 28px 28px;
        }

        .section-title {
            margin: 0 0 12px;

            color: var(--text);

            font-size: 13px;
            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | Book
        |--------------------------------------------------------------------------
        */

        .book-box {
            display: flex;
            align-items: flex-start;
            gap: 14px;

            padding: 15px;

            background: #fafcfb;

            border: 1px solid var(--border);
            border-radius: 11px;
        }

        .book-icon {
            width: 45px;
            height: 52px;

            flex: 0 0 45px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: var(--sidebar);

            border-radius: 8px;

            color: #ffffff;

            font-size: 17px;
        }

        .book-info {
            min-width: 0;
        }

        .book-title {
            margin: 0 0 4px;

            color: var(--text);

            font-family: 'Poppins', sans-serif;

            font-size: 14px;
            font-weight: 600;

            line-height: 1.5;
        }

        .book-author {
            margin: 0;

            color: var(--text-soft);

            font-size: 12px;
        }

        /*
        |--------------------------------------------------------------------------
        | Detail
        |--------------------------------------------------------------------------
        */

        .details {
            margin-top: 15px;

            overflow: hidden;

            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 15px;

            padding: 11px 14px;

            font-size: 12px;
        }

        .detail-row+.detail-row {
            border-top: 1px solid var(--border);
        }

        .detail-label {
            color: var(--text-soft);
        }

        .detail-value {
            color: var(--text);

            font-weight: 600;

            text-align: right;
        }

        .code {
            color: var(--primary);

            font-family: monospace;

            font-size: 12px;
        }

        /*
        |--------------------------------------------------------------------------
        | Information
        |--------------------------------------------------------------------------
        */

        .info {
            display: flex;
            align-items: flex-start;
            gap: 10px;

            margin-top: 18px;

            padding: 13px 14px;

            background: var(--primary-light);

            border: 1px solid #cce8d8;
            border-radius: 9px;

            color: #426653;

            font-size: 12px;

            line-height: 1.6;
        }

        .info i {
            margin-top: 2px;

            color: var(--primary);
        }

        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .actions {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 10px;

            margin-top: 22px;
        }

        .btn {
            min-height: 43px;

            display: flex;
            align-items: center;
            justify-content: center;

            gap: 7px;

            padding: 10px 15px;

            border-radius: 8px;

            font-family: inherit;

            font-size: 13px;
            font-weight: 600;

            text-decoration: none;

            transition:
                background 0.2s ease,
                border-color 0.2s ease,
                transform 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--primary);

            border: 1px solid var(--primary);

            color: #ffffff;
        }

        .btn-primary:hover {
            background: var(--primary-dark);

            border-color: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--white);

            border: 1px solid var(--border);

            color: var(--text);
        }

        .btn-secondary:hover {
            background: #f8faf9;

            border-color: #cfd8d3;
        }

        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .footer {
            margin-top: 18px;

            color: #929b96;

            font-size: 11px;

            text-align: center;
        }

        /*
        |--------------------------------------------------------------------------
        | Mobile
        |--------------------------------------------------------------------------
        */

        @media (max-width: 550px) {

            body {
                padding: 18px 12px;
            }

            .card-header {
                padding: 24px 20px 18px;
            }

            .card-body {
                padding: 0 20px 22px;
            }

            .status-box {
                margin-left: 20px;
                margin-right: 20px;
            }

            .card-header h1 {
                font-size: 20px;
            }

            .actions {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 380px) {

            .card-header {
                padding: 21px 16px 16px;
            }

            .card-body {
                padding: 0 16px 20px;
            }

            .status-box {
                margin-left: 16px;
                margin-right: 16px;
            }

            .book-box {
                padding: 12px;
            }

            .detail-row {
                align-items: flex-start;

                flex-direction: column;

                gap: 4px;
            }

            .detail-value {
                text-align: left;
            }

        }
    </style>

</head>

<body>

    <div class="page">

        <div class="card">

            <!-- HEADER -->

            <div class="card-header">

                <div class="success-icon">
                    <i class="fa-solid fa-check"></i>
                </div>

                <h1>
                    Peminjaman Sedang Diproses
                </h1>

                <p>
                    Pengajuan peminjaman kamu berhasil dikirim.
                    Silakan tunggu konfirmasi dari admin atau petugas
                    perpustakaan sebelum buku dapat dipinjam.
                </p>

            </div>


            <!-- STATUS -->

            <div class="status-box">

                <div class="status-icon">
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div class="status-content">

                    <strong>
                        Menunggu Konfirmasi
                    </strong>

                    <span>
                        Pengajuan kamu sedang diperiksa oleh
                        admin atau petugas perpustakaan.
                    </span>

                </div>

            </div>


            <div class="card-body">

                <!-- BUKU -->

                <h2 class="section-title">
                    Detail Pengajuan
                </h2>

                <div class="book-box">

                    <div class="book-icon">
                        <i class="fa-solid fa-book"></i>
                    </div>

                    <div class="book-info">

                        <h3 class="book-title">
                            <?= $namaBuku ?>
                        </h3>

                        <p class="book-author">
                            oleh <?= $penulis ?>
                        </p>

                    </div>

                </div>


                <!-- DETAIL -->

                <div class="details">

                    <div class="detail-row">

                        <span class="detail-label">
                            Kode Peminjaman
                        </span>

                        <span class="detail-value code">
                            <?= $kode ?>
                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            Jumlah Buku
                        </span>

                        <span class="detail-value">
                            <?= $jumlah ?> eksemplar
                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            Tanggal Pengajuan
                        </span>

                        <span class="detail-value">
                            <?= htmlspecialchars(
                                $tanggalPengajuan,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="detail-label">
                            Status
                        </span>

                        <span
                            class="detail-value"
                            style="color: var(--warning);">
                            Menunggu Konfirmasi
                        </span>

                    </div>

                </div>


                <!-- INFO -->

                <div class="info">

                    <i class="fa-solid fa-circle-info"></i>

                    <span>
                        Peminjaman belum dihitung sebagai peminjaman aktif.
                        Tanggal peminjaman dan batas pengembalian akan
                        ditentukan setelah admin atau petugas menyetujui
                        pengajuan kamu.
                    </span>

                </div>


                <!-- ACTION -->

                <div class="actions">

                    <a
                        href="riwayat.php"
                        class="btn btn-primary">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        Lihat Riwayat
                    </a>


                    <a
                        href="../../index.php#katalog"
                        class="btn btn-secondary">
                        <i class="fa-solid fa-book-open"></i>
                        Kembali ke Katalog
                    </a>

                </div>

            </div>

        </div>


        <div class="footer">

            Perpustakaan Digital

        </div>

    </div>

</body>

</html>