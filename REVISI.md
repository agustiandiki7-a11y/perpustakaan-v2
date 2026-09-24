# Perpustakaan Digital — Revisi

Perbaikan pada versi ini:
- Hak akses backend mengikuti folder role: `/backend/admin` hanya admin dan `/backend/petugas` hanya petugas.
- Menu dan proses `Pengguna` dipaksa khusus admin.
- Tombol `Pinjam` di katalog tidak ditampilkan sebagai aksi pinjam untuk akun staf.
- Kode peminjaman memakai `random_int()` dan dicek agar tidak bentrok dengan kode yang sudah ada.
- Validasi tanggal peminjaman backend dibuat lebih ketat.
- Semua file PHP sudah dicek dengan `php -l` tanpa error sintaks.

Database:
- Import `database.sql`.
- Konfigurasi koneksi ada di `app/config/Database.php`.
- Default koneksi: MySQL `localhost`, user `root`, password kosong, database `perpustakaan-v2`.
