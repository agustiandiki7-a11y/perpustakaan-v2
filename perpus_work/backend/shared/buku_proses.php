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
$rootPath = dirname(__DIR__, 2); // -> root project, biar cover path selalu sama persis dengan yang dipakai frontend

function handleCoverUpload(string $rootPath): array
{
    // return [path_relatif_atau_null, error_message_atau_null]
    if (!isset($_FILES['cover']) || $_FILES['cover']['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    if ($_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Gagal mengunggah file cover.'];
    }

    $fileTmpPath = $_FILES['cover']['tmp_name'];
    $fileSize = $_FILES['cover']['size'];
    $fileExtension = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png'];

    if (!in_array($fileExtension, $allowedExtensions, true)) {
        return [null, 'Format cover harus JPG, JPEG, atau PNG.'];
    }

    if ($fileSize > 2 * 1024 * 1024) {
        return [null, 'Ukuran cover maksimal 2MB.'];
    }

    $newFileName = 'cover_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $fileExtension;
    $uploadDir = $rootPath . '/assets/uploads/cover/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
        return [null, 'Gagal menyimpan file cover ke server.'];
    }

    // Path disimpan relatif dari ROOT project, biar frontend & backend nunjuk ke file yang sama persis.
    return ['assets/uploads/cover/' . $newFileName, null];
}

try {
    if ($action === 'create' || $action === 'update') {
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $kode_buku = trim($_POST['kode_buku'] ?? '');
        $judul = trim($_POST['judul'] ?? '');
        $penulis = trim($_POST['penulis'] ?? '');
        $penerbit = trim($_POST['penerbit'] ?? '');
        $tahun_terbit = trim($_POST['tahun_terbit'] ?? '');
        $jumlah_stok = (int) ($_POST['jumlah_stok'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';

        // Validasi tahun di server, jangan cuma andalin atribut min/max di HTML (gampang dibypass)
        if ($tahun_terbit !== '' && (!ctype_digit($tahun_terbit) || (int) $tahun_terbit < 1900 || (int) $tahun_terbit > (int) date('Y') + 1)) {
            setFlash('error', 'Tahun terbit tidak valid.');
            header('Location: index.php');
            exit;
        }

        if ($category_id <= 0 || $kode_buku === '' || $judul === '' || $penulis === '' || $jumlah_stok < 0) {
            setFlash('error', 'Lengkapi semua kolom wajib dengan benar.');
            header('Location: index.php');
            exit;
        }

        [$coverPath, $coverError] = handleCoverUpload($rootPath);
        if ($coverError) {
            setFlash('error', $coverError);
            header('Location: index.php');
            exit;
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $judul))) . '-' . time();

        if ($action === 'create') {
            $stmt = $db->prepare("
                INSERT INTO books (category_id, kode_buku, judul, slug, penulis, penerbit, tahun_terbit, jumlah_stok, stok_tersedia, cover, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $category_id, $kode_buku, $judul, $slug, $penulis,
                $penerbit ?: null, $tahun_terbit ?: null, $jumlah_stok, $jumlah_stok,
                $coverPath, $status,
            ]);
            setFlash('success', 'Buku berhasil ditambahkan.');
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                setFlash('error', 'ID buku tidak valid.');
                header('Location: index.php');
                exit;
            }

            // Hitung ulang stok tersedia: selisih stok lama vs baru ditambahkan/dikurangi ke stok tersedia
            $stmtLama = $db->prepare('SELECT jumlah_stok, stok_tersedia, cover FROM books WHERE id = ?');
            $stmtLama->execute([$id]);
            $lama = $stmtLama->fetch();

            if (!$lama) {
                setFlash('error', 'Data buku tidak ditemukan.');
                header('Location: index.php');
                exit;
            }

            $selisih = $jumlah_stok - (int) $lama['jumlah_stok'];
            $stokTersediaBaru = max(0, (int) $lama['stok_tersedia'] + $selisih);
            $coverFinal = $coverPath ?? $lama['cover'];

            $stmt = $db->prepare("
                UPDATE books SET category_id = ?, kode_buku = ?, judul = ?, penulis = ?, penerbit = ?,
                    tahun_terbit = ?, jumlah_stok = ?, stok_tersedia = ?, cover = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $category_id, $kode_buku, $judul, $penulis, $penerbit ?: null,
                $tahun_terbit ?: null, $jumlah_stok, $stokTersediaBaru, $coverFinal, $status, $id,
            ]);
            setFlash('success', 'Buku berhasil diperbarui.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $cekPinjam = $db->prepare("SELECT COUNT(*) c FROM loan_details WHERE book_id = ?");
        $cekPinjam->execute([$id]);
        if ((int) $cekPinjam->fetch()['c'] > 0) {
            setFlash('error', 'Buku ini punya riwayat peminjaman, gak bisa dihapus. Ubah jadi Nonaktif aja.');
            header('Location: index.php');
            exit;
        }

        $stmt = $db->prepare('DELETE FROM books WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('success', 'Buku berhasil dihapus.');
    } else {
        setFlash('error', 'Aksi tidak dikenali.');
    }
} catch (PDOException $e) {
    error_log('Buku error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan database.');
}

header('Location: index.php');
exit;
