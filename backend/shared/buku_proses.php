<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();

$routeRole = null;
if (preg_match('#/backend/(admin|petugas)(?:/|$)#i', str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), $match)) {
    $routeRole = strtolower($match[1]);
}
cekRole($routeRole ? [$routeRole] : ['admin', 'petugas']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Permintaan tidak valid.');
    header('Location: index.php');
    exit;
}

$db = (new Database())->connect();
$action = $_POST['action'] ?? '';
$rootPath = dirname(__DIR__, 2);

function handleCoverUpload(string $rootPath): array
{
    if (!isset($_FILES['cover']) || $_FILES['cover']['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    if ($_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Gagal mengunggah file cover.'];
    }

    $extension = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        return [null, 'Format cover harus JPG, JPEG, atau PNG.'];
    }

    if ((int) $_FILES['cover']['size'] > 10 * 1024 * 1024) {
        return [null, 'Ukuran file cover maksimal 10MB.'];
    }

    $uploadDir = $rootPath . '/assets/uploads/cover/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return [null, 'Folder upload cover tidak dapat dibuat.'];
    }

    $fileName = 'cover_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    if (!move_uploaded_file($_FILES['cover']['tmp_name'], $uploadDir . $fileName)) {
        return [null, 'Gagal menyimpan file cover ke server.'];
    }

    return ['assets/uploads/cover/' . $fileName, null];
}

function generateBookCode(PDO $db): string
{
    $stmt = $db->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(kode_buku, 4) AS UNSIGNED)), 0)
        FROM books
        WHERE kode_buku LIKE 'BK-%'
    ");
    $next = ((int) $stmt->fetchColumn()) + 1;
    return 'BK-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

try {
    if ($action === 'create' || $action === 'update') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $penulis = trim($_POST['penulis'] ?? '');
        $penerbit = trim($_POST['penerbit'] ?? '');
        $tahunTerbit = trim($_POST['tahun_terbit'] ?? '');
        $jumlahStok = (int) ($_POST['jumlah_stok'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';

        if ($tahunTerbit !== '' && (!ctype_digit($tahunTerbit) || (int) $tahunTerbit < 1900 || (int) $tahunTerbit > (int) date('Y') + 1)) {
            setFlash('error', 'Tahun terbit tidak valid.');
            header('Location: index.php');
            exit;
        }

        if ($categoryId <= 0 || $judul === '' || $penulis === '' || $jumlahStok < 0) {
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

        $db->beginTransaction();

        if ($action === 'create') {
            // Buku dengan judul yang sama memakai kode yang sama dan stok ditambah.
            $stmtExisting = $db->prepare("
                SELECT id, kode_buku, jumlah_stok, stok_tersedia, cover
                FROM books
                WHERE LOWER(TRIM(judul)) = LOWER(TRIM(?))
                LIMIT 1
                FOR UPDATE
            ");
            $stmtExisting->execute([$judul]);
            $existing = $stmtExisting->fetch();

            if ($existing) {
                $newTotal = (int) $existing['jumlah_stok'] + $jumlahStok;
                $newAvailable = (int) $existing['stok_tersedia'] + $jumlahStok;
                $coverFinal = $coverPath ?? $existing['cover'];

                $stmt = $db->prepare("
                    UPDATE books
                    SET category_id = ?, penulis = ?, penerbit = ?, tahun_terbit = ?,
                        jumlah_stok = ?, stok_tersedia = ?, cover = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $categoryId, $penulis, $penerbit ?: null, $tahunTerbit ?: null,
                    $newTotal, $newAvailable, $coverFinal, $status, $existing['id']
                ]);

                $db->commit();
                setFlash('success', 'Judul buku sudah ada. Stok berhasil ditambahkan ke ' . $existing['kode_buku'] . '.');
                header('Location: index.php');
                exit;
            }

            $kodeBuku = generateBookCode($db);
            $slugBase = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $judul)));
            $slug = trim($slugBase, '-') . '-' . bin2hex(random_bytes(3));

            $stmt = $db->prepare("
                INSERT INTO books
                (category_id, kode_buku, judul, slug, penulis, penerbit, tahun_terbit,
                 jumlah_stok, stok_tersedia, cover, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $categoryId, $kodeBuku, $judul, $slug, $penulis,
                $penerbit ?: null, $tahunTerbit ?: null, $jumlahStok, $jumlahStok,
                $coverPath, $status
            ]);

            $db->commit();
            setFlash('success', "Buku berhasil ditambahkan dengan kode {$kodeBuku}.");
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                $db->rollBack();
                setFlash('error', 'ID buku tidak valid.');
                header('Location: index.php');
                exit;
            }

            $stmtLama = $db->prepare('SELECT jumlah_stok, stok_tersedia, cover, kode_buku FROM books WHERE id = ? FOR UPDATE');
            $stmtLama->execute([$id]);
            $lama = $stmtLama->fetch();

            if (!$lama) {
                $db->rollBack();
                setFlash('error', 'Data buku tidak ditemukan.');
                header('Location: index.php');
                exit;
            }

            $selisih = $jumlahStok - (int) $lama['jumlah_stok'];
            $stokTersediaBaru = max(0, (int) $lama['stok_tersedia'] + $selisih);
            if ($stokTersediaBaru > $jumlahStok) {
                $stokTersediaBaru = $jumlahStok;
            }

            $coverFinal = $coverPath ?? $lama['cover'];

            $stmt = $db->prepare("
                UPDATE books
                SET category_id = ?, judul = ?, penulis = ?, penerbit = ?,
                    tahun_terbit = ?, jumlah_stok = ?, stok_tersedia = ?, cover = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $categoryId, $judul, $penulis, $penerbit ?: null,
                $tahunTerbit ?: null, $jumlahStok, $stokTersediaBaru,
                $coverFinal, $status, $id
            ]);

            $db->commit();
            setFlash('success', "Buku {$lama['kode_buku']} berhasil diperbarui.");
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            setFlash('error', 'ID buku tidak valid.');
            header('Location: index.php');
            exit;
        }

        $cekPinjam = $db->prepare("SELECT COUNT(*) FROM loan_details WHERE book_id = ?");
        $cekPinjam->execute([$id]);
        if ((int) $cekPinjam->fetchColumn() > 0) {
            setFlash('error', 'Buku ini punya riwayat peminjaman. Ubah menjadi Nonaktif saja.');
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
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Buku error: ' . $e->getMessage());

    if ((int) $e->errorInfo[1] === 1062) {
        setFlash('error', 'Data buku sudah ada. Sistem mencegah duplikasi kode.');
    } else {
        setFlash('error', 'Terjadi kesalahan database saat menyimpan buku.');
    }
}

header('Location: index.php');
exit;
