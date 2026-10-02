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
$nik = preg_replace('/\D+/', '', (string) ($_POST['nik'] ?? ''));
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

$_SESSION['old_register'] = [
    'nama' => $nama,
    'nik' => $nik,
    'username' => $username,
    'email' => $email
];

if ($nama === '' || $nik === '' || $username === '' || $email === '' || $password === '' || $passwordConfirm === '') {
    setFlash('error', 'Semua kolom wajib diisi.');
    header('Location: ../pages/register.php');
    exit;
}

if (mb_strlen($nama) > 150 || mb_strlen($nik) !== 16 || mb_strlen($username) > 50 || mb_strlen($email) > 150) {
    setFlash('error', 'Data pendaftaran tidak valid.');
    header('Location: ../pages/register.php');
    exit;
}

if (!preg_match('/^\d{16}$/', $nik)) {
    setFlash('error', 'NIK harus terdiri dari 16 digit angka.');
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

    $cek = $db->prepare('SELECT id, nik FROM users WHERE username = :username OR email = :email OR nik = :nik LIMIT 1');
    $cek->execute([
        ':username' => $username,
        ':email' => $email,
        ':nik' => $nik
    ]);
    $existing = $cek->fetch();

    if ($existing) {
        setFlash('error', $existing['nik'] === $nik ? 'NIK sudah terdaftar. Gunakan akun yang sudah ada.' : 'Username atau email sudah terdaftar.');
        header('Location: ../pages/register.php');
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO users (nama, nik, username, email, password, role)
        VALUES (:nama, :nik, :username, :email, :password, 'peminjam')
    ");
    $stmt->execute([
        ':nama' => $nama,
        ':nik' => $nik,
        ':username' => $username,
        ':email' => $email,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    $newUser = [
        'id' => $db->lastInsertId(),
        'nama' => $nama,
        'username' => $username,
        'email' => $email,
        'role' => 'peminjam',
    ];

    unset($_SESSION['old_register']);
    loginUser($newUser);

    setFlash('success', 'Akun berhasil dibuat, selamat datang!');
    header('Location: ../../index.php');
    exit;
} catch (PDOException $e) {
    error_log('Register error: ' . $e->getMessage());
    setFlash('error', ((int) ($e->errorInfo[1] ?? 0) === 1062)
        ? 'NIK, username, atau email sudah terdaftar.'
        : 'Terjadi kesalahan sistem, coba lagi nanti.');
    header('Location: ../pages/register.php');
    exit;
}
