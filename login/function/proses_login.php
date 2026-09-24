<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Sesi form sudah kadaluarsa, silakan coba lagi.');
    header('Location: ../pages/login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    setFlash('error', 'Username dan password wajib diisi.');
    header('Location: ../pages/login.php');
    exit;
}

try {
    $db = (new Database())->connect();

    $stmt = $db->prepare('SELECT id, nama, username, email, password, role FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        setFlash('error', 'Username atau password salah.');
        header('Location: ../pages/login.php');
        exit;
    }

    loginUser($user);

    switch ($user['role']) {
        case 'admin':
            header('Location: ../../backend/admin/index.php');
            break;
        case 'petugas':
            header('Location: ../../backend/petugas/index.php');
            break;
        case 'peminjam':
            header('Location: ../../index.php');
            break;
        default:
            logoutUser();
            setFlash('error', 'Akun kamu belum punya peran yang valid. Hubungi admin.');
            header('Location: ../pages/login.php');
    }
    exit;

} catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan sistem, coba lagi nanti.');
    header('Location: ../pages/login.php');
    exit;
}
