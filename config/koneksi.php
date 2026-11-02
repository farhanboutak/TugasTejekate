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
    
    // Keep the session cookie limited to the browser session; persistent login uses a separate token.
    if (session_status() === PHP_SESSION_NONE) {
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    require_once __DIR__ . '/remember_me.php';
    remember_restore_session($pdo);
    
} catch (PDOException $e) {
    // Jika koneksi gagal
    die("Koneksi database gagal: " . $e->getMessage());
}
?>