<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();

if (sudahLogin()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') {
        header('Location: ../../backend/admin/index.php');
    } elseif ($role === 'petugas') {
        header('Location: ../../backend/petugas/index.php');
    } else {
        header('Location: ../../index.php');
    }
    exit;
}

$flash = getFlash();
$csrfToken = csrfToken();
$expired = isset($_GET['expired']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — Perpustakaan Digital</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--green:#2f5d50;--green-dark:#23483e;--cream:#f6f2e9;--paper:#fffdf8;--line:#ded8ca;--text:#29352f;--muted:#69736d;--brown:#b4773f}
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Arial,sans-serif;background:var(--cream);color:var(--text);display:flex;align-items:center;justify-content:center;padding:24px}
.login-wrap{width:min(920px,100%);display:grid;grid-template-columns:1.05fr .95fr;background:var(--paper);border:1px solid var(--line);box-shadow:0 14px 40px rgba(54,47,35,.09);border-radius:12px;overflow:hidden}
.login-intro{background:#263f37;color:#fff;padding:42px 38px;display:flex;flex-direction:column;justify-content:center}.brand-mark{display:flex;align-items:center;gap:10px;font-weight:700;font-size:1.15rem;margin-bottom:44px}.brand-mark i{color:#e1b27b}.intro-label{color:#e1b27b;text-transform:uppercase;letter-spacing:.08em;font-size:.74rem;font-weight:700;margin:0 0 8px}.login-intro h1{font-family:Poppins,Arial,sans-serif;font-size:2rem;line-height:1.25;margin:0 0 12px}.login-intro p{color:#d2ddd7;line-height:1.7;font-size:.92rem;max-width:34rem}.intro-note{margin-top:30px;padding-top:18px;border-top:1px solid rgba(255,255,255,.12);font-size:.82rem;color:#b9c9c1}
.login-card{padding:42px 38px;background:var(--paper)}.login-title{font-family:Poppins,Arial,sans-serif;font-size:1.45rem;margin:0 0 5px}.login-sub{text-align:left;color:var(--muted);font-size:.88rem;margin:0 0 25px}.form-group{margin-bottom:16px}label{display:block;font-size:.84rem;font-weight:600;margin-bottom:7px}.input-wrap{position:relative}.input-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#89948e;font-size:.9rem}.input-wrap input{padding-left:38px!important}input[type=text],input[type=password]{width:100%;padding:11px 12px;border:1px solid #d6d0c3;border-radius:7px;font:inherit;font-size:.9rem;background:#fff}.input-wrap input:focus{outline:none;border-color:#7b9a8d;box-shadow:0 0 0 3px rgba(47,93,80,.08)}.btn-login{width:100%;background:var(--green);color:#fff;border:0;border-radius:7px;padding:11px;font-weight:600;font-size:.9rem;cursor:pointer;margin-top:5px}.btn-login:hover{background:var(--green-dark)}.alert{border-radius:7px;padding:10px 12px;font-size:.84rem;margin-bottom:15px}.alert-error{background:#fbefeb;color:#9a4b37;border:1px solid #efd5cc}.alert-success{background:#edf5ef;color:var(--green);border:1px solid #d3e5d7}.register-link,.back-link{font-size:.83rem;text-align:center;color:var(--muted)}.register-link{margin:18px 0 8px}.register-link a{color:var(--green);font-weight:600;text-decoration:none}.back-link{display:block;text-decoration:none;margin-top:8px}.back-link:hover{color:var(--green)}
@media(max-width:720px){.login-wrap{grid-template-columns:1fr}.login-intro{padding:28px}.login-intro h1{font-size:1.55rem}.brand-mark{margin-bottom:25px}.intro-note{display:none}.login-card{padding:30px 24px}}
</style>
</head>
<body>
<div class="login-wrap">
    <section class="login-intro">
        <div class="brand-mark"><i class="fas fa-book-open"></i><span>Perpustakaan Digital</span></div>
        <p class="intro-label">Ruang baca digital</p>
        <h1>Kelola buku dan peminjaman dengan lebih mudah.</h1>
        <p>Masuk untuk mengelola koleksi, mencatat peminjaman, pengembalian, dan melihat laporan sesuai hak akses akun.</p>
        <p class="intro-note"><i class="fas fa-lock me-1"></i> Akses halaman menyesuaikan peran administrator, petugas, atau peminjam.</p>
    </section>
    <section class="login-card">
        <h2 class="login-title">Masuk ke akun</h2>
        <p class="login-sub">Gunakan username dan password yang sudah terdaftar.</p>
        <?php if ($expired): ?>
            <div class="alert alert-error">Sesi kamu habis, silakan masuk lagi.</div>
        <?php endif; ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <form action="../function/proses_login.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrap"><i class="fas fa-user"></i><input type="text" id="username" name="username" required autofocus maxlength="50"></div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap"><i class="fas fa-lock"></i><input type="password" id="password" name="password" required maxlength="255"></div>
            </div>
            <button type="submit" class="btn-login"><i class="fas fa-right-to-bracket me-1"></i> Masuk</button>
        </form>
        <p class="register-link">Belum punya akun peminjam? <a href="register.php">Daftar di sini</a></p>
        <a href="../../index.php" class="back-link"><i class="fas fa-arrow-left me-1"></i> Kembali ke beranda</a>
    </section>
</div>
</body>
</html>
