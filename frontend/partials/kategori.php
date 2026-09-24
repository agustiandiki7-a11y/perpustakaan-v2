    <!-- Kategori Section -->
    <section id="kategori" class="section-kategori">
        <div class="container">
            <div class="section-heading">
                <h2>Jelajah per kategori</h2>
                <p>Pilih satu untuk menyaring katalog di bawah, klik lagi untuk membatalkan.</p>
            </div>

            <?php if (empty($categories)): ?>
                <p class="text-muted">Belum ada kategori tersedia.</p>
            <?php else: ?>
                <div class="kategori-scroll">
                    <?php foreach ($categories as $cat): ?>
                        <button
                            type="button"
                            class="kategori-chip"
                            data-kategori-id="<?= (int) $cat['id'] ?>"
                            onclick="filterKategori(<?= (int) $cat['id'] ?>)"
                        >
                            <?= htmlspecialchars($cat['nama_kategori'], ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
