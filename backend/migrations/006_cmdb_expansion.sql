CREATE TABLE IF NOT EXISTS `ci_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `icon` VARCHAR(50) DEFAULT 'cube',
  `parent_id` INT DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT '#6366f1',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `ci_types`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ci_tags` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `color` VARCHAR(7) DEFAULT '#6366f1',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ci_item_tags` (
  `ci_item_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  PRIMARY KEY (`ci_item_id`, `tag_id`),
  FOREIGN KEY (`ci_item_id`) REFERENCES `ci_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `ci_tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ci_audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ci_item_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `field_name` VARCHAR(100) DEFAULT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `summary` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ci_item_id`) REFERENCES `ci_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_ci_audit_item` (`ci_item_id`),
  INDEX `idx_ci_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `ci_types` (`slug`, `name`, `description`, `icon`, `color`) VALUES
('server', 'Server', 'Physical or virtual server', 'server', '#6366f1'),
('network', 'Network Device', 'Network infrastructure device', 'wifi', '#06b6d4'),
('application', 'Application', 'Software application or service', 'code', '#10b981'),
('storage', 'Storage', 'Storage device or system', 'database', '#f59e0b'),
('database', 'Database', 'Database instance or service', 'database', '#ef4444'),
('workstation', 'Workstation', 'Desktop or laptop computer', 'monitor', '#8b5cf6'),
('mobile', 'Mobile Device', 'Smartphone or tablet', 'smartphone', '#ec4899'),
('peripheral', 'Peripheral', 'Printer, scanner, or other peripheral', 'printer', '#14b8a6'),
('virtual', 'Virtual Infrastructure', 'Virtualization host or VM', 'cloud', '#6366f1'),
('security', 'Security Device', 'Firewall, IDS/IPS, or security appliance', 'shield', '#ef4444'),
('other', 'Other', 'Other configuration item', 'cube', '#6b7280');
