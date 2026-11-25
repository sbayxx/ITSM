CREATE TABLE IF NOT EXISTS `vendors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `api_base_url` VARCHAR(500) DEFAULT NULL,
  `integration_mode` ENUM('email','api','both') NOT NULL DEFAULT 'email',
  `notes` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_vendor_customer` (`customer_id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vendor_api_keys` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT NOT NULL,
  `key_prefix` VARCHAR(12) NOT NULL,
  `key_hash` CHAR(64) NOT NULL,
  `last_used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uk_vendor_key_hash` (`key_hash`),
  INDEX `idx_vendor_key_vendor` (`vendor_id`),
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `tickets`
  ADD COLUMN `vendor_id` INT DEFAULT NULL AFTER `email_from`;

ALTER TABLE `tickets`
  ADD COLUMN `external_ref` VARCHAR(255) DEFAULT NULL AFTER `vendor_id`,
  ADD COLUMN `vendor_requested_at` DATETIME DEFAULT NULL AFTER `external_ref`;

ALTER TABLE `tickets`
  ADD INDEX `idx_tickets_vendor` (`vendor_id`),
  ADD INDEX `idx_tickets_external_ref` (`external_ref`);

ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL;

ALTER TABLE `ticket_comments`
  ADD COLUMN `source` VARCHAR(20) NOT NULL DEFAULT 'internal' AFTER `is_internal`;
