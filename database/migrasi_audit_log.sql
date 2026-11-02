-- Jalankan satu kali pada database SIMBAR lama untuk menyimpan jejak aktivitas.
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) DEFAULT NULL,
  `actor_name` varchar(100) NOT NULL,
  `actor_role` varchar(20) NOT NULL,
  `action` varchar(60) NOT NULL,
  `entity` varchar(40) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `summary` varchar(255) NOT NULL,
  `metadata` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  KEY `idx_audit_logs_actor` (`actor_id`, `created_at`),
  KEY `idx_audit_logs_action` (`action`, `created_at`),
  CONSTRAINT `fk_audit_logs_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
