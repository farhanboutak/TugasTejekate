<?php
require_once 'config/koneksi.php';

// Generate hash baru yang pasti valid untuk password 'admin123'
$password_baru = password_hash('admin123', PASSWORD_DEFAULT);

// Update password untuk user admin
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->execute([$password_baru]);

echo "Password untuk user 'admin' berhasil di-update! Silakan hapus file ini demi keamanan.";
?>