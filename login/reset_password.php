<?php
/**
 * SCRIPT SEKALI PAKAI — HAPUS FILE INI SETELAH DIPAKAI!
 * Reset password akun admin/petugas/budi pakai password_hash() dari PHP
 * kamu sendiri, biar dijamin cocok (gak ada resiko salah karakter dari copy-paste).
 */

require_once __DIR__ . '/../app/config/Database.php';

$akun = [
    'admin'    => 'admin123',
    'petugas1' => 'petugas123',
    'budi'     => 'peminjam123',
];

try {
    $db = (new Database())->connect();

    echo "<h3>Reset Password</h3><ul>";
    foreach ($akun as $username => $passwordBaru) {
        $hash = password_hash($passwordBaru, PASSWORD_DEFAULT);

        $stmt = $db->prepare('UPDATE users SET password = :password WHERE username = :username');
        $stmt->execute([':password' => $hash, ':username' => $username]);

        $affected = $stmt->rowCount();
        echo "<li>{$username}: " . ($affected > 0 ? "berhasil di-reset ✅" : "username gak ketemu di tabel users ❌") . "</li>";
    }
    echo "</ul><p><strong>PENTING: hapus file reset_password.php ini sekarang juga!</strong></p>";

} catch (PDOException $e) {
    echo 'Gagal konek database: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
