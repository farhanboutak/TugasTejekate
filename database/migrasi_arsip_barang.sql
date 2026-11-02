-- Jalankan satu kali pada database SIMBAR yang sudah memiliki tabel barang.
ALTER TABLE `barang`
  ADD COLUMN `diarsipkan` tinyint(1) NOT NULL DEFAULT 0 AFTER `foto`;
