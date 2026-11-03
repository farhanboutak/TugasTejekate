-- Jalankan satu kali jika penambahan akun gagal karena users.id tidak otomatis bertambah.
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;