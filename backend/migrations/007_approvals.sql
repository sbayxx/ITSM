CREATE TABLE IF NOT EXISTS `approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `step_order` INT NOT NULL DEFAULT 1,
  `approver_type` ENUM('user','role') NOT NULL DEFAULT 'role',
  `approver_id` INT DEFAULT NULL,
  `approver_role` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected','skipped') NOT NULL DEFAULT 'pending',
  `comments` TEXT DEFAULT NULL,
  `decided_by` INT DEFAULT NULL,
  `decided_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approver_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`decided_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_approvals_ticket` (`ticket_id`),
  INDEX `idx_approvals_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
