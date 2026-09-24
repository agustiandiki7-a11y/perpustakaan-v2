    <!-- Katalog Buku dengan AJAX Live Search Container -->
    <section id="katalog" class="section-katalog">
        <div class="container">
            <div class="section-heading section-heading-row">
                <div>
                    <h2>Koleksi buku perpustakaan</h2>
                    <p id="katalogInfo">Menampilkan tambahan terbaru</p>
                </div>
            </div>

            <div class="row g-4" id="katalogGrid">
                <?php if (empty($books)): ?>
                    <?= render_empty_state('Belum ada buku yang tersedia saat ini.') ?>
                <?php else: ?>
                    <?php foreach ($books as $book): ?>
                        <?= render_book_card($book, $me ?? null) ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
