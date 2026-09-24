    <!-- Navbar -->
    <nav class="site-nav navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-book-open me-2"></i>Perpustakaan Digital
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Buka menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="#kategori">Kategori</a></li>
                    <li class="nav-item"><a class="nav-link" href="#katalog">Katalog</a></li>
                    <li class="nav-item"><a class="nav-link" href="#keunggulan">Layanan</a></li>
                    <?php if (!empty($me)): ?>
                        <li class="nav-item"><a class="nav-link" href="frontend/page/riwayat.php"><i class="fas fa-clock-rotate-left me-1"></i>Riwayat Saya</a></li>
                        <li class="nav-item nav-user-pill">
                            <i class="fas fa-circle-user me-1"></i><?= htmlspecialchars($me['nama'] ?: $me['username'], ENT_QUOTES, 'UTF-8') ?>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <a href="backend/logout.php" class="btn btn-nav-logout">
                                <i class="fas fa-right-from-bracket me-1"></i> Keluar
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2">
                            <a href="login/pages/login.php" class="btn btn-nav-login">
                                <i class="fas fa-user me-1"></i> Masuk
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
