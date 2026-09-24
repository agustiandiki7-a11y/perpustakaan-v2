<?php
// ==========================================
// KEAMANAN DASAR
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', '0'); // error tetap dicatat di log, gak ditampilin ke pengunjung

require_once __DIR__ . '/app/helpers/auth.php';
mulaiSession();
applySecurityHeaders();
cekAksesPeminjam(); // staf (admin/petugas) diarahkan ke dashboard backend, bukan halaman peminjam
$flash = getFlash();
$me = currentUser();

// ==========================================
// KONEKSI DATABASE & INISIALISASI
// ==========================================
include 'frontend/database/connection.php';
include 'frontend/includes/functions.php';

// --- Kategori aktif, buat filter chip kategori ---
$categories = [];
$resKategori = $conn->query("SELECT id, nama_kategori FROM categories WHERE status = 'aktif' ORDER BY nama_kategori ASC");
if ($resKategori) {
    $categories = $resKategori->fetch_all(MYSQLI_ASSOC);
}

// --- Buku aktif terbaru, buat section "Koleksi Buku Perpustakaan" ---
$books = [];
$resBuku = $conn->query("
    SELECT books.*, categories.nama_kategori
    FROM books
    LEFT JOIN categories ON categories.id = books.category_id
    WHERE books.status = 'aktif'
    ORDER BY books.id DESC
    LIMIT 12
");
if ($resBuku) {
    $books = $resBuku->fetch_all(MYSQLI_ASSOC);
}

// --- Statistik ringkas buat panel di hero ---
$totalBukuAktif = 0;
$resTotalBuku = $conn->query("SELECT COUNT(*) AS total FROM books WHERE status = 'aktif'");
if ($resTotalBuku) {
    $totalBukuAktif = (int) ($resTotalBuku->fetch_assoc()['total'] ?? 0);
}

$totalKategoriAktif = count($categories);

$totalAnggota = 0;
$resTotalAnggota = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'peminjam'");
if ($resTotalAnggota) {
    $totalAnggota = (int) ($resTotalAnggota->fetch_assoc()['total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<?php include 'frontend/partials/head.php' ?>

<body>
    <!-- navbar -->
    <?php include 'frontend/layouts/navbar.php' ?>
    <?php if ($flash): ?>
        <div class="container">
            <div class="flash-banner flash-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    <?php endif; ?>
    <!-- hero -->
    <?php include 'frontend/partials/hero.php' ?>
    <!-- kategori -->
    <?php include 'frontend/partials/kategori.php' ?>
    <!-- katalog -->
    <?php include 'frontend/partials/katalog.php' ?>
    <!-- keunggulan -->
    <?php include 'frontend/partials/keunggulan.php' ?>
    <!-- Footer -->
    <?php include 'frontend/layouts/footer.php' ?>
    <!-- script -->
    <?php include 'frontend/partials/script.php' ?>
</body>
</html>
