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
        // Akun admin gak dibuat/diubah dari halaman ini — cuma petugas & peminjam.
        $role = in_array($_POST['role'] ?? '', ['petugas', 'peminjam'], true) ? $_POST['role'] : 'peminjam';

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

        // Ambil role ASLI dari database dulu — jangan percaya field tersembunyi/hasil tampering
        // dari form. Akun admin gak boleh diubah lewat halaman ini sama sekali.
        $cekTarget = $db->prepare('SELECT role FROM users WHERE id = ?');
        $cekTarget->execute([$id]);
        $target = $cekTarget->fetch();

        if (!$target || $target['role'] === 'admin') {
            setFlash('error', 'Pengguna tidak ditemukan atau tidak bisa diubah dari halaman ini.');
            header('Location: index.php');
            exit;
        }

        $role = in_array($_POST['role'] ?? '', ['petugas', 'peminjam'], true) ? $_POST['role'] : $target['role'];

        // Akun peminjam: data pribadinya (nama, email, password) mereka input sendiri saat
        // registrasi, jadi admin cuma boleh ubah role-nya lewat halaman ini — bukan datanya.
        // Ini mencegah bypass kalau ada yang coba kirim ulang form dengan field yang dikunci di UI.
        if ($target['role'] === 'peminjam') {
            $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$role, $id]);
            setFlash('success', 'Peran pengguna berhasil diperbarui.');
            header('Location: index.php');
            exit;
        }

        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

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

        $cekTargetHapus = $db->prepare('SELECT role FROM users WHERE id = ?');
        $cekTargetHapus->execute([$id]);
        $targetHapus = $cekTargetHapus->fetch();
        if (!$targetHapus || $targetHapus['role'] === 'admin') {
            setFlash('error', 'Pengguna tidak ditemukan atau tidak bisa dihapus dari halaman ini.');
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
