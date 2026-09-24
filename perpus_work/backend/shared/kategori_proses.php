<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();

$routeRole = null;
if (preg_match('#/backend/(admin|petugas)(?:/|$)#i', str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), $match)) {
    $routeRole = strtolower($match[1]);
}

if ($routeRole !== null) {
    cekRole([$routeRole]);
} else {
    cekRole(['admin', 'petugas']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Permintaan tidak valid.');
    header('Location: index.php');
    exit;
}

$db = (new Database())->connect();
$action = $_POST['action'] ?? '';

function slugifyKategori(string $text): string
{
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text)));
}

try {
    if ($action === 'create' || $action === 'update') {
        $nama = trim($_POST['nama_kategori'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';

        if ($nama === '') {
            setFlash('error', 'Nama kategori wajib diisi.');
            header('Location: index.php');
            exit;
        }

        $slug = slugifyKategori($nama);

        if ($action === 'create') {
            $cek = $db->prepare('SELECT id FROM categories WHERE nama_kategori = ? OR slug = ?');
            $cek->execute([$nama, $slug]);
            if ($cek->fetch()) {
                setFlash('error', 'Kategori dengan nama itu sudah ada.');
                header('Location: index.php');
                exit;
            }

            $stmt = $db->prepare('INSERT INTO categories (nama_kategori, slug, deskripsi, status) VALUES (?, ?, ?, ?)');
            $stmt->execute([$nama, $slug, $deskripsi ?: null, $status]);
            setFlash('success', 'Kategori berhasil ditambahkan.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                setFlash('error', 'ID kategori tidak valid.');
                header('Location: index.php');
                exit;
            }

            $cek = $db->prepare('SELECT id FROM categories WHERE (nama_kategori = ? OR slug = ?) AND id != ?');
            $cek->execute([$nama, $slug, $id]);
            if ($cek->fetch()) {
                setFlash('error', 'Kategori dengan nama itu sudah dipakai kategori lain.');
                header('Location: index.php');
                exit;
            }

            $stmt = $db->prepare('UPDATE categories SET nama_kategori = ?, slug = ?, deskripsi = ?, status = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$nama, $slug, $deskripsi ?: null, $status, $id]);
            setFlash('success', 'Kategori berhasil diperbarui.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $cekBuku = $db->prepare('SELECT COUNT(*) c FROM books WHERE category_id = ?');
        $cekBuku->execute([$id]);
        if ((int) $cekBuku->fetch()['c'] > 0) {
            setFlash('error', 'Kategori ini masih dipakai oleh buku, gak bisa dihapus. Ubah jadi Nonaktif aja.');
            header('Location: index.php');
            exit;
        }

        $stmt = $db->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Kategori berhasil dihapus.');
    } else {
        setFlash('error', 'Aksi tidak dikenali.');
    }
} catch (PDOException $e) {
    error_log('Kategori error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan database.');
}

header('Location: index.php');
exit;
