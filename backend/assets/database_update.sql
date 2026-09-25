-- ============================================================
-- MIGRASI DATABASE: perpustakaan-v2
-- Jalankan jika database perpustakaan-v2 SUDAH pernah dibuat/import
-- sebelum memakai versi ZIP ini.
-- ============================================================

USE `perpustakaan-v2`;

ALTER TABLE loans
    MODIFY tanggal_pinjam DATE NULL,
    MODIFY tanggal_jatuh_tempo DATE NULL,
    MODIFY status ENUM('menunggu', 'dipinjam', 'dikembalikan', 'terlambat', 'ditolak')
        NOT NULL DEFAULT 'menunggu';
