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

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !verifyCsrf($_POST['csrf_token'] ?? null)
) {
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

try {
    $db = (new Database())->connect();

    $db->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | CEK BUKU
    |--------------------------------------------------------------------------
    */

    $stmtBuku = $db->prepare("
        SELECT id, judul, stok_tersedia
        FROM books
        WHERE id = ?
          AND status = 'aktif'
        FOR UPDATE
    ");

    $stmtBuku->execute([$bookId]);

    $buku = $stmtBuku->fetch(PDO::FETCH_ASSOC);

    if (!$buku) {
        $db->rollBack();

        setFlash('error', 'Buku tidak ditemukan atau sudah tidak aktif.');
        header('Location: ../../index.php');
        exit;
    }

    $stokTersedia = (int) $buku['stok_tersedia'];

    if ($stokTersedia <= 0) {
        $db->rollBack();

        setFlash('error', 'Stok buku sedang habis.');
        header('Location: pinjam.php?id=' . $bookId);
        exit;
    }

    if ($jumlah > $stokTersedia) {
        $db->rollBack();

        setFlash(
            'error',
            'Jumlah buku melebihi stok yang tersedia.'
        );

        header('Location: pinjam.php?id=' . $bookId);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CEK PENGAJUAN YANG MASIH MENUNGGU
    |--------------------------------------------------------------------------
    */

    $stmtCek = $db->prepare("
        SELECT loans.id
        FROM loans
        INNER JOIN loan_details
            ON loan_details.loan_id = loans.id
        WHERE loans.user_id = ?
          AND loan_details.book_id = ?
          AND loans.status = 'menunggu'
        LIMIT 1
    ");

    $stmtCek->execute([
        $me['id'],
        $bookId
    ]);

    if ($stmtCek->fetch()) {
        $db->rollBack();

        setFlash(
            'error',
            'Kamu sudah mengajukan buku ini dan masih menunggu konfirmasi.'
        );

        header('Location: ../../index.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | KODE PEMINJAMAN
    |--------------------------------------------------------------------------
    */

    do {
        $kodePeminjaman =
            'PJM-' .
            date('Ymd') .
            '-' .
            random_int(1000, 9999);

        $stmtKode = $db->prepare("
            SELECT id
            FROM loans
            WHERE kode_peminjaman = ?
            LIMIT 1
        ");

        $stmtKode->execute([$kodePeminjaman]);

    } while ($stmtKode->fetchColumn());

    /*
    |--------------------------------------------------------------------------
    | TANGGAL
    |--------------------------------------------------------------------------
    |
    | Karena database kita mewajibkan tanggal_pinjam dan
    | tanggal_jatuh_tempo, kita isi dari awal.
    |
    | Tetapi status tetap MENUNGGU.
    |
    */

    $tanggalPengajuan = date('Y-m-d');
    $tanggalPinjam = date('Y-m-d');
    $tanggalJatuhTempo = date(
        'Y-m-d',
        strtotime('+7 days')
    );

    /*
    |--------------------------------------------------------------------------
    | INSERT LOANS
    |--------------------------------------------------------------------------
    */

    $stmtLoan = $db->prepare("
        INSERT INTO loans (
            kode_peminjaman,
            user_id,
            tanggal_pengajuan,
            tanggal_pinjam,
            tanggal_jatuh_tempo,
            tanggal_kembali,
            status,
            catatan
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            NULL,
            'menunggu',
            ?
        )
    ");

    $stmtLoan->execute([
        $kodePeminjaman,
        $me['id'],
        $tanggalPengajuan,
        $tanggalPinjam,
        $tanggalJatuhTempo,
        'Pengajuan peminjaman melalui website dan menunggu konfirmasi admin/petugas.'
    ]);

    $loanId = $db->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | INSERT DETAIL
    |--------------------------------------------------------------------------
    */

    $stmtDetail = $db->prepare("
        INSERT INTO loan_details (
            loan_id,
            book_id,
            jumlah
        )
        VALUES (?, ?, ?)
    ");

    $stmtDetail->execute([
        $loanId,
        $bookId,
        $jumlah
    ]);

    /*
    |--------------------------------------------------------------------------
    | STOK TIDAK DIKURANGI
    |--------------------------------------------------------------------------
    |
    | Karena masih MENUNGGU.
    | Stok baru dikurangi saat admin/petugas menyetujui.
    |
    */

    $db->commit();

    /*
    |--------------------------------------------------------------------------
    | BERHASIL
    |--------------------------------------------------------------------------
    */

    header(
        'Location: peminjaman_berhasil.php?kode=' .
        urlencode($kodePeminjaman)
    );

    exit;

} catch (PDOException $e) {

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log(
        'Pengajuan peminjaman error: ' .
        $e->getMessage()
    );

    setFlash(
        'error',
        'Terjadi kesalahan sistem. Pengajuan peminjaman gagal, coba lagi nanti.'
    );

    header(
        'Location: pinjam.php?id=' .
        $bookId
    );

    exit;
}