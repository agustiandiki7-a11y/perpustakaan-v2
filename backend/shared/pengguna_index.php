<?php
$pageTitle = 'Manajemen Pengguna';
require_once __DIR__ . '/../layouts/header.php';
cekRole(['admin']);
require_once __DIR__ . '/../layouts/sidebar.php';

$editData = null;
if (isset($_GET['edit'])) {
    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($editId) {
        $stmt = $db->prepare("SELECT id, nama, nik, username, email, role FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$editId]);
        $editData = $stmt->fetch() ?: null;
    }
}

$users = $db->query("
    SELECT id, nama, nik, username, email, role, created_at
    FROM users
    WHERE role != 'admin'
    ORDER BY FIELD(role, 'petugas', 'peminjam'), id DESC
")->fetchAll();

$isEditPeminjam = $editData && $editData['role'] === 'peminjam';
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
?>

<div class="panel mb-3">
    <div class="panel-heading">
        <div>
            <h2><?= $editData ? 'Edit Pengguna' : 'Tambah Pengguna' ?></h2>
            <p class="form-hint">Admin dapat membantu membuat akun peminjam yang kesulitan mendaftar sendiri. NIK menjadi identitas unik peminjam.</p>
        </div>
    </div>

    <form action="proses.php" method="POST">
        <?= csrfField() ?>
        <?php if ($editData): ?>
            <input type="hidden" name="id" value="<?= (int) $editData['id'] ?>">
            <input type="hidden" name="action" value="update">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" class="form-control" maxlength="150" required
                       value="<?= htmlspecialchars($editData['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">NIK</label>
                <input type="text" name="nik" class="form-control" maxlength="16" minlength="16" inputmode="numeric" pattern="\d{16}"
                       <?= $isEditPeminjam ? 'readonly' : '' ?>
                       value="<?= htmlspecialchars($editData['nik'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <p class="form-hint">Wajib 16 digit untuk akun peminjam.</p>
            </div>
            <div class="col-md-4">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" maxlength="50" required <?= $editData ? 'readonly' : '' ?>
                       value="<?= htmlspecialchars($editData['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" maxlength="150" required
                       value="<?= htmlspecialchars($editData['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Role</label>
                <select name="role" class="form-control">
                    <option value="peminjam" <?= ($editData['role'] ?? 'peminjam') === 'peminjam' ? 'selected' : '' ?>>Peminjam</option>
                    <option value="petugas" <?= ($editData['role'] ?? '') === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Password <?= $editData ? '(kosongkan jika tidak diganti)' : '' ?></label>
                <input type="password" name="password" class="form-control" <?= $editData ? '' : 'required' ?> minlength="8">
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn-brand"><i class="fas fa-check"></i> <?= $editData ? 'Simpan Perubahan' : 'Tambah Pengguna' ?></button>
            <?php if ($editData): ?><a href="index.php" class="btn-outline">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading"><h2>Daftar Pengguna (<?= count($users) ?>)</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>No</th><th>Nama</th><th>NIK</th><th>Username</th><th>Email</th><th>Role</th><th>Terdaftar</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada pengguna.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($u['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $u['nik'] ? htmlspecialchars($u['nik'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Belum diisi</span>' ?></td>
                        <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge-pill badge-aktif"><?= ucfirst($u['role']) ?></span></td>
                        <td><?= htmlspecialchars($u['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="index.php?edit=<?= (int) $u['id'] ?>" class="btn-outline"><i class="fas fa-pen"></i></a>
                            <?php if ((int) $u['id'] !== $currentUserId): ?>
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
