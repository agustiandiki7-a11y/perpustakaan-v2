<?php
$pageTitle = 'Buku';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

$categories = $db->query("SELECT id, nama_kategori FROM categories WHERE status = 'aktif' ORDER BY nama_kategori ASC")->fetchAll();

$editData = null;
if (isset($_GET['edit'])) {
    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($editId) {
        $stmt = $db->prepare('SELECT * FROM books WHERE id = ?');
        $stmt->execute([$editId]);
        $editData = $stmt->fetch() ?: null;
    }
}

$keyword = trim($_GET['q'] ?? '');
$sql = "SELECT books.*, categories.nama_kategori
        FROM books
        LEFT JOIN categories ON categories.id = books.category_id";
$params = [];

if ($keyword !== '') {
    $sql .= " WHERE books.judul LIKE ? OR books.kode_buku LIKE ? OR books.penulis LIKE ?";
    $like = '%' . $keyword . '%';
    $params = [$like, $like, $like];
}

$sql .= " ORDER BY books.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();
?>

<div class="panel mb-4">
    <div class="panel-heading">
        <div>
            <h2><?= $editData ? 'Edit Buku' : 'Tambah Buku' ?></h2>
            <p class="form-hint">Kode buku dibuat otomatis oleh sistem. Jika judul yang sama ditambahkan lagi, stok akan digabung ke kode yang sudah ada.</p>
        </div>
    </div>

    <form action="proses.php" method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <?php if ($editData): ?>
            <input type="hidden" name="id" value="<?= (int) $editData['id'] ?>">
            <input type="hidden" name="action" value="update">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>

        <div class="row g-3">
            <?php if ($editData): ?>
                <div class="col-md-3">
                    <label class="form-label">Kode Buku</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($editData['kode_buku'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                </div>
            <?php endif; ?>

            <div class="<?= $editData ? 'col-md-4' : 'col-md-5' ?>">
                <label class="form-label">Judul</label>
                <input type="text" name="judul" class="form-control" required maxlength="255"
                       value="<?= htmlspecialchars($editData['judul'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="<?= $editData ? 'col-md-5' : 'col-md-7' ?>">
                <label class="form-label">Kategori</label>
                <select name="category_id" class="form-control" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= (int) ($editData['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nama_kategori'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Penulis</label>
                <input type="text" name="penulis" class="form-control" required maxlength="150"
                       value="<?= htmlspecialchars($editData['penulis'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Penerbit</label>
                <input type="text" name="penerbit" class="form-control" maxlength="150"
                       value="<?= htmlspecialchars($editData['penerbit'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Tahun Terbit</label>
                <input type="number" name="tahun_terbit" class="form-control" min="1900" max="<?= (int) date('Y') + 1 ?>"
                       value="<?= htmlspecialchars($editData['tahun_terbit'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Jumlah Stok</label>
                <input type="number" name="jumlah_stok" class="form-control" min="0" required
                       value="<?= htmlspecialchars($editData['jumlah_stok'] ?? '0', ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($editData): ?>
                    <p class="form-hint">Stok tersedia: <?= (int) $editData['stok_tersedia'] ?> dari <?= (int) $editData['jumlah_stok'] ?>.</p>
                <?php endif; ?>
            </div>

            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="aktif" <?= ($editData['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= ($editData['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Cover (JPG/PNG, maks 10MB)</label>
                <input type="file" name="cover" class="form-control" accept=".jpg,.jpeg,.png">
                <?php if (!empty($editData['cover'])): ?>
                    <p class="form-hint">File saat ini: <?= htmlspecialchars(basename($editData['cover']), ENT_QUOTES, 'UTF-8') ?>.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn-brand"><i class="fas fa-check"></i> <?= $editData ? 'Simpan Perubahan' : 'Tambah Buku' ?></button>
            <?php if ($editData): ?>
                <a href="index.php" class="btn-outline">Batal</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-heading">
        <h2>Daftar Buku (<?= count($books) ?>)</h2>
        <form action="index.php" method="GET">
            <input type="text" name="q" class="form-control" placeholder="Cari judul/penulis/kode..." value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" style="width:240px;">
        </form>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th><th>Kode</th><th>Judul</th><th>Kategori</th><th>Penulis</th><th>Stok</th><th>Status</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($books)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada buku.</td></tr>
                <?php else: ?>
                    <?php foreach ($books as $index => $book): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($book['kode_buku'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($book['judul'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($book['nama_kategori'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($book['penulis'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) $book['stok_tersedia'] ?> / <?= (int) $book['jumlah_stok'] ?></td>
                            <td><span class="badge-pill badge-<?= $book['status'] === 'aktif' ? 'aktif' : 'nonaktif' ?>"><?= ucfirst($book['status']) ?></span></td>
                            <td>
                                <a href="index.php?edit=<?= (int) $book['id'] ?>" class="btn-outline"><i class="fas fa-pen"></i></a>
                                <form action="proses.php" method="POST" style="display:inline" onsubmit="return confirm('Hapus buku ini?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $book['id'] ?>">
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
