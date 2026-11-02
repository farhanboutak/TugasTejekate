# Konteks Proyek untuk AI Agent

## Ringkasan

SIMBAR adalah aplikasi web sederhana untuk mengelola inventaris barang dan transaksi peminjaman. Proyek ini dibuat sebagai tugas web dan dijalankan secara lokal menggunakan PHP, MySQL/MariaDB, dan XAMPP.

## Teknologi dan Cara Menjalankan

- Backend: PHP native, tanpa framework.
- Database: MySQL/MariaDB, database bernama `db_simbar`.
- Koneksi database menggunakan PDO dan session PHP.
- Impor skema serta data awal dari `database/db_simbar.sql` melalui phpMyAdmin. Untuk database lama, jalankan satu kali `database/migrasi_foto_barang.sql` agar kolom `barang.foto` tersedia.
- Jalankan proyek lewat Apache XAMPP dan buka `index.php` di browser.
- Konfigurasi koneksi lokal berada di `config/koneksi.php`. Jangan menambahkan kredensial database ke file lain atau dokumentasi.
- Belum ada konfigurasi dependency manager, framework, maupun test suite otomatis. Untuk validasi file PHP, gunakan `php -l` jika PHP CLI tersedia.

## Struktur dan Alur Aplikasi

- `index.php`: landing page publik; tombol masuk mengarah ke `login.php`.
- `login.php`: formulir login dan mode registrasi melalui `?mode=register`; pesan hasil ditampilkan dari parameter `pesan`.
- `login_proses.php`: mencari user berdasarkan username, memverifikasi password dengan `password_verify()`, menyimpan identitas dan role dalam session, lalu mengarahkan user berdasarkan role (`admin` ke `admin/index.php`, `petugas` ke `petugas/index.php`, `peminjam` ke `peminjaman/index.php`).
- `register_proses.php`: validasi dan pembuatan akun baru dengan role tetap `peminjam`, password hash `password_hash()`, dan perlindungan CSRF.
- `peminjaman/index.php`: halaman khusus role `peminjam` untuk mengirim pengajuan peminjaman berstatus `Pending` dan melihat riwayat akun. Pengajuan memeriksa stok yang tersedia setelah mengurangi jumlah transaksi berstatus `Pending` atau `Disetujui`.
- `peminjaman/index.php` juga menampilkan pembaruan status terkini dari transaksi peminjam serta menyediakan tautan profil akun.
- `petugas/index.php`: dashboard role `petugas` untuk mencari/filter semua transaksi serta menyetujui, menolak, atau menandai transaksi disetujui sebagai dikembalikan.
- `admin/barang.php` dan `admin/barang_aksi.php`: inventaris CRUD untuk admin/petugas; stok tidak boleh dikurangi di bawah jumlah transaksi Pending/Disetujui, barang yang sudah memiliki riwayat transaksi tidak dapat dihapus, dan foto barang dapat diunggah atau diganti.
- Foto inventaris disimpan di `uploads/barang/` dengan nama acak; hanya JPG, PNG, dan WebP hingga 5 MB yang diterima. Form admin menampilkan pratinjau dan pengguna melihat foto pada katalog di `peminjaman/index.php`.
- `admin/ekspor_peminjaman.php`: ekspor CSV transaksi dengan filter status, rentang tanggal pinjam, dan pencarian; nilai teks CSV dinetralkan agar tidak dieksekusi sebagai formula spreadsheet.
- `admin/aksi_status.php`: endpoint POST perubahan status yang digunakan admin maupun petugas; validasi transisi status dan CSRF, lalu kembali ke dashboard sesuai role.
- `admin/hapus.php`: endpoint hapus transaksi admin-only dengan metode POST dan CSRF.
- `admin/pengguna.php`: halaman admin untuk membuat, melihat, mengubah nama/username/role, mereset password, dan menghapus akun yang tidak memiliki transaksi.
- `admin/pengguna_aksi.php`: endpoint POST pengelolaan akun; akses admin-only, CSRF, validasi input, hash password, dan perlindungan akun admin terakhir.
- `akun.php`: profil bersama semua role untuk memperbarui nama/username dan opsional password; perubahan memerlukan password saat ini.
- `config/koneksi.php`: membuat koneksi PDO ke database dan memulai session.
- `config/auth.php`: fungsi `cek_akses($allowed_roles)` untuk memeriksa session login dan role.
- `admin/index.php`: mengambil daftar peminjaman dengan join ke tabel `users` dan `barang`.
- `logout.php`: menghapus session dan mengarahkan user kembali ke halaman login.
- `update_password.php`: skrip utilitas yang langsung mengubah password admin; jangan menjalankannya tanpa persetujuan eksplisit dan jangan mengeksposnya sebagai endpoint publik.
- `database/db_simbar.sql`: definisi dan data awal untuk tabel `users`, `barang`, dan `peminjaman`.

Skema utama:

- `users`: nama, username, hash password, dan role (`admin`, `petugas`, `peminjam`).
- `barang`: nama barang, kategori, foto opsional, stok, dan kondisi.
- `peminjaman`: peminjam, barang, tanggal pinjam/kembali, jumlah, dan status (`Pending`, `Disetujui`, `Ditolak`, `Dikembalikan`).

## Status Implementasi yang Perlu Diingat

- `config/auth.php` menyediakan pemeriksaan role, tetapi halaman yang mengelola data tetap perlu memanggilnya secara eksplisit; jangan mengasumsikan halaman otomatis terlindungi.
- Ringkasan angka dan contoh transaksi pada landing page adalah konten tampilan, bukan data yang diambil dari database.
- Dashboard admin, petugas, peminjam, pengguna, dan inventaris harus tetap nyaman digunakan di layar ponsel; tabel besar sebaiknya berubah menjadi kartu ringkas, bukan memaksa seluruh halaman melebar.
- Jangan mengarang struktur tabel atau fitur CRUD lain; periksa implementasi dan dump SQL sebelum mengubah alur data.

## Panduan Perubahan

- Pertahankan pendekatan PHP native dan PDO yang sudah digunakan; gunakan prepared statements untuk input yang masuk ke query.
- Escape nilai database saat menampilkan teks HTML, misalnya dengan `htmlspecialchars()`.
- Jaga alur session dan pemeriksaan role saat mengubah autentikasi atau halaman admin.
- Gunakan bahasa Indonesia untuk teks UI yang ditujukan kepada pengguna, kecuali gaya yang sudah ada mengharuskan sebaliknya.
- Jadikan sistem gaya di `login.php` sebagai default untuk semua tampilan baru atau yang diperbarui: `color-scheme: light`; variabel warna `--ink: #172a3a`, `--muted: #647587`, `--brand: #146b68`, `--brand-dark: #105653`, `--mint: #e8f4ef`, `--paper: #fbfaf6`, `--line: #e7e9e3`, dan `--white: #fff`; gunakan `box-sizing: border-box`, tipografi `"Segoe UI", Arial, sans-serif`, teks berwarna `--ink`, latar terang `--paper`, dan tinggi baris sekitar `1.55`.
- Untuk bahasa visual, utamakan layout bersih dengan ruang yang cukup, kartu putih bersudut membulat, garis pembatas halus, bayangan lembut, aksen hijau kebiruan, serta state hover/fokus yang jelas. Gunakan panel intro berwarna `--brand` jika sesuai dengan struktur halaman; jangan memaksakan struktur halaman login pada halaman yang berbeda.
- Semua tampilan harus responsif dan nyaman digunakan di layar ponsel; ikuti breakpoint dan penyusunan ulang layout dari `login.php` sebagai pola. Sertakan fokus keyboard yang terlihat dan HTML semantik.
- Pertahankan CSS langsung di dalam `<style>` seperti halaman publik yang ada, dan jangan menambahkan Bootstrap atau dependency tampilan lain kecuali ada alasan jelas.
- Hindari perubahan tidak terkait. Jangan mengubah data/konfigurasi lokal atau menjalankan skrip yang mengubah password tanpa diminta.
