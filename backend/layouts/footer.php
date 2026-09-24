    </main>
</div>
</div>

<div class="logout-modal" id="logoutModal" aria-hidden="true">
    <div class="logout-dialog" role="dialog" aria-modal="true" aria-labelledby="logoutTitle">
        <div class="logout-icon"><i class="fas fa-right-from-bracket"></i></div>
        <h2 id="logoutTitle">Keluar dari akun?</h2>
        <p>Kamu akan keluar dari dashboard dan kembali ke halaman login.</p>
        <div class="logout-actions">
            <button type="button" class="btn-outline" data-logout-cancel>Batal</button>
            <a href="<?= baseUrlPath() ?>/backend/logout.php" class="btn-danger-solid">Ya, Keluar</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const modal = document.getElementById('logoutModal');
    if (!modal) return;
    const openButtons = document.querySelectorAll('.logout-confirm');
    const cancel = modal.querySelector('[data-logout-cancel]');

    function openModal(event) {
        event.preventDefault();
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        cancel.focus();
    }
    function closeModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    openButtons.forEach(btn => btn.addEventListener('click', openModal));
    cancel.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
})();
</script>
</body>
</html>
