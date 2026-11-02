-- Jalankan satu kali pada database SIMBAR yang sudah berisi tabel barang.
ALTER TABLE `barang`
  ADD COLUMN `foto` varchar(255) DEFAULT NULL AFTER `kategori`;
