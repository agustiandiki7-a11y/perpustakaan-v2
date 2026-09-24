-- ============================================================
-- DATABASE: perpustakaan-v2
-- Import file ini di phpMyAdmin / mysql CLI sebelum jalanin project.
-- Nama database pakai backtick (`) karena ada tanda "-", ini WAJIB
-- biar MySQL gak salah baca sebagai pengurangan.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `perpustakaan-v2` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `perpustakaan-v2`;

-- ------------------------------------------------------------
-- Tabel: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'petugas', 'peminjam') NOT NULL DEFAULT 'peminjam',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel: categories
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    deskripsi TEXT NULL,
    status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel: books
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    kode_buku VARCHAR(50) NOT NULL UNIQUE,
    judul VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL,
    penulis VARCHAR(150) NOT NULL,
    penerbit VARCHAR(150) NULL,
    tahun_terbit YEAR NULL,
    jumlah_stok INT NOT NULL DEFAULT 0,
    stok_tersedia INT NOT NULL DEFAULT 0,
    cover VARCHAR(255) NULL,
    status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_books_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel: loans (peminjaman)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_peminjaman VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    tanggal_pengajuan DATE NOT NULL,
    tanggal_pinjam DATE NOT NULL,
    tanggal_jatuh_tempo DATE NOT NULL,
    tanggal_kembali DATE NULL,
    status ENUM('dipinjam', 'dikembalikan', 'terlambat') NOT NULL DEFAULT 'dipinjam',
    catatan VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_loans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabel: loan_details (relasi loans <-> books)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loan_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    book_id INT NOT NULL,
    jumlah INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_loandetails_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    CONSTRAINT fk_loandetails_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- DATA CONTOH
-- ============================================================

-- --- Akun (password login pakai teks di bawah, udah di-hash pakai bcrypt) ---
-- admin    / admin123
-- petugas1 / petugas123
-- budi     / peminjam123
INSERT INTO users (nama, username, email, password, role) VALUES
('Administrator', 'admin', 'admin@perpustakaan.test', '$2y$10$nPSQAd/jI3RqzUMVkTCj0.I4Z9Ea6zYew/ZRwViieKL6/Ys.eHxMK', 'admin'),
('Rina Petugas', 'petugas1', 'petugas1@perpustakaan.test', '$2y$10$j07biTprgP6UNsNy5VbK1uiKcavl9kkHdqE/jBpjICnOX7dphumMa', 'petugas'),
('Budi Santoso', 'budi', 'budi@mail.test', '$2y$10$riHfiOsGit2khFX86lX6cuBOIhZDxN4OwmUBDxazTRmnNTN5G.CRC', 'peminjam');

-- --- Kategori ---
INSERT INTO categories (nama_kategori, slug, deskripsi, status) VALUES
('Fiksi', 'fiksi', 'Novel dan cerita fiksi dari penulis dalam & luar negeri.', 'aktif'),
('Sains', 'sains', 'Buku pengetahuan populer dan sains.', 'aktif'),
('Sejarah', 'sejarah', 'Buku sejarah dan biografi tokoh.', 'aktif'),
('Teknologi', 'teknologi', 'Buku pemrograman dan teknologi.', 'aktif'),
('Anak', 'anak', 'Buku cerita dan edukasi untuk anak-anak.', 'aktif');

-- --- Buku ---
INSERT INTO books (category_id, kode_buku, judul, slug, penulis, penerbit, tahun_terbit, jumlah_stok, stok_tersedia, cover, status) VALUES
(1, 'BK-0001', 'Laut Bercerita', 'laut-bercerita', 'Leila S. Chudori', 'KPG', 2017, 5, 5, NULL, 'aktif'),
(1, 'BK-0002', 'Negeri 5 Menara', 'negeri-5-menara', 'Ahmad Fuadi', 'Gramedia', 2009, 4, 4, NULL, 'aktif'),
(2, 'BK-0003', 'Sapiens', 'sapiens', 'Yuval Noah Harari', 'Alvabet', 2017, 3, 3, NULL, 'aktif'),
(3, 'BK-0004', 'Bumi Manusia', 'bumi-manusia', 'Pramoedya Ananta Toer', 'Lentera Dipantara', 1980, 4, 4, NULL, 'aktif'),
(4, 'BK-0005', 'Clean Code', 'clean-code', 'Robert C. Martin', 'Prentice Hall', 2008, 3, 3, NULL, 'aktif'),
(5, 'BK-0006', 'Cerita Rakyat Nusantara', 'cerita-rakyat-nusantara', 'Tim Penulis', 'Erlangga', 2015, 6, 6, NULL, 'aktif');
