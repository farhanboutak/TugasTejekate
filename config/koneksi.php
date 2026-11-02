<?php
// Konfigurasi Database
$host = "localhost";
$username = "root";
$password = "";
$database = "db_simbar";

// Membuat koneksi menggunakan PDO (Rekomendasi agar aman dan mendukung Prepared Statements)
try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $username, $password);
    
    // Set error mode PDO ke exception agar mudah mendebug jika ada error query
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Aktifkan session jika belum aktif (berguna untuk fitur login nanti)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
} catch (PDOException $e) {
    // Jika koneksi gagal
    die("Koneksi database gagal: " . $e->getMessage());
}
?>