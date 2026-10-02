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

$redirect = baseUrlPath() . '/backend/' . ($routeRole ?: (currentUser()['role'] ?? 'petugas')) . '/peminjaman/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Permintaan tidak valid.');
    header('Location: ' . $redirect);
    exit;
}

$db = (new Database())->connect();
$action = strtolower(trim((string) ($_POST['action'] ?? '')));

try {
    if ($action === 'create') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $bookId = (int) ($_POST['book_id'] ?? 0);
        $jumlah = (int) ($_POST['jumlah'] ?? 1);

        if ($userId <= 0 || $bookId <= 0 || $jumlah <= 0) {
            setFlash('error', 'Data peminjaman manual tidak valid.');
            header('Location: ' . $redirect);
            exit;
        }

        $db->beginTransaction();

        $stmtUser = $db->prepare("SELECT id, nama FROM users WHERE id = ? AND role = 'peminjam' LIMIT 1");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch();

        $stmtBook = $db->prepare("
            SELECT id, judul, stok_tersedia
            FROM books
            WHERE id = ? AND status = 'aktif'
            FOR UPDATE
        ");
        $stmtBook->execute([$bookId]);
        $book = $stmtBook->fetch();

        if (!$user || !$book) {
            $db->rollBack();
            setFlash('error', 'Peminjam atau buku tidak ditemukan.');
            header('Location: ' . $redirect);
            exit;
        }

        if ((int) $book['stok_tersedia'] < $jumlah) {
            $db->rollBack();
            setFlash('error', 'Stok buku tidak mencukupi.');
            header('Location: ' . $redirect);
            exit;
        }

        do {
            $kode = 'PJM-' . date('Ymd') . '-' . random_int(1000, 9999);
            $cekKode = $db->prepare('SELECT id FROM loans WHERE kode_peminjaman = ? LIMIT 1');
            $cekKode->execute([$kode]);
        } while ($cekKode->fetchColumn());

        $tanggalPinjam = date('Y-m-d');
        $tanggalJatuhTempo = date('Y-m-d', strtotime('+7 days'));

        $stmtLoan = $db->prepare("
            INSERT INTO loans
            (kode_peminjaman, user_id, tanggal_pengajuan, tanggal_pinjam, tanggal_jatuh_tempo, status, catatan)
            VALUES (?, ?, ?, ?, ?, 'dipinjam', ?)
        ");
        $stmtLoan->execute([
            $kode,
            $userId,
            $tanggalPinjam,
            $tanggalPinjam,
            $tanggalJatuhTempo,
            'Peminjaman manual dicatat oleh ' . (currentUser()['nama'] ?: 'Petugas') . '.'
        ]);

        $loanId = (int) $db->lastInsertId();

        $stmtDetail = $db->prepare('INSERT INTO loan_details (loan_id, book_id, jumlah) VALUES (?, ?, ?)');
        $stmtDetail->execute([$loanId, $bookId, $jumlah]);

        $stmtStok = $db->prepare("
            UPDATE books
            SET stok_tersedia = stok_tersedia - ?
            WHERE id = ? AND stok_tersedia >= ?
        ");
        $stmtStok->execute([$jumlah, $bookId, $jumlah]);

        if ($stmtStok->rowCount() !== 1) {
            $db->rollBack();
            setFlash('error', 'Stok buku berubah. Silakan coba lagi.');
            header('Location: ' . $redirect);
            exit;
        }

        $db->commit();
        setFlash('success', "Peminjaman manual {$kode} berhasil dicatat. Tanggal pengembalian ditetapkan otomatis 7 hari.");
        header('Location: ' . $redirect);
        exit;
    }

    $loanId = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
    if (!$loanId || $loanId <= 0) {
        setFlash('error', 'ID peminjaman tidak valid.');
        header('Location: ' . $redirect);
        exit;
    }

    if (!in_array($action, ['konfirmasi', 'setujui', 'tolak'], true)) {
        setFlash('error', 'Aksi peminjaman tidak valid.');
        header('Location: ' . $redirect);
        exit;
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        SELECT loans.id, loans.status, loans.kode_peminjaman
        FROM loans
        WHERE loans.id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();

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
        $namaPetugas = currentUser()['nama'] ?: (currentUser()['username'] ?? 'Petugas');
        $stmt = $db->prepare("
            UPDATE loans
            SET status = 'ditolak', catatan = ?
            WHERE id = ? AND status = 'menunggu'
        ");
        $stmt->execute(['Peminjaman ditolak oleh ' . $namaPetugas . '.', $loanId]);

        $db->commit();
        setFlash('success', 'Peminjaman berhasil ditolak.');
        header('Location: ' . $redirect);
        exit;
    }

    $stmtDetails = $db->prepare("
        SELECT ld.book_id, ld.jumlah, b.judul, b.stok_tersedia
        FROM loan_details ld
        INNER JOIN books b ON b.id = ld.book_id
        WHERE ld.loan_id = ?
        FOR UPDATE
    ");
    $stmtDetails->execute([$loanId]);
    $details = $stmtDetails->fetchAll();

    if (!$details) {
        $db->rollBack();
        setFlash('error', 'Detail buku pada peminjaman tidak ditemukan.');
        header('Location: ' . $redirect);
        exit;
    }

    foreach ($details as $detail) {
        if ((int) $detail['jumlah'] <= 0 || (int) $detail['stok_tersedia'] < (int) $detail['jumlah']) {
            $db->rollBack();
            setFlash('error', 'Stok salah satu buku tidak mencukupi.');
            header('Location: ' . $redirect);
            exit;
        }
    }

    foreach ($details as $detail) {
        $stmtStok = $db->prepare("
            UPDATE books
            SET stok_tersedia = stok_tersedia - ?
            WHERE id = ? AND stok_tersedia >= ?
        ");
        $stmtStok->execute([
            (int) $detail['jumlah'],
            (int) $detail['book_id'],
            (int) $detail['jumlah']
        ]);
    }

    $tanggalPinjam = date('Y-m-d');
    $tanggalJatuhTempo = date('Y-m-d', strtotime('+7 days'));
    $namaPetugas = currentUser()['nama'] ?: (currentUser()['username'] ?? 'Petugas');

    $stmtApprove = $db->prepare("
        UPDATE loans
        SET status = 'dipinjam',
            tanggal_pinjam = ?,
            tanggal_jatuh_tempo = ?,
            catatan = ?
        WHERE id = ? AND status = 'menunggu'
    ");
    $stmtApprove->execute([
        $tanggalPinjam,
        $tanggalJatuhTempo,
        'Peminjaman disetujui oleh ' . $namaPetugas . '.',
        $loanId
    ]);

    if ($stmtApprove->rowCount() !== 1) {
        $db->rollBack();
        setFlash('error', 'Status peminjaman gagal diperbarui.');
        header('Location: ' . $redirect);
        exit;
    }

    $db->commit();
    setFlash('success', 'Peminjaman berhasil disetujui. Tanggal pinjam dan jatuh tempo dibuat otomatis oleh sistem.');
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Peminjaman proses error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan sistem saat memproses peminjaman.');
}

header('Location: ' . $redirect);
exit;
