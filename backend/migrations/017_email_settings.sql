CREATE TABLE IF NOT EXISTS `email_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT DEFAULT NULL,
  `customer_id` INT DEFAULT NULL,
  `direction` ENUM('inbound','outbound') NOT NULL,
  `from_email` VARCHAR(255) NOT NULL,
  `to_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_text` TEXT DEFAULT NULL,
  `body_html` TEXT DEFAULT NULL,
  `message_id` VARCHAR(255) DEFAULT NULL,
  `in_reply_to` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','sent','received','failed') NOT NULL DEFAULT 'pending',
  `error_message` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ticket` (`ticket_id`),
  INDEX `idx_customer` (`customer_id`),
  INDEX `idx_direction` (`direction`),
  INDEX `idx_message_id` (`message_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `tickets`
  ADD COLUMN `email_message_id` VARCHAR(255) DEFAULT NULL AFTER `sla_breached`,
  ADD COLUMN `email_from` VARCHAR(255) DEFAULT NULL AFTER `email_message_id`,
  ADD INDEX `idx_email_message_id` (`email_message_id`);
