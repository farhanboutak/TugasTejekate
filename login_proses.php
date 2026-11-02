<?php
require_once 'config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        // Query select user berdasarkan username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Jika user ditemukan dan password valid
        if ($user && password_verify($password, $user['password'])) {
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Redirect berdasarkan role masing-masing
            if ($user['role'] == 'admin') {
                header("Location: admin/index.php");
                exit();
            } elseif ($user['role'] == 'petugas') {
                header("Location: petugas/index.php");
                exit();
            } elseif ($user['role'] == 'peminjam') {
                header("Location: peminjaman/index.php");
                exit();
            }
        } else {
            // Jika gagal, kembalikan ke halaman login dengan pesan error
            header("Location: login.php?pesan=gagal");
            exit();
        }
    } catch (PDOException $e) {
        die("Terjadi kesalahan sistem: " . $e->getMessage());
    }
}
?>