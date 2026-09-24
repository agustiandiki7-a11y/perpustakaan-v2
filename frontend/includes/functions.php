<?php
/**
 * Render satu kartu buku (dipakai di beranda & hasil pencarian AJAX)
 * biar tampilannya selalu konsisten di dua tempat itu.
 */
function render_book_card(array $book, ?array $viewer = null): string
{
    $judul    = htmlspecialchars($book['judul'] ?? 'Tanpa Judul', ENT_QUOTES, 'UTF-8');
    $penulis  = htmlspecialchars($book['penulis'] ?? '-', ENT_QUOTES, 'UTF-8');
    $kategori = htmlspecialchars($book['nama_kategori'] ?? 'Umum', ENT_QUOTES, 'UTF-8');
    $cover    = trim((string) ($book['cover'] ?? ''));
    $coverSrc = $cover !== '' ? htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') : '';

    // Warna "punggung buku" kecil di sisi kartu, ditentukan dari category_id
    // biar tiap kategori punya identitas warna sendiri tanpa perlu kolom baru di DB.
    $spinePalette = ['#1B4332', '#C9A227', '#7A4419', '#3A5A78', '#6B3F69'];
    $spineIndex   = ((int) ($book['category_id'] ?? 0)) % count($spinePalette);
    $spineColor   = $spinePalette[$spineIndex];

    $stokTersedia = (int) ($book['stok_tersedia'] ?? 0);
    if ($stokTersedia > 0) {
        $stokBadge = '<span class="stok-badge stok-tersedia">Tersedia · ' . $stokTersedia . '</span>';
    } else {
        $stokBadge = '<span class="stok-badge stok-habis">Stok habis</span>';
    }

    // Tombol pinjam disesuaikan sama status login:
    // - belum login  -> diarahkan ke halaman masuk dulu
    // - login (peminjam) & stok ada -> langsung ke form pengajuan pinjam
    // - login (peminjam) & stok habis -> tombol nonaktif
    $bookId = (int) ($book['id'] ?? 0);
    if ($stokTersedia <= 0) {
        $borrowButton = '<span class="book-borrow-link book-borrow-disabled">Stok Habis</span>';
    } elseif ($viewer === null) {
        $borrowButton = '<a href="login/pages/login.php" class="book-borrow-link">Pinjam</a>';
    } elseif (($viewer['role'] ?? '') === 'peminjam') {
        $borrowButton = '<a href="frontend/page/pinjam.php?id=' . $bookId . '" class="book-borrow-link">Pinjam</a>';
    } else {
        $borrowButton = '<span class="book-borrow-link book-borrow-disabled" title="Akun staf menggunakan dashboard backend">Staf</span>';
    }

    ob_start();
    ?>
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card-book" style="--spine-color: <?= $spineColor ?>;">
            <div class="book-cover-wrap">
                <?php if ($coverSrc !== ''): ?>
                    <img
                        src="<?= $coverSrc ?>"
                        class="book-cover"
                        alt="Sampul <?= $judul ?>"
                        loading="lazy"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                    >
                    <div class="book-cover-fallback" style="display:none;">
                        <i class="fas fa-book"></i>
                    </div>
                <?php else: ?>
                    <div class="book-cover-fallback">
                        <i class="fas fa-book"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-book-body">
                <span class="kategori-tag"><?= $kategori ?></span>
                <h3 class="book-title" title="<?= $judul ?>"><?= $judul ?></h3>
                <p class="book-author">oleh <?= $penulis ?></p>
                <div class="card-book-footer">
                    <?= $stokBadge ?>
                    <?= $borrowButton ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Blok "tidak ada hasil", dipakai di beranda & hasil pencarian AJAX.
 */
function render_empty_state(string $message): string
{
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    return '<div class="col-12 katalog-empty">
                <i class="fas fa-book-open"></i>
                <p>' . $message . '</p>
            </div>';
}
