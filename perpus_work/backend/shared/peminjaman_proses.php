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

$user_id = (int) ($_POST['user_id'] ?? 0);
$book_id = (int) ($_POST['book_id'] ?? 0);
$tanggal_pinjam = trim($_POST['tanggal_pinjam'] ?? '');
$tanggal_jatuh_tempo = trim($_POST['tanggal_jatuh_tempo'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');

if ($user_id <= 0 || $book_id <= 0 || $tanggal_pinjam === '' || $tanggal_jatuh_tempo === '') {
    setFlash('error', 'Semua kolom wajib diisi.');
    header('Location: index.php');
    exit;
}

// Validasi tanggal di server, jangan cuma andalin atribut min/max di HTML (gampang dibypass).
$today = new DateTime('today');
$pinjamDate = DateTime::createFromFormat('!Y-m-d', $tanggal_pinjam) ?: null;
$kembaliDate = DateTime::createFromFormat('!Y-m-d', $tanggal_jatuh_tempo) ?: null;

if (
    !$pinjamDate || !$kembaliDate ||
    $pinjamDate->format('Y-m-d') !== $tanggal_pinjam ||
    $kembaliDate->format('Y-m-d') !== $tanggal_jatuh_tempo
) {
    setFlash('error', 'Format tanggal tidak valid.');
    header('Location: index.php');
    exit;
}

if ($pinjamDate < $today) {
    setFlash('error', 'Tanggal pinjam gak boleh sebelum hari ini.');
    header('Location: index.php');
    exit;
}

$maxKembali = (clone $pinjamDate)->modify('+7 days');
if ($kembaliDate < $pinjamDate || $kembaliDate > $maxKembali) {
    setFlash('error', 'Tanggal pengembalian harus dalam rentang 1–7 hari setelah tanggal pinjam.');
    header('Location: index.php');
    exit;
}

try {
    $db->beginTransaction();

    // Kunci baris buku biar gak race-condition kalau ada 2 transaksi barengan
    $stmtBuku = $db->prepare('SELECT stok_tersedia FROM books WHERE id = ? FOR UPDATE');
    $stmtBuku->execute([$book_id]);
    $buku = $stmtBuku->fetch();

    if (!$buku || (int) $buku['stok_tersedia'] <= 0) {
        $db->rollBack();
        setFlash('error', 'Stok buku ini sudah habis.');
        header('Location: index.php');
        exit;
    }

    $kode_peminjaman = 'PJM-' . date('Ymd') . '-' . mt_rand(1000, 9999);

    $stmtLoan = $db->prepare("
        INSERT INTO loans (kode_peminjaman, user_id, tanggal_pengajuan, tanggal_pinjam, tanggal_jatuh_tempo, status, catatan)
        VALUES (?, ?, ?, ?, ?, 'dipinjam', ?)
    ");
    $stmtLoan->execute([$kode_peminjaman, $user_id, date('Y-m-d'), $tanggal_pinjam, $tanggal_jatuh_tempo, $catatan ?: null]);
    $loanId = $db->lastInsertId();

    $stmtDetail = $db->prepare('INSERT INTO loan_details (loan_id, book_id, jumlah) VALUES (?, ?, 1)');
    $stmtDetail->execute([$loanId, $book_id]);

    $stmtStok = $db->prepare('UPDATE books SET stok_tersedia = stok_tersedia - 1 WHERE id = ?');
    $stmtStok->execute([$book_id]);

    $db->commit();
    setFlash('success', "Peminjaman $kode_peminjaman berhasil dicatat.");
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Peminjaman error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan database.');
}

header('Location: index.php');
exit;
