<?php

require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();

if (!sudahLogin()) {
    header('Location: ' . baseUrlPath() . '/login/pages/login.php');
    exit;
}

$me = currentUser();
$role = strtolower(trim((string)($me['role'] ?? '')));

if (!in_array($role, ['admin', 'petugas'], true)) {
    http_response_code(403);
    exit('Akses ditolak.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Permintaan tidak valid.');
    header('Location: ' . baseUrlPath() . '/backend/' . $role . '/peminjaman/index.php');
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
    header('Location: ' . baseUrlPath() . '/backend/' . $role . '/peminjaman/index.php');
    exit;
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));
$loanId = (int)($_POST['loan_id'] ?? 0);

if ($loanId <= 0) {
    setFlash('error', 'ID peminjaman tidak valid.');
    header('Location: ' . baseUrlPath() . '/backend/' . $role . '/peminjaman/index.php');
    exit;
}

if (!in_array($action, ['konfirmasi', 'setujui', 'tolak'], true)) {
    setFlash('error', 'Aksi peminjaman tidak valid.');
    header('Location: ' . baseUrlPath() . '/backend/' . $role . '/peminjaman/index.php');
    exit;
}

$redirect = baseUrlPath() . '/backend/' . $role . '/peminjaman/index.php';
$db = (new Database())->connect();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("
        SELECT
            loans.id,
            loans.status,
            loans.kode_peminjaman,
            loan_details.book_id,
            loan_details.jumlah,
            books.judul,
            books.stok_tersedia
        FROM loans
        INNER JOIN loan_details ON loan_details.loan_id = loans.id
        INNER JOIN books ON books.id = loan_details.book_id
        WHERE loans.id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        $db->rollBack();
        setFlash('error', 'Data peminjaman tidak ditemukan.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($loan['status'] !== 'menunggu') {
        $db->rollBack();
        setFlash('error', 'Peminjaman ini sudah diproses sebelumnya.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'tolak') {
        $namaPetugas = $me['nama'] ?: ($me['username'] ?? 'Petugas');
        $catatan = 'Peminjaman ditolak oleh ' . $namaPetugas . '.';

        $stmt = $db->prepare("
            UPDATE loans
            SET status = 'ditolak', catatan = ?
            WHERE id = ? AND status = 'menunggu'
        ");
        $stmt->execute([$catatan, $loanId]);

        $db->commit();
        setFlash('success', 'Peminjaman berhasil ditolak.');
        header('Location: ' . $redirect);
        exit;
    }

    $jumlah = (int)$loan['jumlah'];
    $stok = (int)$loan['stok_tersedia'];

    if ($jumlah <= 0) {
        $db->rollBack();
        setFlash('error', 'Jumlah buku tidak valid.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($stok < $jumlah) {
        $db->rollBack();
        setFlash('error', 'Stok buku tidak mencukupi.');
        header('Location: ' . $redirect);
        exit;
    }

    $tanggalPinjam = date('Y-m-d');
    $tanggalJatuhTempo = date('Y-m-d', strtotime('+7 days'));
    $namaPetugas = $me['nama'] ?: ($me['username'] ?? 'Petugas');
    $catatan = 'Peminjaman disetujui oleh ' . $namaPetugas . '.';

    $stmtStok = $db->prepare("
        UPDATE books
        SET stok_tersedia = stok_tersedia - ?
        WHERE id = ? AND stok_tersedia >= ?
    ");
    $stmtStok->execute([$jumlah, $loan['book_id'], $jumlah]);

    if ($stmtStok->rowCount() !== 1) {
        $db->rollBack();
        setFlash('error', 'Stok buku tidak mencukupi atau sudah berubah.');
        header('Location: ' . $redirect);
        exit;
    }

    $stmtApprove = $db->prepare("
        UPDATE loans
        SET
            status = 'dipinjam',
            tanggal_pinjam = ?,
            tanggal_jatuh_tempo = ?,
            catatan = ?
        WHERE id = ? AND status = 'menunggu'
    ");
    $stmtApprove->execute([$tanggalPinjam, $tanggalJatuhTempo, $catatan, $loanId]);

    if ($stmtApprove->rowCount() !== 1) {
        $db->rollBack();
        setFlash('error', 'Status peminjaman gagal diperbarui.');
        header('Location: ' . $redirect);
        exit;
    }

    $db->commit();
    setFlash('success', 'Peminjaman berhasil disetujui dan status menjadi dipinjam.');
    header('Location: ' . $redirect);
    exit;

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Peminjaman proses error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan sistem saat memproses peminjaman.');
    header('Location: ' . $redirect);
    exit;
}
