<?php
// Fungsi untuk membatasi akses berdasarkan role tertentu
function cek_akses($allowed_roles) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Jika belum login, lempar ke halaman login utama
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    // Jika role tidak sesuai dengan hak akses halaman
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        echo "<script>alert('Akses ditolak! Anda tidak memiliki hak akses ke halaman ini.'); window.location='../login.php';</script>";
        exit();
    }
}
?>