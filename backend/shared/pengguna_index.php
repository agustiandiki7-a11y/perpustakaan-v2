<?php
$pageTitle = 'Manajemen Pengguna';
require_once __DIR__ . '/../layouts/header.php';
cekRole(['admin']); // khusus admin

require_once __DIR__ . '/../layouts/sidebar.php';

$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT id, nama, username, email, role FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([(int) $_GET['edit']]);
    $editData = $stmt->fetch() ?: null;
}

// Mengambil daftar pengguna selain admin
$users = $db->query("SELECT id, nama, username, email, role, created_at FROM users WHERE role != 'admin' ORDER BY FIELD(role, 'petugas', 'peminjam'), id DESC")->fetchAll();

$isEditPeminjam = $editData && $editData['role'] === 'peminjam';
$currentUserId = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0);
?>

<div class="panel mb-3">
    <div class="panel-heading">
        <h2><?= $editData ? 'Edit Pengguna' : 'Tambah Pengguna' ?></h2>
    </div>
    <form action="proses.php" method="POST">
        <?= csrfField() ?>
        <?php if ($editData): ?>
            <input type="hidden" name="id" value="<?= (int) $editData['id'] ?>">
            <input type="hidden" name="action" value="update">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>

        <?php if ($isEditPeminjam): ?>
            <div class="alert-flash alert-error" style="margin-bottom:0.75rem;">
                <i class="fas fa-circle-info"></i>
                Data pribadi (nama, username, email, password) akun peminjam dikunci dari halaman admin.
                Data itu milik &amp; diinput sendiri oleh peminjam saat daftar, jadi admin cuma bisa
                mengubah <strong>peran</strong> (mis. mempromosikan jadi petugas) atau menghapus akunnya.
            </div>
        <?php endif; ?>

        <!-- Menggunakan g-2 agar jarak antar kolom lebih rapat -->
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Nama</label>
                <input type="text" name="nama" class="form-control" required <?= $isEditPeminjam ? 'readonly' : '' ?>
                       value="<?= htmlspecialchars($editData['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required <?= $editData ? 'readonly' : '' ?>
                       value="<?= htmlspecialchars($editData['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required <?= $isEditPeminjam ? 'readonly' : '' ?>
                       value="<?= htmlspecialchars($editData['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-control">
                    <?php foreach (['peminjam', 'petugas'] as $r): ?>
                        <option value="<?= $r ?>" <?= ($editData['role'] ?? 'peminjam') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint mb-0">Akun admin gak dikelola dari sini.</p>
            </div>
            <div class="col-md-6 mt-2">
                <label class="form-label">
                    Password <?= $editData ? '(kosongkan kalau gak ganti)' : '' ?>
                    <?= $isEditPeminjam ? '— dikunci untuk akun peminjam' : '' ?>
                </label>
                <input type="password" name="password" class="form-control"
                       <?= $editData ? '' : 'required' ?> <?= $isEditPeminjam ? 'disabled' : '' ?>>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn-brand"><i class="fas fa-check"></i> <?= $editData ? 'Simpan Perubahan' : 'Tambah Pengguna' ?></button>
            <?php if ($editData): ?>
                <a href="index.php" class="btn-outline">Batal</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading"><h2>Daftar Pengguna (<?= count($users) ?>)</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Terdaftar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pengguna.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge-pill badge-aktif"><?= ucfirst($u['role']) ?></span></td>
                            <td><?= htmlspecialchars($u['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <a href="index.php?edit=<?= (int) $u['id'] ?>" class="btn-outline"><i class="fas fa-pen"></i></a>
                                <?php if ((int) $u['id'] !== (int) $currentUserId): ?>
                                    <form action="proses.php" method="POST" style="display:inline" onsubmit="return confirm('Hapus pengguna ini?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="btn-danger-outline"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>