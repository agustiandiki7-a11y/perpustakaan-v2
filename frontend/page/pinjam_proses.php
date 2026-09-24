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

/*
|--------------------------------------------------------------------------
| Validasi Request
|--------------------------------------------------------------------------
*/

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

$db = (new Database())->connect();

try {

    $db->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Cek Buku
    |--------------------------------------------------------------------------
    | Buku dikunci hanya untuk memastikan data buku tidak berubah
    | ketika pengajuan sedang dibuat.
    |
    | Stok TIDAK dikurangi di sini.
    |--------------------------------------------------------------------------
    */

    $stmtBuku = $db->prepare("
        SELECT
            id,
            judul,
            stok_tersedia
        FROM books
        WHERE id = ?
          AND status = 'aktif'
        FOR UPDATE
    ");

    $stmtBuku->execute([$bookId]);

    $buku = $stmtBuku->fetch();

    if (!$buku) {
        $db->rollBack();

        setFlash(
            'error',
            'Buku tidak ditemukan atau sudah tidak aktif.'
        );

        header('Location: ../../index.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Validasi Stok
    |--------------------------------------------------------------------------
    | Kita tetap mengecek stok saat pengajuan dibuat.
    | Tetapi stok belum dikurangi sampai admin/petugas menyetujui.
    |--------------------------------------------------------------------------
    */

    if ($jumlah > (int) $buku['stok_tersedia']) {
        $db->rollBack();

        setFlash(
            'error',
            'Jumlah buku melebihi stok yang tersedia saat ini.'
        );

        header('Location: pinjam.php?id=' . $bookId);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Buat Kode Peminjaman
    |--------------------------------------------------------------------------
    */

    do {

        $kodePeminjaman =
            'PJM-' .
            date('Ymd') .
            '-' .
            random_int(1000, 9999);

        $cekKode = $db->prepare("
            SELECT 1
            FROM loans
            WHERE kode_peminjaman = ?
            LIMIT 1
        ");

        $cekKode->execute([$kodePeminjaman]);

    } while ($cekKode->fetchColumn());

    /*
    |--------------------------------------------------------------------------
    | Tanggal Pengajuan
    |--------------------------------------------------------------------------
    */

    $tanggalPengajuan = date('Y-m-d');

    /*
    |--------------------------------------------------------------------------
    | Buat Data Peminjaman
    |--------------------------------------------------------------------------
    |
    | Status:
    | menunggu
    |
    | Karena belum dikonfirmasi admin/petugas:
    |
    | tanggal_pinjam       = NULL
    | tanggal_jatuh_tempo  = NULL
    |
    |--------------------------------------------------------------------------
    */

    $stmtLoan = $db->prepare("
        INSERT INTO loans (
            kode_peminjaman,
            user_id,
            tanggal_pengajuan,
            tanggal_pinjam,
            tanggal_jatuh_tempo,
            status,
            catatan
        )
        VALUES (
            ?,
            ?,
            ?,
            NULL,
            NULL,
            'menunggu',
            ?
        )
    ");

    $stmtLoan->execute([
        $kodePeminjaman,
        $me['id'],
        $tanggalPengajuan,
        'Pengajuan peminjaman melalui website dan sedang menunggu konfirmasi admin/petugas.'
    ]);

    $loanId = $db->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Simpan Detail Buku
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
    | PENTING
    |--------------------------------------------------------------------------
    |
    | Stok BELUM dikurangi.
    |
    | Stok baru akan dikurangi ketika admin/petugas menekan
    | tombol "Konfirmasi Peminjaman".
    |
    |--------------------------------------------------------------------------
    */

    $db->commit();

    /*
    |--------------------------------------------------------------------------
    | Pengajuan Berhasil
    |--------------------------------------------------------------------------
    |
    | Jangan langsung masuk riwayat.
    | Nanti kita buat halaman peminjaman_berhasil.php
    | yang menampilkan popup/card:
    |
    | "Peminjaman sedang diproses"
    |--------------------------------------------------------------------------
    */

    header(
        'Location: peminjaman_berhasil.php?kode=' .
        urlencode($kodePeminjaman)
    );

    exit;

} catch (PDOException $e) {

    if ($db->inTransaction()) {
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