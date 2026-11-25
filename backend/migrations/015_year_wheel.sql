CREATE TABLE IF NOT EXISTS `yw_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(7) NOT NULL DEFAULT '#3B82F6',
  `icon` VARCHAR(50) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `customer_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `yw_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category_id` INT DEFAULT NULL,
  `customer_id` INT DEFAULT NULL,
  `assigned_to` INT DEFAULT NULL,
  `status` ENUM('planned','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
  `priority` ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `is_recurring` TINYINT(1) NOT NULL DEFAULT 0,
  `recurrence_config` JSON DEFAULT NULL,
  `parent_task_id` INT DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  FOREIGN KEY (`category_id`) REFERENCES `yw_categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`parent_task_id`) REFERENCES `yw_tasks`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default categories for GovernIT
INSERT IGNORE INTO `yw_categories` (`name`, `color`, `icon`, `sort_order`, `customer_id`) VALUES
('Audit & Compliance', '#EF4444', '🔍', 0, NULL),
('Management Review', '#F59E0B', '👥', 1, NULL),
('Security', '#3B82F6', '🔒', 2, NULL),
('Maintenance', '#10B981', '🔧', 3, NULL),
('HR & Training', '#8B5CF6', '🎓', 4, NULL),
('Finance & Budget', '#EC4899', '💰', 5, NULL);
