<?php
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../app/helpers/auth.php';
mulaiSession();
applySecurityHeaders();

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../includes/functions.php';

$viewer = currentUser();

$keyword    = trim($_GET['q'] ?? '');
$kategoriId = isset($_GET['kategori']) ? (int) $_GET['kategori'] : 0;

$sql = "SELECT books.*, categories.nama_kategori
        FROM books
        LEFT JOIN categories ON categories.id = books.category_id
        WHERE books.status = 'aktif'";

$types  = '';
$params = [];

if ($keyword !== '') {
    $sql .= " AND (books.judul LIKE ? OR books.penulis LIKE ? OR books.penerbit LIKE ?)";
    $like = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

if ($kategoriId > 0) {
    $sql .= " AND books.category_id = ?";
    $params[] = $kategoriId;
    $types   .= 'i';
}

$sql .= " ORDER BY books.id DESC LIMIT 24";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo render_empty_state('Terjadi kesalahan saat mencari buku.');
    exit;
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$books  = $result->fetch_all(MYSQLI_ASSOC);

if (empty($books)) {
    echo render_empty_state('Buku dengan kata kunci "' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '" tidak ditemukan.');
    exit;
}

foreach ($books as $book) {
    echo render_book_card($book, $viewer);
}
