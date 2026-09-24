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

$stmt = $db->prepare("
    SELECT books.*, categories.nama_kategori
    FROM books
    LEFT JOIN categories ON categories.id = books.category_id
    WHERE books.id = ? AND books.status = 'aktif'
");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    setFlash('error', 'Buku tidak ditemukan atau sudah tidak aktif.');
    header('Location: ../../index.php');
    exit;
}

$stokTersedia = (int) $book['stok_tersedia'];
$flash = getFlash();
$csrfToken = csrfToken();

$tanggalPinjam = date('Y-m-d');
$tanggalKembali = date('Y-m-d', strtotime('+7 days'));
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ajukan Peminjaman - Perpustakaan Digital</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
:root {
    --primary: #198754;
    --primary-dark: #146c43;
    --primary-light: #EAF6EF;

    --sidebar: #173B2C;
    --sidebar-dark: #102D21;

    --bg: #F5F7F6;
    --white: #FFFFFF;

    --text: #202A25;
    --text-soft: #718078;

    --border: #E1E8E4;

    --success: #198754;
    --success-bg: #EAF6EF;

    --danger: #D9534F;
    --danger-bg: #FFF0EF;

    --shadow: 0 8px 25px rgba(23, 59, 44, 0.06);

    --radius: 14px;
}

/* =========================
   RESET
========================= */

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    min-height: 100vh;

    font-family: 'Inter', sans-serif;

    background: var(--bg);
    color: var(--text);
}

/* =========================
   TOPBAR
========================= */

.topbar {
    height: 68px;

    display: flex;
    align-items: center;

    background: var(--white);

    border-bottom: 1px solid var(--border);

    color: var(--text);
}

.topbar-inner {
    width: min(1100px, calc(100% - 40px));

    margin: 0 auto;

    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* =========================
   BRAND
========================= */

.brand {
    display: flex;
    align-items: center;
    gap: 11px;

    color: var(--text);
    text-decoration: none;
}

.brand-icon {
    width: 38px;
    height: 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background: var(--primary-light);
    color: var(--primary);

    font-size: 17px;
}

.brand-name {
    font-family: 'Poppins', sans-serif;

    font-size: 17px;
    font-weight: 700;

    color: var(--text);
}

/* =========================
   USER INFO
========================= */

.user-info {
    display: flex;
    align-items: center;
    gap: 9px;

    color: var(--text-soft);

    font-size: 13px;
    font-weight: 500;
}

.user-icon {
    width: 32px;
    height: 32px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: var(--primary-light);
    color: var(--primary);

    font-size: 13px;
}

/* =========================
   PAGE
========================= */

.page {
    width: min(900px, calc(100% - 40px));

    margin: 0 auto;

    padding: 35px 0 50px;
}

/* =========================
   BACK LINK
========================= */

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    margin-bottom: 20px;

    color: var(--text-soft);

    text-decoration: none;

    font-size: 14px;
    font-weight: 500;

    transition: color 0.2s ease;
}

.back-link:hover {
    color: var(--primary);
}

.back-link i {
    font-size: 13px;
}

/* =========================
   MAIN CARD
========================= */

.card {
    overflow: hidden;

    background: var(--white);

    border: 1px solid var(--border);
    border-radius: var(--radius);

    box-shadow: var(--shadow);
}

/* =========================
   CARD HEADER
========================= */

.card-header {
    padding: 25px 28px;

    border-bottom: 1px solid var(--border);
}

.page-title {
    display: flex;
    align-items: center;
    gap: 13px;

    margin: 0;

    color: var(--text);

    font-family: 'Poppins', sans-serif;

    font-size: 22px;
    font-weight: 700;
}

.title-icon {
    width: 42px;
    height: 42px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background: var(--primary-light);
    color: var(--primary);

    font-size: 18px;
}

.subtitle {
    margin: 8px 0 0 55px;

    color: var(--text-soft);

    font-size: 13px;
    line-height: 1.6;
}

/* =========================
   CARD BODY
========================= */

.card-body {
    padding: 28px;
}

/* =========================
   BOOK INFORMATION
========================= */

.book-box {
    display: flex;
    align-items: flex-start;
    gap: 17px;

    padding: 18px;

    margin-bottom: 24px;

    background: #FAFAFD;

    border: 1px solid var(--border);
    border-radius: 12px;
}

.book-icon {
    flex: 0 0 48px;

    width: 48px;
    height: 58px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background: var(--sidebar);
    color: #FFFFFF;

    font-size: 19px;
}

.book-content {
    min-width: 0;
}

.book-title {
    margin: 0 0 5px;

    color: var(--text);

    font-family: 'Poppins', sans-serif;

    font-size: 17px;
    font-weight: 700;

    line-height: 1.4;
}

.book-author {
    margin: 0 0 10px;

    color: var(--text-soft);

    font-size: 13px;
}

.book-details {
    display: flex;
    flex-wrap: wrap;

    gap: 8px;
}

.book-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    padding: 5px 9px;

    background: var(--white);

    border: 1px solid var(--border);
    border-radius: 6px;

    color: var(--text-soft);

    font-size: 12px;
}

.book-tag i {
    color: var(--primary);
}

.book-tag strong {
    color: var(--text);
}

/* =========================
   ALERT
========================= */

.alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;

    padding: 13px 15px;

    margin-bottom: 22px;

    border-radius: 9px;

    font-size: 13px;
    line-height: 1.5;
}

.alert i {
    margin-top: 2px;
}

/* Success */

.alert-success {
    background: var(--success-bg);
    color: var(--success);

    border: 1px solid #B9E8D0;
}

/* Error */

.alert-error {
    background: var(--danger-bg);
    color: var(--danger);

    border: 1px solid #F5C7C5;
}

/* =========================
   INFORMATION BOX
========================= */

.info-box {
    padding: 18px 20px;

    margin-bottom: 26px;

    background: var(--primary-light);

    border: 1px solid #DDD6FE;
    border-radius: 10px;
}

.info-title {
    display: flex;
    align-items: center;
    gap: 8px;

    margin-bottom: 10px;

    color: var(--text);

    font-size: 14px;
    font-weight: 700;
}

.info-title i {
    color: var(--primary);
}

.info-box ol {
    margin: 0;

    padding-left: 21px;

    color: var(--text-soft);

    font-size: 13px;
    line-height: 1.7;
}

.info-box li {
    padding-left: 3px;
}

.info-box li::marker {
    color: var(--primary);
    font-weight: 600;
}

.info-box strong {
    color: var(--text);
}

/* =========================
   FORM TITLE
========================= */

.form-title {
    margin: 0 0 17px;

    color: var(--text);

    font-family: 'Poppins', sans-serif;

    font-size: 16px;
    font-weight: 700;
}

/* =========================
   FORM
========================= */

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;

    margin-bottom: 7px;

    color: var(--text);

    font-size: 13px;
    font-weight: 600;
}

.input-wrapper {
    position: relative;
}

.input-wrapper i {
    position: absolute;

    left: 13px;
    top: 50%;

    transform: translateY(-50%);

    color: #9AA1B5;

    font-size: 14px;

    pointer-events: none;
}

.form-control {
    width: 100%;

    padding: 11px 13px 11px 39px;

    background: var(--white);

    border: 1px solid var(--border);
    border-radius: 8px;

    color: var(--text);

    font-family: inherit;

    font-size: 14px;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.form-control:hover {
    border-color: #D3D6E2;
}

.form-control:focus {
    outline: none;

    border-color: var(--primary);

    box-shadow: 0 0 0 3px rgba(108, 77, 223, 0.10);
}

.form-hint {
    margin: 7px 0 0;

    color: var(--text-soft);

    font-size: 12px;
}

/* =========================
   SUMMARY
========================= */

.summary {
    margin-top: 24px;

    overflow: hidden;

    background: var(--white);

    border: 1px solid var(--border);
    border-radius: 10px;
}

.summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    padding: 13px 15px;

    font-size: 13px;
}

.summary-row + .summary-row {
    border-top: 1px solid var(--border);
}

.summary-label {
    color: var(--text-soft);
}

.summary-label i {
    width: 17px;

    margin-right: 4px;

    color: var(--primary);
}

.summary-value {
    color: var(--text);

    font-weight: 600;

    text-align: right;
}

.summary-value small {
    color: var(--text-soft);

    font-size: 11px;
    font-weight: 500;
}

/* =========================
   SUBMIT BUTTON
========================= */

.btn-submit {
    width: 100%;

    display: flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    margin-top: 22px;

    padding: 12px 18px;

    background: var(--primary);
    color: #FFFFFF;

    border: none;
    border-radius: 8px;

    font-family: inherit;

    font-size: 14px;
    font-weight: 600;

    cursor: pointer;

    transition:
        background 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.btn-submit:hover {
    background: var(--primary-dark);

    box-shadow: 0 5px 14px rgba(108, 77, 223, 0.20);

    transform: translateY(-1px);
}

.btn-submit:active {
    transform: translateY(0);

    box-shadow: none;
}

.btn-submit i {
    font-size: 13px;
}

/* =========================
   EMPTY STOCK
========================= */

.empty-stock {
    padding: 28px 20px;

    text-align: center;

    background: var(--danger-bg);

    border: 1px solid #F5C7C5;
    border-radius: 10px;
}

.empty-stock-icon {
    width: 45px;
    height: 45px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin: 0 auto 10px;

    background: var(--white);

    border-radius: 50%;

    color: var(--danger);

    font-size: 17px;
}

.empty-stock strong {
    display: block;

    margin-bottom: 5px;

    color: var(--danger);

    font-size: 14px;
}

.empty-stock p {
    margin: 0;

    color: #9F302D;

    font-size: 12px;
    line-height: 1.5;
}

/* =========================
   FOOTER
========================= */

.footer {
    margin-top: 25px;

    color: #9298AA;

    font-size: 12px;

    text-align: center;
}

/* =========================
   INPUT NUMBER
========================= */

input[type="number"] {
    appearance: textfield;
    -moz-appearance: textfield;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    margin: 0;

    -webkit-appearance: none;
}

/* =========================
   SELECTION
========================= */

::selection {
    background: rgba(108, 77, 223, 0.18);
    color: var(--text);
}

/* =========================
   SCROLLBAR
========================= */

::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg);
}

::-webkit-scrollbar-thumb {
    background: #C8C5D9;

    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #AAA5C2;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 650px) {

    .topbar {
        height: 62px;
    }

    .topbar-inner {
        width: calc(100% - 28px);
    }

    .brand-name {
        font-size: 15px;
    }

    .user-info span {
        display: none;
    }

    .page {
        width: calc(100% - 24px);

        padding-top: 22px;
        padding-bottom: 35px;
    }

    .card-header,
    .card-body {
        padding: 20px;
    }

    .page-title {
        font-size: 19px;
    }

    .title-icon {
        width: 38px;
        height: 38px;

        font-size: 16px;
    }

    .subtitle {
        margin-left: 0;
        margin-top: 9px;
    }

    .book-box {
        gap: 13px;

        padding: 14px;
    }

    .book-icon {
        flex-basis: 42px;

        width: 42px;
        height: 52px;
    }

    .book-title {
        font-size: 15px;
    }

    .book-details {
        flex-direction: column;
        align-items: flex-start;
    }

    .summary-row {
        align-items: flex-start;
    }

    .summary-value {
        max-width: 55%;
    }
}

@media (max-width: 420px) {

    .topbar-inner {
        width: calc(100% - 20px);
    }

    .page {
        width: calc(100% - 18px);
    }

    .card-header,
    .card-body {
        padding: 17px;
    }

    .page-title {
        font-size: 18px;
    }

    .book-box {
        flex-direction: column;
    }

    .book-icon {
        width: 44px;
        height: 44px;
    }

    .summary-row {
        flex-direction: column;
        gap: 5px;
    }

    .summary-value {
        max-width: 100%;

        text-align: left;
    }
}
</style>
</head>

<body>

    <header class="topbar">
        <div class="topbar-inner">

            <a href="../../index.php" class="brand">
                <div class="brand-icon">
                    <i class="fas fa-book-open"></i>
                </div>

                <span class="brand-name">
                    Perpustakaan
                </span>
            </a>

            <div class="user-info">
                <div class="user-icon">
                    <i class="fas fa-user"></i>
                </div>

                <span>
                    <?= htmlspecialchars($me['NamaLengkap'] ?? $me['nama_lengkap'] ?? $me['username'] ?? 'Peminjam', ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

        </div>
    </header>

    <main class="page">

        <a href="../../index.php#katalog" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Kembali ke katalog
        </a>

        <section class="card">

            <div class="card-header">

                <h1 class="page-title">
                    <span class="title-icon">
                        <i class="fas fa-book-reader"></i>
                    </span>

                    Ajukan Peminjaman
                </h1>

                <p class="subtitle">
                    Isi jumlah buku yang ingin kamu pinjam. Pastikan data peminjaman sudah sesuai sebelum mengajukan.
                </p>

            </div>

            <div class="card-body">

                <div class="book-box">

                    <div class="book-icon">
                        <i class="fas fa-book"></i>
                    </div>

                    <div class="book-content">

                        <h2 class="book-title">
                            <?= htmlspecialchars($book['judul'], ENT_QUOTES, 'UTF-8') ?>
                        </h2>

                        <p class="book-author">
                            oleh <?= htmlspecialchars($book['penulis'], ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <div class="book-details">

                            <span class="book-tag">
                                <i class="fas fa-layer-group"></i>
                                <?= htmlspecialchars($book['nama_kategori'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </span>

                            <span class="book-tag">
                                <i class="fas fa-box"></i>
                                Stok:
                                <strong><?= $stokTersedia ?></strong>
                            </span>

                        </div>

                    </div>

                </div>

                <?php if ($flash): ?>

                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">

                        <i class="fas fa-<?= $flash['type'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>

                        <span>
                            <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    </div>

                <?php endif; ?>

                <div class="info-box">

                    <div class="info-title">
                        <i class="fas fa-circle-info"></i>
                        Alur Peminjaman
                    </div>

                    <ol>
                        <li>
                            Tentukan jumlah eksemplar yang ingin dipinjam.
                        </li>

                        <li>
                            Klik tombol <strong>Ajukan Peminjaman</strong>.
                        </li>

                        <li>
                            Peminjaman tercatat aktif mulai hari ini dan memiliki batas pengembalian 7 hari.
                        </li>

                        <li>
                            Status peminjaman dapat dilihat melalui halaman <strong>Riwayat Saya</strong>.
                        </li>
                    </ol>

                </div>

                <?php if ($stokTersedia <= 0): ?>

                    <div class="empty-stock">

                        <div class="empty-stock-icon">
                            <i class="fas fa-box-open"></i>
                        </div>

                        <strong>Stok Buku Sedang Habis</strong>

                        <p>
                            Buku ini belum dapat dipinjam karena stok yang tersedia saat ini adalah 0.
                        </p>

                    </div>

                <?php else: ?>

                    <form action="pinjam_proses.php" method="POST">

                        <?= csrfField() ?>

                        <input
                            type="hidden"
                            name="book_id"
                            value="<?= (int) $book['id'] ?>">

                        <h3 class="form-title">
                            Detail Peminjaman
                        </h3>

                        <div class="form-group">

                            <label class="form-label" for="jumlah">
                                Jumlah Eksemplar
                            </label>

                            <div class="input-wrapper">

                                <i class="fas fa-hashtag"></i>

                                <input
                                    type="number"
                                    id="jumlah"
                                    name="jumlah"
                                    class="form-control"
                                    min="1"
                                    max="<?= $stokTersedia ?>"
                                    value="1"
                                    required>

                            </div>

                            <p class="form-hint">
                                Maksimal <?= $stokTersedia ?> eksemplar sesuai stok yang tersedia.
                            </p>

                        </div>

                        <div class="summary">

                            <div class="summary-row">

                                <span class="summary-label">
                                    <i class="far fa-calendar"></i>
                                    Tanggal Pinjam
                                </span>

                                <strong class="summary-value">
                                    <?= htmlspecialchars($tanggalPinjam, ENT_QUOTES, 'UTF-8') ?>
                                </strong>

                            </div>

                            <div class="summary-row">

                                <span class="summary-label">
                                    <i class="far fa-calendar-check"></i>
                                    Batas Pengembalian
                                </span>

                                <strong class="summary-value">
                                    <?= htmlspecialchars($tanggalKembali, ENT_QUOTES, 'UTF-8') ?>
                                    <small>(7 hari)</small>
                                </strong>

                            </div>

                        </div>

                        <button type="submit" class="btn-submit">

                            <i class="fas fa-check"></i>

                            Ajukan Peminjaman

                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </section>

        <div class="footer">
            Perpustakaan Digital &copy; <?= date('Y') ?>
        </div>

    </main>

</body>

</html>