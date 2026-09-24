document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('liveSearchInput');
    var searchBtn = document.getElementById('searchBtn');
    var katalogGrid = document.getElementById('katalogGrid');
    var katalogInfo = document.getElementById('katalogInfo');

    if (!katalogGrid) return;

    var debounceTimer = null;
    var activeKategoriId = 0;

    function showLoading() {
        katalogGrid.innerHTML =
            '<div class="col-12 katalog-loading">' +
            '<div class="spinner-border" role="status"></div>' +
            '<p>Mencari buku...</p>' +
            '</div>';
    }

    function cariBuku(keyword, kategoriId) {
        showLoading();

        var params = new URLSearchParams();
        if (keyword) params.set('q', keyword);
        if (kategoriId) params.set('kategori', kategoriId);

        fetch('frontend/ajax/cari_buku.php?' + params.toString())
            .then(function (res) { return res.text(); })
            .then(function (html) {
                katalogGrid.innerHTML = html;
                if (katalogInfo) {
                    katalogInfo.textContent = (keyword || kategoriId)
                        ? 'Menampilkan hasil pencarian'
                        : 'Menampilkan tambahan terbaru';
                }
            })
            .catch(function () {
                katalogGrid.innerHTML =
                    '<div class="col-12 katalog-loading">Gagal memuat data, coba lagi.</div>';
            });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            var keyword = this.value.trim();
            debounceTimer = setTimeout(function () {
                cariBuku(keyword, activeKategoriId);
            }, 400);
        });
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', function () {
            clearTimeout(debounceTimer);
            cariBuku(searchInput ? searchInput.value.trim() : '', activeKategoriId);
        });
    }

    // Dipanggil dari onclick di kategori.php
    window.filterKategori = function (id) {
        activeKategoriId = (activeKategoriId === id) ? 0 : id; // klik lagi = reset filter
        if (searchInput) searchInput.value = '';

        document.querySelectorAll('.kategori-chip').forEach(function (el) {
            el.classList.remove('active');
        });

        if (activeKategoriId) {
            var activeChip = document.querySelector('[data-kategori-id="' + id + '"]');
            if (activeChip) activeChip.classList.add('active');
        }

        cariBuku('', activeKategoriId);

        var katalogSection = document.getElementById('katalog');
        if (katalogSection) katalogSection.scrollIntoView({ behavior: 'smooth' });
    };
});
