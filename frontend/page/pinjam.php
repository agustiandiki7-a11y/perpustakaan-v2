<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();
cekAksesPeminjam(); // staf (admin/petugas) ditendang ke dashboard backend

if (!sudahLogin()) {
    setFlash('error', 'Silakan masuk dulu buat mengajukan peminjaman.');
    header('Location: ../../login/pages/login.php');
    exit;
}

$me = currentUser();
if (($me['role'] ?? '') !== 'peminjam') {
    // Jaga-jaga kalau suatu saat ada role lain selain admin/petugas/peminjam.
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
    FROM books LEFT JOIN categories ON categories.id = books.category_id
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
<title>Ajukan Peminjaman — Perpustakaan Digital</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        --color-bg: #F2F6FC; --color-surface: #FFFFFF; --color-ink: #12202E;
        --color-ink-soft: #5B6B7C; --color-primary: #0B4F9C; --color-primary-dark: #073868;
        --color-accent: #1E9BE0; --color-border: #DCE6F0;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; font-family: 'Inter', sans-serif; background: var(--color-bg);
        color: var(--color-ink); padding: 2rem 1.25rem;
    }
    .wrap { max-width: 640px; margin: 0 auto; }
    .back-link { display: inline-flex; align-items: center; gap: 0.4rem; color: var(--color-ink-soft); text-decoration: none; font-size: 0.88rem; margin-bottom: 1.25rem; }
    .back-link:hover { color: var(--color-primary); }
    .card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 16px; padding: 2rem; box-shadow: 0 20px 45px -30px rgba(11,79,156,0.35); }
    h1 { font-family: 'Poppins', sans-serif; font-size: 1.3rem; margin: 0 0 0.3rem; }
    .book-meta { color: var(--color-ink-soft); font-size: 0.92rem; margin-bottom: 1.5rem; }
    .book-meta strong { color: var(--color-ink); }
    .info-steps { background: #EEF5FC; border: 1px solid var(--color-border); border-radius: 10px; padding: 1rem 1.15rem; margin-bottom: 1.5rem; font-size: 0.86rem; color: var(--color-ink-soft); }
    .info-steps ol { margin: 0.4rem 0 0; padding-left: 1.1rem; }
    .info-steps li { margin-bottom: 0.25rem; }
    .form-group { margin-bottom: 1.1rem; }
    label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; }
    input[type=number] {
        width: 100%; padding: 0.65rem 0.9rem; border: 1.5px solid var(--color-border); border-radius: 8px;
        font-size: 0.95rem; font-family: inherit; background: var(--color-bg);
    }
    input:focus { outline: none; border-color: var(--color-primary); }
    .form-hint { font-size: 0.78rem; color: var(--color-ink-soft); margin-top: 0.3rem; }
    .summary-row { display: flex; justify-content: space-between; font-size: 0.88rem; padding: 0.4rem 0; border-bottom: 1px dashed var(--color-border); }
    .summary-row:last-child { border-bottom: none; }
    .btn-submit {
        width: 100%; background: var(--color-primary); color: #fff; border: none; border-radius: 8px;
        padding: 0.8rem; font-weight: 600; font-size: 0.95rem; cursor: pointer; margin-top: 1.2rem;
    }
    .btn-submit:hover { background: var(--color-primary-dark); }
    .alert { border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.88rem; margin-bottom: 1.25rem; }
    .alert-error { background: #FBE7E5; color: #A23B2E; }
    .alert-success { background: #E3F0FB; color: var(--color-primary); }
</style>
</head>
<body>
<div class="wrap">
    <a href="../../index.php#katalog" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke katalog</a>

    <div class="card">
        <h1><i class="fas fa-book-open" style="color:var(--color-accent);margin-right:0.4rem;"></i>Ajukan Peminjaman</h1>
        <p class="book-meta">
            <strong><?= htmlspecialchars($book['judul'], ENT_QUOTES, 'UTF-8') ?></strong> · oleh <?= htmlspecialchars($book['penulis'], ENT_QUOTES, 'UTF-8') ?><br>
            Kategori: <?= htmlspecialchars($book['nama_kategori'] ?? '-', ENT_QUOTES, 'UTF-8') ?> · Stok tersedia: <strong><?= $stokTersedia ?></strong>
        </p>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="info-steps">
            Alur peminjaman:
            <ol>
                <li>Tentukan jumlah eksemplar yang mau dipinjam (maks sesuai stok tersedia).</li>
                <li>Klik <strong>"Ajukan Peminjaman"</strong> di bawah.</li>
                <li>Peminjaman langsung tercatat aktif hari ini dan wajib dikembalikan dalam <strong>7 hari</strong>.</li>
                <li>Status &amp; tenggat pengembalian bisa dicek kapan saja di halaman <strong>Riwayat Saya</strong>.</li>
            </ol>
        </div>

        <?php if ($stokTersedia <= 0): ?>
            <div class="alert alert-error">Maaf, stok buku ini sedang habis dan tidak bisa dipinjam saat ini.</div>
        <?php else: ?>
            <form action="pinjam_proses.php" method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">

                <div class="form-group">
                    <label for="jumlah">Jumlah eksemplar</label>
                    <input type="number" id="jumlah" name="jumlah" min="1" max="<?= $stokTersedia ?>" value="1" required>
                    <p class="form-hint">Maksimal <?= $stokTersedia ?> eksemplar (sesuai stok tersedia).</p>
                </div>

                <div class="summary-row"><span>Tanggal pinjam</span><strong><?= htmlspecialchars($tanggalPinjam, ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="summary-row"><span>Batas pengembalian</span><strong><?= htmlspecialchars($tanggalKembali, ENT_QUOTES, 'UTF-8') ?> (7 hari)</strong></div>

                <button type="submit" class="btn-submit"><i class="fas fa-check me-1"></i> Ajukan Peminjaman</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
