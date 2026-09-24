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
$me = currentUser();
$action = trim($_POST['action'] ?? '');

function redirectPeminjaman(): never
{
    header('Location: index.php');
    exit;
}

function generateKodePeminjaman(PDO $db): string
{
    do {
        $kode = 'PJM-' . date('Ymd') . '-' . random_int(1000, 9999);
        $stmt = $db->prepare('SELECT 1 FROM loans WHERE kode_peminjaman = ? LIMIT 1');
        $stmt->execute([$kode]);
    } while ($stmt->fetchColumn());

    return $kode;
}

/*
|--------------------------------------------------------------------------
| KONFIRMASI PENGAJUAN ONLINE
|--------------------------------------------------------------------------
*/
if ($action === 'konfirmasi') {
    $loanId = (int) ($_POST['loan_id'] ?? 0);

    if ($loanId <= 0) {
        setFlash('error', 'Data pengajuan tidak valid.');
        redirectPeminjaman();
    }

    try {
        $db->beginTransaction();

        $stmtLoan = $db->prepare("
            SELECT id, kode_peminjaman, status
            FROM loans
            WHERE id = ?
            FOR UPDATE
        ");
        $stmtLoan->execute([$loanId]);
        $loan = $stmtLoan->fetch();

        if (!$loan) {
            throw new RuntimeException('Data pengajuan tidak ditemukan.');
        }

        if ($loan['status'] !== 'menunggu') {
            throw new RuntimeException('Pengajuan ini sudah diproses sebelumnya.');
        }

        $stmtDetails = $db->prepare("
            SELECT
                loan_details.book_id,
                loan_details.jumlah,
                books.judul,
                books.status,
                books.stok_tersedia
            FROM loan_details
            INNER JOIN books ON books.id = loan_details.book_id
            WHERE loan_details.loan_id = ?
            FOR UPDATE
        ");
        $stmtDetails->execute([$loanId]);
        $details = $stmtDetails->fetchAll();

        if (!$details) {
            throw new RuntimeException('Detail buku pada pengajuan tidak ditemukan.');
        }

        foreach ($details as $detail) {
            $jumlah = (int) $detail['jumlah'];
            if ($jumlah < 1) {
                throw new RuntimeException('Jumlah buku pada pengajuan tidak valid.');
            }
            if ($detail['status'] !== 'aktif') {
                throw new RuntimeException('Buku "' . $detail['judul'] . '" sudah tidak aktif.');
            }
            if ((int) $detail['stok_tersedia'] < $jumlah) {
                throw new RuntimeException(
                    'Stok buku "' . $detail['judul'] . '" tidak mencukupi. ' .
                    'Tersedia ' . (int) $detail['stok_tersedia'] . ', diminta ' . $jumlah . '.'
                );
            }
        }

        $tanggalPinjam = date('Y-m-d');
        $tanggalJatuhTempo = date('Y-m-d', strtotime('+7 days'));
        $namaPetugas = $me['nama'] ?: $me['username'];

        $stmtStok = $db->prepare("
            UPDATE books
            SET stok_tersedia = stok_tersedia - ?
            WHERE id = ? AND stok_tersedia >= ?
        ");

        foreach ($details as $detail) {
            $jumlah = (int) $detail['jumlah'];
            $bookId = (int) $detail['book_id'];

            $stmtStok->execute([$jumlah, $bookId, $jumlah]);

            if ($stmtStok->rowCount() !== 1) {
                throw new RuntimeException('Gagal memperbarui stok buku.');
            }
        }

        $stmtUpdate = $db->prepare("
            UPDATE loans
            SET
                tanggal_pinjam = ?,
                tanggal_jatuh_tempo = ?,
                status = 'dipinjam',
                catatan = CONCAT(
                    COALESCE(NULLIF(catatan, ''), ''),
                    CASE WHEN COALESCE(NULLIF(catatan, ''), '') = '' THEN '' ELSE ' | ' END,
                    'Dikonfirmasi oleh ',
                    ?
                ),
                updated_at = NOW()
            WHERE id = ? AND status = 'menunggu'
        ");
        $stmtUpdate->execute([
            $tanggalPinjam,
            $tanggalJatuhTempo,
            $namaPetugas,
            $loanId
        ]);

        if ($stmtUpdate->rowCount() !== 1) {
            throw new RuntimeException('Status pengajuan gagal diperbarui.');
        }

        $db->commit();

        setFlash(
            'success',
            'Peminjaman ' . $loan['kode_peminjaman'] .
            ' berhasil dikonfirmasi. Batas pengembalian ' .
            $tanggalJatuhTempo . '.'
        );
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Konfirmasi peminjaman error: ' . $e->getMessage());
        setFlash('error', $e->getMessage());
    }

    redirectPeminjaman();
}

/*
|--------------------------------------------------------------------------
| TOLAK PENGAJUAN ONLINE
|--------------------------------------------------------------------------
*/
if ($action === 'tolak') {
    $loanId = (int) ($_POST['loan_id'] ?? 0);

    if ($loanId <= 0) {
        setFlash('error', 'Data pengajuan tidak valid.');
        redirectPeminjaman();
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT id, kode_peminjaman, status
            FROM loans
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt->execute([$loanId]);
        $loan = $stmt->fetch();

        if (!$loan) {
            throw new RuntimeException('Data pengajuan tidak ditemukan.');
        }

        if ($loan['status'] !== 'menunggu') {
            throw new RuntimeException('Pengajuan ini sudah diproses sebelumnya.');
        }

        $namaPetugas = $me['nama'] ?: $me['username'];

        $stmtUpdate = $db->prepare("
            UPDATE loans
            SET
                status = 'ditolak',
                catatan = CONCAT(
                    COALESCE(NULLIF(catatan, ''), ''),
                    CASE WHEN COALESCE(NULLIF(catatan, ''), '') = '' THEN '' ELSE ' | ' END,
                    'Ditolak oleh ',
                    ?
                ),
                updated_at = NOW()
            WHERE id = ? AND status = 'menunggu'
        ");
        $stmtUpdate->execute([$namaPetugas, $loanId]);

        if ($stmtUpdate->rowCount() !== 1) {
            throw new RuntimeException('Pengajuan gagal ditolak.');
        }

        $db->commit();
        setFlash('success', 'Pengajuan ' . $loan['kode_peminjaman'] . ' berhasil ditolak.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Penolakan peminjaman error: ' . $e->getMessage());
        setFlash('error', $e->getMessage());
    }

    redirectPeminjaman();
}

/*
|--------------------------------------------------------------------------
| CATAT PEMINJAMAN MANUAL
|--------------------------------------------------------------------------
*/
if ($action === 'create') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $bookId = (int) ($_POST['book_id'] ?? 0);
    $tanggalPinjam = trim($_POST['tanggal_pinjam'] ?? '');
    $tanggalJatuhTempo = trim($_POST['tanggal_jatuh_tempo'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    if ($userId <= 0 || $bookId <= 0 || $tanggalPinjam === '' || $tanggalJatuhTempo === '') {
        setFlash('error', 'Semua kolom wajib diisi.');
        redirectPeminjaman();
    }

    $pinjamDate = DateTime::createFromFormat('!Y-m-d', $tanggalPinjam);
    $kembaliDate = DateTime::createFromFormat('!Y-m-d', $tanggalJatuhTempo);
    $today = new DateTime('today');

    if (
        !$pinjamDate ||
        !$kembaliDate ||
        $pinjamDate->format('Y-m-d') !== $tanggalPinjam ||
        $kembaliDate->format('Y-m-d') !== $tanggalJatuhTempo
    ) {
        setFlash('error', 'Format tanggal tidak valid.');
        redirectPeminjaman();
    }

    if ($pinjamDate < $today) {
        setFlash('error', 'Tanggal pinjam tidak boleh sebelum hari ini.');
        redirectPeminjaman();
    }

    $maxKembali = (clone $pinjamDate)->modify('+7 days');
    if ($kembaliDate < $pinjamDate || $kembaliDate > $maxKembali) {
        setFlash('error', 'Tanggal jatuh tempo harus berada dalam rentang 0–7 hari dari tanggal pinjam.');
        redirectPeminjaman();
    }

    try {
        $db->beginTransaction();

        $stmtUser = $db->prepare("
            SELECT id
            FROM users
            WHERE id = ? AND role = 'peminjam'
            LIMIT 1
        ");
        $stmtUser->execute([$userId]);

        if (!$stmtUser->fetch()) {
            throw new RuntimeException('Anggota peminjam tidak ditemukan.');
        }

        $stmtBook = $db->prepare("
            SELECT id, judul, stok_tersedia, status
            FROM books
            WHERE id = ?
            FOR UPDATE
        ");
        $stmtBook->execute([$bookId]);
        $book = $stmtBook->fetch();

        if (!$book) {
            throw new RuntimeException('Buku tidak ditemukan.');
        }
        if ($book['status'] !== 'aktif') {
            throw new RuntimeException('Buku sedang tidak aktif.');
        }
        if ((int) $book['stok_tersedia'] < 1) {
            throw new RuntimeException('Stok buku sedang habis.');
        }

        $kode = generateKodePeminjaman($db);

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
            VALUES (?, ?, ?, ?, ?, 'dipinjam', ?)
        ");
        $stmtLoan->execute([
            $kode,
            $userId,
            date('Y-m-d'),
            $tanggalPinjam,
            $tanggalJatuhTempo,
            $catatan !== '' ? $catatan : 'Dicatat oleh petugas/admin.'
        ]);

        $loanId = (int) $db->lastInsertId();

        $stmtDetail = $db->prepare("
            INSERT INTO loan_details (loan_id, book_id, jumlah)
            VALUES (?, ?, 1)
        ");
        $stmtDetail->execute([$loanId, $bookId]);

        $stmtStok = $db->prepare("
            UPDATE books
            SET stok_tersedia = stok_tersedia - 1
            WHERE id = ? AND stok_tersedia > 0
        ");
        $stmtStok->execute([$bookId]);

        if ($stmtStok->rowCount() !== 1) {
            throw new RuntimeException('Stok buku gagal diperbarui.');
        }

        $db->commit();
        setFlash('success', 'Peminjaman ' . $kode . ' berhasil dicatat.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Create peminjaman error: ' . $e->getMessage());
        setFlash('error', $e->getMessage());
    }

    redirectPeminjaman();
}

setFlash('error', 'Aksi peminjaman tidak dikenali.');
redirectPeminjaman();
