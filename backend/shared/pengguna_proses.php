<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
cekRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Permintaan tidak valid.');
    header('Location: index.php');
    exit;
}

$db = (new Database())->connect();
$action = $_POST['action'] ?? '';
$me = currentUser();

try {
    if ($action === 'create') {
        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['admin', 'petugas', 'peminjam'], true) ? $_POST['role'] : 'peminjam';

        if ($nama === '' || $username === '' || $email === '' || $password === '') {
            setFlash('error', 'Semua kolom wajib diisi.');
            header('Location: index.php');
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Format email tidak valid.');
            header('Location: index.php');
            exit;
        }

        $cek = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $cek->execute([$username, $email]);
        if ($cek->fetch()) {
            setFlash('error', 'Username atau email sudah dipakai.');
            header('Location: index.php');
            exit;
        }

        $stmt = $db->prepare('INSERT INTO users (nama, username, email, password, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$nama, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        setFlash('success', 'Pengguna berhasil ditambahkan.');

    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['admin', 'petugas', 'peminjam'], true) ? $_POST['role'] : 'peminjam';

        if ($id <= 0 || $nama === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Data tidak valid.');
            header('Location: index.php');
            exit;
        }

        $cek = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $cek->execute([$email, $id]);
        if ($cek->fetch()) {
            setFlash('error', 'Email sudah dipakai akun lain.');
            header('Location: index.php');
            exit;
        }

        if ($password !== '') {
            $stmt = $db->prepare('UPDATE users SET nama = ?, email = ?, password = ?, role = ? WHERE id = ?');
            $stmt->execute([$nama, $email, password_hash($password, PASSWORD_DEFAULT), $role, $id]);
        } else {
            $stmt = $db->prepare('UPDATE users SET nama = ?, email = ?, role = ? WHERE id = ?');
            $stmt->execute([$nama, $email, $role, $id]);
        }
        setFlash('success', 'Pengguna berhasil diperbarui.');

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === (int) $me['id']) {
            setFlash('error', 'Gak bisa hapus akun sendiri.');
            header('Location: index.php');
            exit;
        }

        $cekPinjam = $db->prepare('SELECT COUNT(*) c FROM loans WHERE user_id = ?');
        $cekPinjam->execute([$id]);
        if ((int) $cekPinjam->fetch()['c'] > 0) {
            setFlash('error', 'Pengguna ini punya riwayat peminjaman, gak bisa dihapus.');
            header('Location: index.php');
            exit;
        }

        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Pengguna berhasil dihapus.');
    } else {
        setFlash('error', 'Aksi tidak dikenali.');
    }
} catch (PDOException $e) {
    error_log('Pengguna error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan database.');
}

header('Location: index.php');
exit;
