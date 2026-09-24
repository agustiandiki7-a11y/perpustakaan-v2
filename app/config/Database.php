<?php

class Database
{
    private string $host     = 'localhost';
    private string $dbName   = 'perpustakaan-v2';
    private string $username = 'root';
    private string $password = '';

    private ?PDO $connection = null;

    public function connect(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4";

        try {
            $this->connection = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

            return $this->connection;
        } catch (PDOException $e) {
            // Detail error cuma dicatat di log server, gak ditampilin ke pengguna.
            error_log('Koneksi database backend gagal: ' . $e->getMessage());
            http_response_code(503);
            die('Maaf, layanan sedang tidak tersedia. Silakan coba beberapa saat lagi.');
        }
    }

    // Alias, biar kompatibel kalau ada kode lama yang manggil getConnection()
    public function getConnection(): PDO
    {
        return $this->connect();
    }
}
