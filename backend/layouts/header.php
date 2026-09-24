<?php
require_once __DIR__ . '/../../app/config/Database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';

mulaiSession();
applySecurityHeaders();

/*
 * Setiap URL backend hanya boleh dibuka oleh role yang sesuai dengan
 * foldernya (/backend/admin atau /backend/petugas). Ini mencegah user
 * petugas membuka URL admin secara manual.
 */
$routeRole = null;
if (preg_match('#/backend/(admin|petugas)(?:/|$)#i', str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), $match)) {
    $routeRole = strtolower($match[1]);
}

if ($routeRole !== null) {
    cekRole([$routeRole]);
} else {
    cekRole(['admin', 'petugas']);
}

$db = (new Database())->connect();
$me = currentUser();
$pageTitle = $pageTitle ?? 'Dashboard';
$flash = getFlash();
// Admin & petugas sekarang folder terpisah, base path-nya ngikut role yang lagi login
$backendBase = baseUrlPath() . '/backend/' . $me['role'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — Perpustakaan Digital</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= baseUrlPath() ?>/backend/assets/style.css">
</head>
<body>
<div class="admin-shell">
