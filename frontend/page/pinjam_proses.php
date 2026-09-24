<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();
cekAksesPeminjam();

if (!sudahLogin()) {
    setFlash('error', 'Silakan masuk dulu buat mengajukan peminjaman.');
    header('Location: ../../login/pages/login.php');
    exit;
}

$me = currentUser();
if (($me['role'] ?? '') !== 'peminjam') {
    http_response_code(403);
    exit('Akses ditolak.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Permintaan tidak valid, coba lagi.');
    header('Location: ../../index.php');
    exit;
}

$bookId = (int) ($_POST['book_id'] ?? 0);
$jumlah = (int) ($_POST['jumlah'] ?? 0);

if ($bookId <= 0 || $jumlah <= 0) {
    setFlash('error', 'Data peminjaman tidak valid.');
    header('Location: pinjam.php?id=' . $bookId);
    exit;
}

$db = (new Database())->connect();

try {
    $db->beginTransaction();

    // Kunci baris buku biar gak ada race-condition kalau ada pengajuan barengan.
    $stmtBuku = $db->prepare("SELECT id, stok_tersedia FROM books WHERE id = ? AND status = 'aktif' FOR UPDATE");
    $stmtBuku->execute([$bookId]);
    $buku = $stmtBuku->fetch();

    if (!$buku) {
        $db->rollBack();
        setFlash('error', 'Buku tidak ditemukan atau sudah tidak aktif.');
        header('Location: ../../index.php');
        exit;
    }

    if ($jumlah > (int) $buku['stok_tersedia']) {
        $db->rollBack();
        setFlash('error', 'Jumlah melebihi stok tersedia saat ini.');
        header('Location: pinjam.php?id=' . $bookId);
        exit;
    }

    do {
        $kodePeminjaman = 'PJM-' . date('Ymd') . '-' . random_int(1000, 9999);
        $cekKode = $db->prepare('SELECT 1 FROM loans WHERE kode_peminjaman = ? LIMIT 1');
        $cekKode->execute([$kodePeminjaman]);
    } while ($cekKode->fetchColumn());
    $tanggalPinjam = date('Y-m-d');
    $tanggalJatuhTempo = date('Y-m-d', strtotime('+7 days'));

    $stmtLoan = $db->prepare("
        INSERT INTO loans (kode_peminjaman, user_id, tanggal_pengajuan, tanggal_pinjam, tanggal_jatuh_tempo, status, catatan)
        VALUES (?, ?, ?, ?, ?, 'dipinjam', ?)
    ");
    $stmtLoan->execute([
        $kodePeminjaman, $me['id'], $tanggalPinjam, $tanggalPinjam, $tanggalJatuhTempo,
        'Diajukan mandiri oleh peminjam lewat website',
    ]);
    $loanId = $db->lastInsertId();

    $stmtDetail = $db->prepare('INSERT INTO loan_details (loan_id, book_id, jumlah) VALUES (?, ?, ?)');
    $stmtDetail->execute([$loanId, $bookId, $jumlah]);

    $stmtStok = $db->prepare('UPDATE books SET stok_tersedia = stok_tersedia - ? WHERE id = ?');
    $stmtStok->execute([$jumlah, $bookId]);

    $db->commit();
    setFlash('success', "Peminjaman $kodePeminjaman berhasil diajukan. Wajib dikembalikan sebelum $tanggalJatuhTempo.");
    header('Location: riwayat.php');
    exit;
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Pinjam mandiri error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan sistem, coba lagi nanti.');
    header('Location: pinjam.php?id=' . $bookId);
    exit;
}
