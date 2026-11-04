# Konteks Proyek untuk AI Agent

## Ringkasan

SIMBAR adalah aplikasi web sederhana untuk mengelola inventaris barang dan transaksi peminjaman. Proyek ini dibuat sebagai tugas web dan dijalankan secara lokal menggunakan PHP, MySQL/MariaDB, dan XAMPP.

## Teknologi dan Cara Menjalankan

- Backend: PHP native, tanpa framework.
- Database: MySQL/MariaDB, database bernama `db_simbar`.
- Koneksi database menggunakan PDO dan session PHP.
- Impor skema serta data awal dari `database/db_simbar.sql` melalui phpMyAdmin. Untuk database lama, jalankan migrasi yang belum diterapkan: `database/migrasi_foto_barang.sql` untuk kolom `barang.foto` dan `database/migrasi_arsip_barang.sql` untuk `barang.diarsipkan`.
- Jalankan proyek lewat Apache XAMPP dan buka `index.php` di browser.
- Konfigurasi koneksi lokal berada di `config/koneksi.php`. Jangan menambahkan kredensial database ke file lain atau dokumentasi.
- Belum ada konfigurasi dependency manager, framework, maupun test suite otomatis. Untuk validasi file PHP, gunakan `php -l` jika PHP CLI tersedia.

## Struktur dan Alur Aplikasi

- `index.php`: landing page publik; tombol masuk mengarah ke `login.php`.
- `login.php`: formulir login dengan opsi ingat saya dan mode registrasi melalui `?mode=register`; pesan hasil ditampilkan dari parameter `pesan`.
- `login_proses.php`: memvalidasi CSRF, memverifikasi password dengan `password_verify()`, membuat session, dan jika dipilih menerbitkan token ingat saya dengan masa berlaku 30 hari.
- `config/remember_me.php`: membuat, memutar, memulihkan, dan mencabut token ingat saya; cookie `HttpOnly`, `SameSite=Lax`, dan `Secure` ketika HTTPS. Hanya hash token yang disimpan di tabel `remember_tokens`.
- `register_proses.php`: validasi dan pembuatan akun baru dengan role tetap `peminjam`, password hash `password_hash()`, dan perlindungan CSRF.
- `peminjaman/index.php`: halaman khusus role `peminjam` untuk mengirim pengajuan peminjaman berstatus `Pending` dan melihat riwayat akun. Pengajuan memeriksa stok yang tersedia setelah mengurangi jumlah transaksi berstatus `Pending` atau `Disetujui`.
- `peminjaman/index.php` juga menampilkan pembaruan status terkini dari transaksi peminjam serta menyediakan tautan profil akun.
- `petugas/index.php`: dashboard untuk role dengan izin `transactions.view`; perubahan status memerlukan `transactions.manage`.
- `admin/barang.php` dan `admin/barang_aksi.php`: pengelolaan inventaris untuk admin/petugas; stok tidak boleh dikurangi di bawah jumlah transaksi Pending/Disetujui, barang dapat diarsipkan atau dipulihkan tanpa menghapus transaksi/foto, dan foto barang dapat diunggah atau diganti. Daftar katalog peminjaman hanya menampilkan barang aktif.
- Foto inventaris disimpan di `uploads/barang/` dengan nama acak; hanya JPG, PNG, dan WebP hingga 5 MB yang diterima. Form admin menampilkan pratinjau dan dapat mengambil foto dari perangkat atau URL HTTPS publik langsung ke file gambar; URL diunduh pada server dengan pembatasan alamat publik, tanpa redirect, lalu divalidasi sebagai gambar. Pengguna melihat foto pada katalog di `peminjaman/index.php`.
- `admin/ekspor_peminjaman.php`: ekspor CSV transaksi dengan filter status, rentang tanggal pinjam, dan pencarian; nilai teks CSV dinetralkan agar tidak dieksekusi sebagai formula spreadsheet.
- `admin/aksi_status.php`: endpoint POST perubahan status yang digunakan admin maupun petugas; validasi transisi status dan CSRF, lalu kembali ke dashboard sesuai role.
- `admin/hapus.php`: endpoint POST hapus transaksi dengan CSRF dan izin `transactions.delete`.
- `admin/pengguna.php` dan `admin/pengguna_aksi.php`: pengelolaan akun dengan izin `users.manage`, validasi input, hash password, dan perlindungan akun admin terakhir.
- `admin/peran.php` dan `admin/peran_aksi.php`: membuat role serta mengubah label dan izin modulnya; role yang sedang dipakai tidak dihapus.
- `admin/log.php`: halaman pencarian jejak aktivitas dengan izin `audit.view`.
- `config/audit.php`: menulis log aktivitas dengan snapshot nama/role pelaku, metadata perubahan non-rahasia, alamat IP, dan waktu; jangan pernah memasukkan password atau token ke metadata.
- `akun.php`: profil bersama semua role untuk memperbarui nama/username dan opsional password; perubahan memerlukan password saat ini.
- `config/koneksi.php`: membuat koneksi PDO, memulai session dengan cookie yang aman, lalu memulihkan login dari token ingat saya bila session sudah berakhir.
- `config/auth.php`: katalog capability dan pemeriksaan `cek_akses($permission)` berdasarkan tabel role; izin utama mencakup transaksi, inventaris, pengguna/role, log, dan peminjaman.
- `admin/index.php`: dashboard admin dengan ringkasan, filter transaksi responsif, tautan pengelolaan, aktivitas terbaru, dan thumbnail foto barang.
- `logout.php`: mencabut token ingat saya pada perangkat tersebut, menghapus session, dan mengarahkan user kembali ke halaman login.
- `update_password.php`: skrip utilitas yang langsung mengubah password admin; jangan menjalankannya tanpa persetujuan eksplisit dan jangan mengeksposnya sebagai endpoint publik.
- `database/db_simbar.sql`: definisi dan data awal untuk tabel `users`, `roles`, `barang`, `peminjaman`, `remember_tokens`, dan `audit_logs`. Database lama perlu menjalankan migrasi terkait, termasuk `database/migrasi_role_dinamis.sql`, satu kali.

Skema utama:

- `users`: nama, username, hash password, dan `role_key` dari tabel `roles`.
- `roles`: label role dan daftar izin capability dalam JSON; registrasi publik tetap membuat akun dengan role bawaan `peminjam`.
- `barang`: nama barang, kategori, foto opsional, status arsip (`diarsipkan`), stok, dan kondisi.
- `peminjaman`: peminjam, barang, tanggal pinjam/kembali, jumlah, dan status (`Pending`, `Disetujui`, `Ditolak`, `Dikembalikan`).

## Status Implementasi yang Perlu Diingat

- Setiap halaman dan endpoint yang mengelola data tetap harus memanggil `cek_akses()` dengan capability yang sesuai; jangan mengasumsikan halaman otomatis terlindungi.
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
