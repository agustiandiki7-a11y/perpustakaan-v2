    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <p class="hero-eyebrow">Perpustakaan Digital</p>
                    <h1 class="hero-title">Satu rak penuh cerita,<br>selalu terbuka untukmu.</h1>
                    <p class="hero-lead">
                        Telusuri koleksi buku fisik dan ebook, temukan judul yang kamu cari dalam hitungan detik,
                        lalu ajukan peminjaman langsung dari sini.
                    </p>

                    <div class="hero-search">
                        <i class="fas fa-search hero-search-icon"></i>
                        <input
                            type="text"
                            id="liveSearchInput"
                            class="hero-search-input"
                            placeholder="Cari judul, penulis, atau penerbit..."
                            autocomplete="off"
                        >
                        <button id="searchBtn" class="hero-search-btn" type="button">Cari</button>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="hero-card">
                        <span class="hero-card-label">Hari ini di perpustakaan</span>
                        <div class="hero-card-stat">
                            <span class="hero-card-number"><?= number_format($totalBukuAktif ?? 0) ?></span>
                            <span class="hero-card-unit">judul buku aktif</span>
                        </div>
                        <hr class="hero-card-rule">
                        <div class="hero-card-row">
                            <span>Kategori tersedia</span>
                            <strong><?= number_format($totalKategoriAktif ?? 0) ?></strong>
                        </div>
                        <div class="hero-card-row">
                            <span>Anggota terdaftar</span>
                            <strong><?= number_format($totalAnggota ?? 0) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
