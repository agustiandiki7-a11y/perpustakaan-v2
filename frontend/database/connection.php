<?php
// mysqli_report dimatiin biar query yang gagal balikin false (bisa dicek manual),
// bukan malah throw exception yang bisa nampilin path/stack trace ke pengunjung.
mysqli_report(MYSQLI_REPORT_OFF);

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "perpustakaan-v2";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    // Detail error cuma dicatat di log server, gak ditampilin ke pengunjung.
    error_log('Koneksi database frontend gagal: ' . $conn->connect_error);
    http_response_code(503);
    die('Maaf, layanan sedang tidak tersedia. Silakan coba beberapa saat lagi.');
}

$conn->set_charset('utf8mb4');
