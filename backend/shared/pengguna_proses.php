<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();
cekRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Permintaan tidak valid.');
    header('Location: index.php');
    exit;
}

$db = (new Database())->connect();
$action = $_POST['action'] ?? '';

function validateNik(?string $nik, bool $required): ?string
{
    $nik = preg_replace('/\D+/', '', (string) $nik);
    if ($nik === '' && !$required) {
        return null;
    }
    if (!preg_match('/^\d{16}$/', $nik)) {
        throw new InvalidArgumentException('NIK harus terdiri dari 16 digit angka.');
    }
    return $nik;
}

try {
    if ($action === 'create') {
        $nama = trim($_POST['nama'] ?? '');
        $nik = validateNik($_POST['nik'] ?? '', ($_POST['role'] ?? 'peminjam') === 'peminjam');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['petugas', 'peminjam'], true) ? $_POST['role'] : 'peminjam';

        if ($nama === '' || $username === '' || $email === '' || $password === '') {
            throw new InvalidArgumentException('Semua kolom wajib diisi.');
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            throw new InvalidArgumentException('Username hanya boleh huruf, angka, underscore, minimal 3 karakter.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Format email tidak valid.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password minimal 8 karakter.');
        }

        $cek = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ? OR (nik IS NOT NULL AND nik = ?) LIMIT 1');
        $cek->execute([$username, $email, $nik]);
        if ($cek->fetch()) {
            throw new InvalidArgumentException('Username, email, atau NIK sudah dipakai.');
        }

        $stmt = $db->prepare('
            INSERT INTO users (nama, nik, username, email, password, role)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $nama,
            $nik,
            $username,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role
        ]);

        setFlash('success', 'Pengguna berhasil ditambahkan.');
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nikInput = trim($_POST['nik'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['petugas', 'peminjam'], true) ? $_POST['role'] : 'peminjam';

        if ($id <= 0 || $nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Data pengguna tidak valid.');
        }

        $targetStmt = $db->prepare('SELECT id, username, role, nik FROM users WHERE id = ? LIMIT 1');
        $targetStmt->execute([$id]);
        $target = $targetStmt->fetch();

        if (!$target || $target['role'] === 'admin') {
            throw new InvalidArgumentException('Akun admin tidak dapat diubah dari halaman ini.');
        }

        $nik = validateNik($nikInput, $role === 'peminjam');

        $cek = $db->prepare('
            SELECT id
            FROM users
            WHERE (email = ? OR (nik IS NOT NULL AND nik = ?))
              AND id != ?
            LIMIT 1
        ');
        $cek->execute([$email, $nik, $id]);
        if ($cek->fetch()) {
            throw new InvalidArgumentException('Email atau NIK sudah dipakai akun lain.');
        }

        if ($password !== '' && strlen($password) < 8) {
            throw new InvalidArgumentException('Password baru minimal 8 karakter.');
        }

        if ($password !== '') {
            $stmt = $db->prepare('
                UPDATE users
                SET nama = ?, nik = ?, email = ?, password = ?, role = ?
                WHERE id = ?
            ');
            $stmt->execute([$nama, $nik, $email, password_hash($password, PASSWORD_DEFAULT), $role, $id]);
        } else {
            $stmt = $db->prepare('
                UPDATE users
                SET nama = ?, nik = ?, email = ?, role = ?
                WHERE id = ?
            ');
            $stmt->execute([$nama, $nik, $email, $role, $id]);
        }

        setFlash('success', 'Pengguna berhasil diperbarui.');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $me = currentUser();

        if ($id <= 0 || $id === (int) ($me['id'] ?? 0)) {
            throw new InvalidArgumentException('Akun sendiri tidak dapat dihapus.');
        }

        $cekTarget = $db->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $cekTarget->execute([$id]);
        $target = $cekTarget->fetch();

        if (!$target || $target['role'] === 'admin') {
            throw new InvalidArgumentException('Pengguna tidak ditemukan atau tidak bisa dihapus.');
        }

        $cekPinjam = $db->prepare('SELECT COUNT(*) FROM loans WHERE user_id = ?');
        $cekPinjam->execute([$id]);
        if ((int) $cekPinjam->fetchColumn() > 0) {
            throw new InvalidArgumentException('Pengguna punya riwayat peminjaman. Nonaktifkan akun jika perlu, jangan dihapus.');
        }

        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Pengguna berhasil dihapus.');
    } else {
        throw new InvalidArgumentException('Aksi tidak dikenali.');
    }
} catch (InvalidArgumentException $e) {
    setFlash('error', $e->getMessage());
} catch (PDOException $e) {
    error_log('Pengguna error: ' . $e->getMessage());
    setFlash('error', ((int) ($e->errorInfo[1] ?? 0) === 1062)
        ? 'Username, email, atau NIK sudah dipakai.'
        : 'Terjadi kesalahan database.');
}

header('Location: index.php');
exit;
