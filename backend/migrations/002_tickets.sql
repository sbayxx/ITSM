CREATE TABLE IF NOT EXISTS `service_catalog` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `fulfillment_workflow` JSON DEFAULT NULL,
  `estimated_fulfillment_hours` DECIMAL(8,2) DEFAULT NULL,
  `requires_approval` TINYINT(1) DEFAULT 0,
  `approval_chain` JSON DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_number` VARCHAR(20) NOT NULL UNIQUE,
  `type` ENUM('incident','service_request','problem','change') NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'new',
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `priority` ENUM('critical','high','medium','low') DEFAULT 'medium',
  `impact` ENUM('high','medium','low') DEFAULT NULL,
  `urgency` ENUM('high','medium','low') DEFAULT NULL,
  `source` VARCHAR(50) DEFAULT 'portal',
  `category` VARCHAR(100) DEFAULT NULL,
  `subcategory` VARCHAR(100) DEFAULT NULL,
  `reported_by` INT DEFAULT NULL,
  `assigned_to` INT DEFAULT NULL,
  `assigned_group` VARCHAR(100) DEFAULT NULL,
  `organization_id` INT DEFAULT NULL,
  `ci_item_id` INT DEFAULT NULL,
  `service_catalog_item_id` INT DEFAULT NULL,
  `sla_response_deadline` DATETIME DEFAULT NULL,
  `sla_resolution_deadline` DATETIME DEFAULT NULL,
  `sla_breached` TINYINT(1) DEFAULT 0,
  `resolution` TEXT DEFAULT NULL,
  `resolution_date` DATETIME DEFAULT NULL,
  `closure_code` VARCHAR(50) DEFAULT NULL,
  `closure_notes` TEXT DEFAULT NULL,
  `is_major` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `closed_at` DATETIME DEFAULT NULL,
  INDEX `idx_type` (`type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_priority` (`priority`),
  INDEX `idx_assigned` (`assigned_to`),
  INDEX `idx_created` (`created_at`),
  FOREIGN KEY (`reported_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`service_catalog_item_id`) REFERENCES `service_catalog`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `incidents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL UNIQUE,
  `impact` ENUM('high','medium','low') DEFAULT NULL,
  `urgency` ENUM('high','medium','low') DEFAULT NULL,
  `is_major` TINYINT(1) DEFAULT 0,
  `major_criteria` TEXT DEFAULT NULL,
  `workaround` TEXT DEFAULT NULL,
  `known_error_id` INT DEFAULT NULL,
  `root_cause` TEXT DEFAULT NULL,
  `resolution_time_minutes` INT DEFAULT NULL,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `service_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL UNIQUE,
  `catalog_item_id` INT DEFAULT NULL,
  `fulfillment_data` JSON DEFAULT NULL,
  `approval_status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` INT DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `fulfillment_notes` TEXT DEFAULT NULL,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`catalog_item_id`) REFERENCES `service_catalog`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `problems` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL UNIQUE,
  `root_cause` TEXT DEFAULT NULL,
  `workaround` TEXT DEFAULT NULL,
  `known_error` TINYINT(1) DEFAULT 0,
  `known_error_id` VARCHAR(50) DEFAULT NULL,
  `resolution` TEXT DEFAULT NULL,
  `resolution_date` DATETIME DEFAULT NULL,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `problem_incidents` (
  `problem_id` INT NOT NULL,
  `incident_id` INT NOT NULL,
  PRIMARY KEY (`problem_id`, `incident_id`),
  FOREIGN KEY (`problem_id`) REFERENCES `problems`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`incident_id`) REFERENCES `incidents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `changes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL UNIQUE,
  `change_type` ENUM('normal','standard','emergency') NOT NULL DEFAULT 'normal',
  `risk_level` ENUM('low','medium','high','critical') DEFAULT 'low',
  `impact_assessment` TEXT DEFAULT NULL,
  `rollback_plan` TEXT DEFAULT NULL,
  `implementation_plan` TEXT DEFAULT NULL,
  `test_plan` TEXT DEFAULT NULL,
  `cab_required` TINYINT(1) DEFAULT 0,
  `cab_decision` ENUM('pending','approved','rejected','deferred') DEFAULT 'pending',
  `cab_notes` TEXT DEFAULT NULL,
  `scheduled_start` DATETIME DEFAULT NULL,
  `scheduled_end` DATETIME DEFAULT NULL,
  `implemented_at` DATETIME DEFAULT NULL,
  `review_required` TINYINT(1) DEFAULT 0,
  `reviewed_at` DATETIME DEFAULT NULL,
  `review_notes` TEXT DEFAULT NULL,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cab_votes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `change_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `vote` ENUM('approve','reject','abstain') NOT NULL,
  `comments` TEXT DEFAULT NULL,
  `voted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`change_id`) REFERENCES `changes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
