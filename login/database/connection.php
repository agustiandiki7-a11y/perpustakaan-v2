<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "perpustakaan-v2";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log('Koneksi database login gagal: ' . $conn->connect_error);
    http_response_code(503);
    die('Maaf, layanan sedang tidak tersedia. Silakan coba beberapa saat lagi.');
}
$conn->set_charset('utf8mb4');
// echo "Connected successfully";