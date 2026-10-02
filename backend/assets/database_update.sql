-- ============================================================
-- MIGRASI DATABASE: perpustakaan-v2
-- Jalankan SEKALI jika database perpustakaan-v2 yang lama sudah ada.
-- Tambahan: NIK, bukti pengembalian, kondisi buku, dan denda.
-- ============================================================

USE `perpustakaan-v2`;

ALTER TABLE users
    ADD COLUMN nik VARCHAR(16) NULL UNIQUE AFTER email;

ALTER TABLE loans
    MODIFY tanggal_pinjam DATE NULL,
    MODIFY tanggal_jatuh_tempo DATE NULL,
    MODIFY status ENUM('menunggu', 'dipinjam', 'dikembalikan', 'terlambat', 'ditolak')
        NOT NULL DEFAULT 'menunggu',
    ADD COLUMN bukti_pengembalian VARCHAR(255) NULL AFTER catatan,
    ADD COLUMN kondisi_buku ENUM('baik', 'rusak_ringan', 'rusak_berat') NULL AFTER bukti_pengembalian,
    ADD COLUMN denda_keterlambatan DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER kondisi_buku,
    ADD COLUMN denda_kerusakan DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER denda_keterlambatan,
    ADD COLUMN denda_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER denda_kerusakan,
    ADD COLUMN denda_dibayar TINYINT(1) NOT NULL DEFAULT 0 AFTER denda_total;
