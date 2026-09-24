<?php
$pageTitle = 'Kategori';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editData = $stmt->fetch() ?: null;
}

$categories = $db->query('SELECT * FROM categories ORDER BY nama_kategori ASC')->fetchAll();
?>

<div class="panel mb-4">
    <div class="panel-heading">
        <h2><?= $editData ? 'Edit Kategori' : 'Tambah Kategori' ?></h2>
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
            <div class="col-md-5">
                <label class="form-label">Nama Kategori</label>
                <input type="text" name="nama_kategori" class="form-control" required
                       value="<?= htmlspecialchars($editData['nama_kategori'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label">Deskripsi (opsional)</label>
                <input type="text" name="deskripsi" class="form-control"
                       value="<?= htmlspecialchars($editData['deskripsi'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="aktif" <?= ($editData['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= ($editData['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn-brand"><i class="fas fa-check"></i> <?= $editData ? 'Simpan Perubahan' : 'Tambah Kategori' ?></button>
            <?php if ($editData): ?>
                <a href="index.php" class="btn-outline">Batal</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading">
        <h2>Daftar Kategori (<?= count($categories) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Nama</th><th>Deskripsi</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada kategori.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= htmlspecialchars($cat['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($cat['deskripsi'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge-pill badge-<?= $cat['status'] === 'aktif' ? 'aktif' : 'nonaktif' ?>"><?= ucfirst($cat['status']) ?></span></td>
                            <td>
                                <a href="index.php?edit=<?= (int) $cat['id'] ?>" class="btn-outline"><i class="fas fa-pen"></i></a>
                                <form action="proses.php" method="POST" style="display:inline" onsubmit="return confirm('Hapus kategori ini?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                    <button type="submit" class="btn-danger-outline"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
