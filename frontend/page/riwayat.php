<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();
cekAksesPeminjam();

if (!sudahLogin()) {
    setFlash('error', 'Silakan masuk dulu buat melihat riwayat peminjaman kamu.');
    header('Location: ../../login/pages/login.php');
    exit;
}

$me = currentUser();
if (($me['role'] ?? '') !== 'peminjam') {
    http_response_code(403);
    exit('Akses ditolak.');
}

$db = (new Database())->connect();
$flash = getFlash();

$stmt = $db->prepare("
    SELECT loans.*, GROUP_CONCAT(books.judul SEPARATOR ', ') AS judul_buku
    FROM loans
    LEFT JOIN loan_details ON loan_details.loan_id = loans.id
    LEFT JOIN books ON books.id = loan_details.book_id
    WHERE loans.user_id = ?
    GROUP BY loans.id
    ORDER BY loans.id DESC
");
$stmt->execute([$me['id']]);
$loans = $stmt->fetchAll();

$today = new DateTime();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Peminjaman Saya — Perpustakaan Digital</title>
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
    body { margin: 0; min-height: 100vh; font-family: 'Inter', sans-serif; background: var(--color-bg); color: var(--color-ink); padding: 2rem 1.25rem; }
    .wrap { max-width: 900px; margin: 0 auto; }
    .back-link { display: inline-flex; align-items: center; gap: 0.4rem; color: var(--color-ink-soft); text-decoration: none; font-size: 0.88rem; margin-bottom: 1.25rem; }
    .back-link:hover { color: var(--color-primary); }
    .card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 16px; padding: 1.75rem 1.5rem; box-shadow: 0 20px 45px -30px rgba(11,79,156,0.35); }
    h1 { font-family: 'Poppins', sans-serif; font-size: 1.3rem; margin: 0 0 1.25rem; }
    .alert { border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.88rem; margin-bottom: 1.25rem; }
    .alert-error { background: #FBE7E5; color: #A23B2E; }
    .alert-success { background: #E3F0FB; color: var(--color-primary); }
    table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    th, td { text-align: left; padding: 0.65rem 0.6rem; border-bottom: 1px solid var(--color-border); }
    th { color: var(--color-ink-soft); font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.02em; }
    .badge { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.76rem; font-weight: 600; }
    .badge-dipinjam { background: #E3F0FB; color: var(--color-primary); }
    .badge-dikembalikan { background: #E4F5E9; color: #1B7A3D; }
    .badge-terlambat { background: #FBE7E5; color: #A23B2E; }
    .empty { text-align: center; color: var(--color-ink-soft); padding: 2rem 0; }
</style>
</head>
<body>
<div class="wrap">
    <a href="../../index.php#katalog" class="back-link"><i class="fas fa-arrow-left"></i> Kembali ke katalog</a>

    <div class="card">
        <h1><i class="fas fa-clock-rotate-left" style="color:var(--color-accent);margin-right:0.4rem;"></i>Riwayat Peminjaman Saya</h1>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (empty($loans)): ?>
            <p class="empty">Kamu belum pernah mengajukan peminjaman. Yuk cari buku di katalog!</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr><th>Kode</th><th>Buku</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Tgl Kembali</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): ?>
                        <?php
                        $status = $loan['status'];
                        $isTelatBelumKembali = $status === 'dipinjam' && new DateTime($loan['tanggal_jatuh_tempo']) < $today;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($loan['kode_peminjaman'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['judul_buku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['tanggal_pinjam'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['tanggal_kembali'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($isTelatBelumKembali): ?>
                                    <span class="badge badge-terlambat">Belum dikembalikan (lewat tenggat)</span>
                                <?php else: ?>
                                    <span class="badge badge-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
