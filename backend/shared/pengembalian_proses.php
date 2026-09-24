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
$dendaPerHari = 2000;
$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'ID peminjaman tidak valid.');
    header('Location: index.php');
    exit;
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM loans WHERE id = ? AND status = 'dipinjam' FOR UPDATE");
    $stmt->execute([$id]);
    $loan = $stmt->fetch();

    if (!$loan) {
        $db->rollBack();
        setFlash('error', 'Data peminjaman tidak ditemukan atau sudah dikembalikan.');
        header('Location: index.php');
        exit;
    }

    $today = new DateTime('today');
    $jatuhTempo = new DateTime($loan['tanggal_jatuh_tempo']);
    $telat = $today > $jatuhTempo;
    $hariTelat = $telat ? $today->diff($jatuhTempo)->days : 0;
    $denda = $hariTelat * $dendaPerHari;
    $statusBaru = $telat ? 'terlambat' : 'dikembalikan';

    $stmtUpdate = $db->prepare("
        UPDATE loans SET tanggal_kembali = ?, status = ?, updated_at = NOW() WHERE id = ?
    ");
    $stmtUpdate->execute([$today->format('Y-m-d'), $statusBaru, $id]);

    // Kembalikan stok buku yang terkait peminjaman ini
    $stmtDetail = $db->prepare('SELECT book_id, jumlah FROM loan_details WHERE loan_id = ?');
    $stmtDetail->execute([$id]);
    foreach ($stmtDetail->fetchAll() as $detail) {
        $stmtRestock = $db->prepare('UPDATE books SET stok_tersedia = stok_tersedia + ? WHERE id = ?');
        $stmtRestock->execute([(int) $detail['jumlah'], (int) $detail['book_id']]);
    }

    $db->commit();

    if ($telat) {
        setFlash('success', "Buku dikembalikan dengan telat $hariTelat hari. Denda: Rp" . number_format($denda) . '.');
    } else {
        setFlash('success', 'Buku berhasil dikembalikan tepat waktu.');
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Pengembalian error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan database.');
}

header('Location: index.php');
exit;
