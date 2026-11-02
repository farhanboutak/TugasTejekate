<div align="center">

# 📦 SIMBAR

### Sistem Inventaris & Peminjaman Barang

Kelola inventaris, ajukan peminjaman, dan pantau status barang dalam satu aplikasi web yang sederhana dan responsif.

<br>

![PHP](https://img.shields.io/badge/PHP-Native-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%2FMariaDB-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![XAMPP](https://img.shields.io/badge/Local%20Server-XAMPP-FB7A24?style=for-the-badge&logo=apachefriends&logoColor=white)
![Bahasa](https://img.shields.io/badge/Bahasa-Indonesia-146b68?style=for-the-badge)

<br>

[Fitur](#-fitur-utama) · [Mulai](#-cara-menjalankan) · [Peran](#-jenis-pengguna) · [Struktur](#-struktur-proyek)

</div>

---

## ✨ Tentang proyek

SIMBAR adalah aplikasi untuk mengelola barang inventaris dan proses peminjaman. Aplikasi dibuat menggunakan PHP native dan MySQL/MariaDB, dengan antarmuka berbahasa Indonesia yang menyesuaikan layar desktop maupun ponsel.

## 🚀 Fitur utama

- **Inventaris:** tambah, ubah, dan kelola stok barang.
- **Foto produk:** unggah foto JPG, PNG, atau WebP (maksimal 5 MB), lalu tampilkan pada katalog barang.
- **Peminjaman:** ajukan barang berdasarkan ketersediaan stok dan pantau riwayat serta status pengajuan.
- **Pengelolaan transaksi:** petugas dan admin dapat mencari, memfilter, dan memperbarui status peminjaman.
- **Pengelolaan akun:** admin dapat mengatur pengguna dan perannya.
- **Profil akun:** ubah informasi profil dan kata sandi.
- **Ekspor laporan:** unduh daftar peminjaman sebagai CSV.
- **Akses berbasis peran:** halaman dan tindakan dibatasi sesuai peran pengguna.
- **Tampilan responsif:** halaman inventaris dan dashboard dirancang agar tetap nyaman digunakan di layar kecil.

## 👥 Jenis pengguna

| Peran | Kemampuan |
|---|---|
| **Admin** | Mengelola transaksi, inventaris, akun pengguna, dan laporan. |
| **Petugas** | Mengelola inventaris dan memproses transaksi peminjaman. |
| **Peminjam** | Melihat katalog, mengajukan peminjaman, dan memantau riwayat. |

Akun baru yang dibuat melalui registrasi akan menggunakan peran **peminjam**.

## 🧰 Teknologi

- PHP native dan PDO
- MySQL/MariaDB
- Apache melalui XAMPP
- HTML, CSS, dan JavaScript
- Bootstrap 5 dan Bootstrap Icons pada dashboard admin

## 🛠️ Cara menjalankan

### Persiapan

Pastikan XAMPP (Apache dan MySQL) sudah terpasang dan berjalan.

### Instalasi

1. **Salin atau clone proyek** ke folder `htdocs` XAMPP. Contoh:

   ```text
   C:\xampp\htdocs\TugasTejekate
   ```

2. **Buat database** bernama `db_simbar` melalui phpMyAdmin (`http://localhost/phpmyadmin`).

3. **Impor skema dan data awal** dengan memilih database tersebut, lalu mengimpor [`database/db_simbar.sql`](database/db_simbar.sql).

   Jika database sudah lama dan kolom foto belum tersedia, jalankan migrasi satu kali:

   ```sql
   ALTER TABLE barang
   ADD COLUMN foto VARCHAR(255) DEFAULT NULL AFTER kategori;
   ```

   Skrip migrasi juga tersedia di [`database/migrasi_foto_barang.sql`](database/migrasi_foto_barang.sql). Jangan jalankan jika kolom `foto` sudah ada.

4. **Periksa koneksi database** di [`config/koneksi.php`](config/koneksi.php). Sesuaikan host, nama database, username, dan password dengan konfigurasi lokalmu.

5. **Buka aplikasi** di browser:

   ```text
   http://localhost/TugasTejekate/
   ```

   Registrasi akun melalui halaman login atau gunakan akun yang tersedia di database lokalmu.

### Upload foto

Foto produk disimpan di `uploads/barang/`. Pastikan Apache/PHP memiliki izin untuk membuat dan menulis file di folder tersebut. Format selain JPG, PNG, dan WebP, serta file lebih dari 5 MB, akan ditolak.

## 🗂️ Struktur proyek

```text
.
├── admin/                 # Dashboard admin, akun, inventaris, dan transaksi
├── config/                # Koneksi database dan autentikasi
├── database/              # Dump database dan migrasi
├── peminjaman/            # Katalog serta halaman peminjam
├── petugas/               # Dashboard petugas
├── uploads/barang/        # Foto produk yang diunggah
├── akun.php               # Profil pengguna
├── index.php              # Halaman utama
├── login.php              # Login dan registrasi
└── register_proses.php    # Proses registrasi
```

## 🔐 Catatan keamanan

- Konfigurasi database pada [`config/koneksi.php`](config/koneksi.php) ditujukan untuk lingkungan lokal. Jangan unggah kredensial produksi ke repositori.
- Gunakan akun dengan hak akses minimum yang sesuai untuk deployment.
- Endpoint perubahan data menggunakan pemeriksaan peran dan token CSRF.
- Kata sandi pengguna disimpan dalam bentuk hash.

## 🧪 Pengujian

Proyek ini belum memiliki test suite otomatis. Untuk memeriksa sintaks file PHP secara manual:

```bash
php -l index.php
```

## 📚 Kredit

Proyek ini dibuat sebagai tugas web dari Pak Fera Zulkarnain.

---

<div align="center">
Dibuat dengan PHP untuk membantu pengelolaan inventaris dan peminjaman barang.
</div>
