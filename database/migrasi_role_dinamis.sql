-- Jalankan satu kali pada database SIMBAR lama untuk menambah role dan permission dinamis.
CREATE TABLE IF NOT EXISTS `roles` (
  `role_key` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `permissions` text NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`role_key`),
  UNIQUE KEY `role_label` (`label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `roles` (`role_key`, `label`, `permissions`, `is_system`) VALUES
('admin', 'Admin', '["transactions.view","transactions.manage","transactions.export","transactions.delete","inventory.manage","users.manage","audit.view","borrow.use"]', 1),
('petugas', 'Petugas', '["transactions.view","transactions.manage","transactions.export","inventory.manage"]', 1),
('peminjam', 'Peminjam', '["borrow.use"]', 1);

ALTER TABLE `users`
  MODIFY `role` varchar(50) NOT NULL DEFAULT 'peminjam';