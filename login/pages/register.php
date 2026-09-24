<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();

if (sudahLogin()) {
    header('Location: ../../index.php');
    exit;
}

$flash = getFlash();
$csrfToken = csrfToken();
$old = $_SESSION['old_register'] ?? [];
unset($_SESSION['old_register']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun — Perpustakaan Digital</title>
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
        margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        font-family: 'Inter', sans-serif; background: var(--color-bg); color: var(--color-ink); padding: 1.5rem;
    }
    .login-card {
        background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 16px;
        padding: 2.5rem 2.25rem; width: 100%; max-width: 440px; box-shadow: 0 20px 45px -30px rgba(11,79,156,0.35);
    }
    .login-brand { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.35rem; color: var(--color-primary); margin-bottom: 0.3rem; text-align: center; }
    .login-brand i { margin-right: 0.4rem; color: var(--color-accent); }
    .login-sub { text-align: center; color: var(--color-ink-soft); font-size: 0.9rem; margin-bottom: 1.75rem; }
    .form-row { display: flex; gap: 0.8rem; }
    .form-group { margin-bottom: 1.1rem; flex: 1; }
    label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; }
    input[type=text], input[type=email], input[type=password] {
        width: 100%; padding: 0.65rem 0.9rem; border: 1.5px solid var(--color-border); border-radius: 8px;
        font-size: 0.95rem; font-family: inherit; background: var(--color-bg);
    }
    input:focus { outline: none; border-color: var(--color-primary); }
    .form-hint { font-size: 0.76rem; color: var(--color-ink-soft); margin-top: 0.3rem; }
    .btn-login {
        width: 100%; background: var(--color-primary); color: #fff; border: none; border-radius: 8px;
        padding: 0.75rem; font-weight: 600; font-size: 0.95rem; cursor: pointer; margin-top: 0.5rem;
    }
    .btn-login:hover { background: var(--color-primary-dark); }
    .alert { border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.88rem; margin-bottom: 1.25rem; }
    .alert-error { background: #FBE7E5; color: #A23B2E; }
    .back-link { display: block; text-align: center; margin-top: 1.5rem; font-size: 0.85rem; color: var(--color-ink-soft); text-decoration: none; }
    .back-link:hover { color: var(--color-primary); }
</style>
</head>
<body>
    <div class="login-card">
        <p class="login-brand"><i class="fas fa-user-plus"></i>Daftar Akun Peminjam</p>
        <p class="login-sub">Isi data di bawah buat mulai pinjam buku</p>

        <?php if ($flash): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="../function/proses_register.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" required maxlength="150"
                       value="<?= htmlspecialchars($old['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required maxlength="50" pattern="[A-Za-z0-9_]{3,50}"
                           value="<?= htmlspecialchars($old['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <p class="form-hint">Huruf, angka, underscore. Min 3 karakter.</p>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required maxlength="150"
                           value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8" maxlength="255">
                <p class="form-hint">Minimal 8 karakter.</p>
            </div>

            <div class="form-group">
                <label for="password_confirm">Konfirmasi Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8" maxlength="255">
            </div>

            <button type="submit" class="btn-login">Daftar</button>
        </form>

        <a href="login.php" class="back-link"><i class="fas fa-arrow-left me-1"></i> Sudah punya akun? Masuk</a>
    </div>
</body>
</html>
