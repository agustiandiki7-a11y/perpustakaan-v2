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

$redirect = baseUrlPath() . '/backend/' . ($routeRole ?: (currentUser()['role'] ?? 'petugas')) . '/pengembalian/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Permintaan tidak valid.');
    header('Location: ' . $redirect);
    exit;
}


if (($_POST['action'] ?? '') === 'bayar_denda') {
    $loanId = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
    if (!$loanId || $loanId <= 0) {
        setFlash('error', 'ID transaksi denda tidak valid.');
        header('Location: ' . $redirect);
        exit;
    }

    $db = (new Database())->connect();
    try {
        $stmt = $db->prepare("
            UPDATE loans
            SET denda_dibayar = 1, updated_at = NOW()
            WHERE id = ? AND denda_total > 0 AND denda_dibayar = 0
        ");
        $stmt->execute([$loanId]);

        setFlash('success', $stmt->rowCount() === 1
            ? 'Denda berhasil ditandai sebagai lunas.'
            : 'Denda tidak ditemukan atau sudah lunas.');
    } catch (PDOException $e) {
        error_log('Bayar denda error: ' . $e->getMessage());
        setFlash('error', 'Denda gagal diperbarui.');
    }

    header('Location: ' . $redirect);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$kondisi = $_POST['kondisi_buku'] ?? '';
$dendaKerusakan = max(0, (float) ($_POST['denda_kerusakan'] ?? 0));

if (!$id || $id <= 0 || !in_array($kondisi, ['baik', 'rusak_ringan', 'rusak_berat'], true)) {
    setFlash('error', 'Data pengembalian tidak valid.');
    header('Location: ' . $redirect);
    exit;
}

if (!isset($_FILES['bukti_pengembalian']) || $_FILES['bukti_pengembalian']['error'] !== UPLOAD_ERR_OK) {
    setFlash('error', 'Bukti foto pengembalian wajib diunggah.');
    header('Location: ' . $redirect . '?return=' . $id);
    exit;
}

$file = $_FILES['bukti_pengembalian'];
if ((int) $file['size'] > 5 * 1024 * 1024) {
    setFlash('error', 'Ukuran bukti foto maksimal 5MB.');
    header('Location: ' . $redirect . '?return=' . $id);
    exit;
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array($extension, $allowed, true)) {
    setFlash('error', 'Format bukti foto harus JPG, JPEG, PNG, atau WEBP.');
    header('Location: ' . $redirect . '?return=' . $id);
    exit;
}

if (@getimagesize($file['tmp_name']) === false) {
    setFlash('error', 'File yang diunggah bukan gambar yang valid.');
    header('Location: ' . $redirect . '?return=' . $id);
    exit;
}

$db = (new Database())->connect();
$rootPath = dirname(__DIR__, 2);
$uploadDir = $rootPath . '/assets/uploads/returns/';

if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    setFlash('error', 'Folder bukti pengembalian tidak dapat dibuat.');
    header('Location: ' . $redirect . '?return=' . $id);
    exit;
}

$fileName = 'return_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
$relativePath = 'assets/uploads/returns/' . $fileName;
$targetPath = $rootPath . '/' . $relativePath;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    setFlash('error', 'Bukti foto gagal disimpan.');
    header('Location: ' . $redirect . '?return=' . $id);
    exit;
}

$dendaPerHari = 2000;

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM loans WHERE id = ? AND status = 'dipinjam' FOR UPDATE");
    $stmt->execute([$id]);
    $loan = $stmt->fetch();

    if (!$loan) {
        $db->rollBack();
        @unlink($targetPath);
        setFlash('error', 'Peminjaman tidak ditemukan atau sudah dikembalikan.');
        header('Location: ' . $redirect);
        exit;
    }

    $today = new DateTime('today');
    $jatuhTempo = new DateTime($loan['tanggal_jatuh_tempo']);
    $hariTelat = $today > $jatuhTempo ? $today->diff($jatuhTempo)->days : 0;
    $dendaKeterlambatan = $hariTelat * $dendaPerHari;
    $dendaTotal = $dendaKeterlambatan + $dendaKerusakan;
    $statusBaru = $hariTelat > 0 ? 'terlambat' : 'dikembalikan';

    $stmtUpdate = $db->prepare("
        UPDATE loans
        SET tanggal_kembali = ?,
            status = ?,
            bukti_pengembalian = ?,
            kondisi_buku = ?,
            denda_keterlambatan = ?,
            denda_kerusakan = ?,
            denda_total = ?,
            denda_dibayar = 0,
            updated_at = NOW()
        WHERE id = ? AND status = 'dipinjam'
    ");
    $stmtUpdate->execute([
        $today->format('Y-m-d'),
        $statusBaru,
        $relativePath,
        $kondisi,
        $dendaKeterlambatan,
        $dendaKerusakan,
        $dendaTotal,
        $id
    ]);

    if ($stmtUpdate->rowCount() !== 1) {
        throw new RuntimeException('Status pengembalian gagal diperbarui.');
    }

    $stmtDetail = $db->prepare('SELECT book_id, jumlah FROM loan_details WHERE loan_id = ?');
    $stmtDetail->execute([$id]);

    foreach ($stmtDetail->fetchAll() as $detail) {
        $stmtRestock = $db->prepare('
            UPDATE books
            SET stok_tersedia = LEAST(jumlah_stok, stok_tersedia + ?)
            WHERE id = ?
        ');
        $stmtRestock->execute([(int) $detail['jumlah'], (int) $detail['book_id']]);
    }

    $db->commit();

    if ($dendaTotal > 0) {
        setFlash('success', 'Pengembalian berhasil dicatat. Total denda Rp' . number_format($dendaTotal, 0, ',', '.') . ' dan statusnya belum dibayar.');
    } else {
        setFlash('success', 'Pengembalian berhasil dicatat dengan bukti foto.');
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    @unlink($targetPath);
    error_log('Pengembalian error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan saat memproses pengembalian.');
}

header('Location: ' . $redirect);
exit;
