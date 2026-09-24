    <footer class="site-footer">
        <div class="container">
            <div class="row gy-4">
                <div class="col-md-5">
                    <p class="footer-brand"><i class="fas fa-book-open me-2"></i>Perpustakaan Digital</p>
                    <p class="footer-text">Membuka akses baca untuk semua orang, kapan pun butuh.</p>
                </div>
                <div class="col-md-3">
                    <p class="footer-heading">Jelajah</p>
                    <ul class="footer-links">
                        <li><a href="#kategori">Kategori</a></li>
                        <li><a href="#katalog">Katalog</a></li>
                        <li><a href="#keunggulan">Layanan</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <p class="footer-heading">Akses</p>
                    <ul class="footer-links">
                        <li><a href="login/pages/login.php">Masuk anggota</a></li>
                    </ul>
                </div>
            </div>
            <hr class="footer-rule">
            <p class="footer-copy">&copy; <?= date('Y') ?> Sistem Informasi Perpustakaan Digital.</p>
        </div>
    </footer>

<div class="site-logout-modal" id="siteLogoutModal" aria-hidden="true">
    <div class="site-logout-dialog" role="dialog" aria-modal="true" aria-labelledby="siteLogoutTitle">
        <div class="site-logout-icon"><i class="fas fa-right-from-bracket"></i></div>
        <h2 id="siteLogoutTitle">Keluar dari akun?</h2>
        <p>Kamu akan kembali ke halaman login.</p>
        <div class="site-logout-actions">
            <button type="button" class="btn-site-cancel" data-site-logout-cancel>Batal</button>
            <a href="backend/logout.php" class="btn-site-logout">Ya, Keluar</a>
        </div>
    </div>
</div>
<script>
(function(){
 const modal=document.getElementById('siteLogoutModal'); if(!modal)return;
 const links=document.querySelectorAll('.btn-nav-logout'); const cancel=modal.querySelector('[data-site-logout-cancel]');
 links.forEach(link=>link.addEventListener('click',function(e){e.preventDefault();modal.classList.add('show');modal.setAttribute('aria-hidden','false');cancel.focus();}));
 cancel.addEventListener('click',()=>{modal.classList.remove('show');modal.setAttribute('aria-hidden','true');});
 modal.addEventListener('click',e=>{if(e.target===modal)cancel.click();});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')cancel.click();});
})();
</script>
