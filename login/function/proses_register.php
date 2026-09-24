<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/register.php');
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Sesi form sudah kadaluarsa, silakan coba lagi.');
    header('Location: ../pages/register.php');
    exit;
}

$nama = trim($_POST['nama'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

$_SESSION['old_register'] = ['nama' => $nama, 'username' => $username, 'email' => $email];

// --- Validasi input, semua dicek di server (jangan percaya validasi HTML doang) ---
if ($nama === '' || $username === '' || $email === '' || $password === '' || $passwordConfirm === '') {
    setFlash('error', 'Semua kolom wajib diisi.');
    header('Location: ../pages/register.php');
    exit;
}

if (mb_strlen($nama) > 150 || mb_strlen($username) > 50 || mb_strlen($email) > 150) {
    setFlash('error', 'Ada input yang kepanjangan.');
    header('Location: ../pages/register.php');
    exit;
}

if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
    setFlash('error', 'Username cuma boleh huruf, angka, underscore, minimal 3 karakter.');
    header('Location: ../pages/register.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setFlash('error', 'Format email tidak valid.');
    header('Location: ../pages/register.php');
    exit;
}

if (strlen($password) < 8) {
    setFlash('error', 'Password minimal 8 karakter.');
    header('Location: ../pages/register.php');
    exit;
}

if (!hash_equals($password, $passwordConfirm)) {
    setFlash('error', 'Konfirmasi password tidak cocok.');
    header('Location: ../pages/register.php');
    exit;
}

try {
    $db = (new Database())->connect();

    $cek = $db->prepare('SELECT id FROM users WHERE username = :username OR email = :email');
    $cek->execute([':username' => $username, ':email' => $email]);
    if ($cek->fetch()) {
        setFlash('error', 'Username atau email sudah terdaftar.');
        header('Location: ../pages/register.php');
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare('INSERT INTO users (nama, username, email, password, role) VALUES (:nama, :username, :email, :password, :role)');
    $stmt->execute([
        ':nama'     => $nama,
        ':username' => $username,
        ':email'    => $email,
        ':password' => $hashed,
        ':role'     => 'peminjam',
    ]);

    $newUser = [
        'id'       => $db->lastInsertId(),
        'nama'     => $nama,
        'username' => $username,
        'email'    => $email,
        'role'     => 'peminjam',
    ];

    unset($_SESSION['old_register']);
    loginUser($newUser);

    setFlash('success', 'Akun berhasil dibuat, selamat datang!');
    header('Location: ../../index.php');
    exit;

} catch (PDOException $e) {
    error_log('Register error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan sistem, coba lagi nanti.');
    header('Location: ../pages/register.php');
    exit;
}
