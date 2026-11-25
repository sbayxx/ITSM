-- DemoCorp users (customer_id = 2)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `customer_id`, `is_active`, `email_verified_at`, `created_at`) VALUES
('Alice Johnson', 'alice@democorp.com', '$2y$10$an2qskEes.1ml13LvlH5veBQKHNqI2vt0QJAuhXlCou.cm/ceZCQW', 'agent', 2, 1, NOW(), NOW()),
('Bob Smith', 'bob@democorp.com', '$2y$10$an2qskEes.1ml13LvlH5veBQKHNqI2vt0QJAuhXlCou.cm/ceZCQW', 'user', 2, 1, NOW(), NOW()),
('Carol Davis', 'carol@democorp.com', '$2y$10$an2qskEes.1ml13LvlH5veBQKHNqI2vt0QJAuhXlCou.cm/ceZCQW', 'manager', 2, 1, NOW(), NOW());

SET @alice_id = LAST_INSERT_ID();
SET @bob_id = @alice_id + 1;
SET @carol_id = @alice_id + 2;

-- TestOrg users (customer_id = 3)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `customer_id`, `is_active`, `email_verified_at`, `created_at`) VALUES
('Dave Wilson', 'dave@testorg.com', '$2y$10$an2qskEes.1ml13LvlH5veBQKHNqI2vt0QJAuhXlCou.cm/ceZCQW', 'agent', 3, 1, NOW(), NOW()),
('Eve Martin', 'eve@testorg.com', '$2y$10$an2qskEes.1ml13LvlH5veBQKHNqI2vt0QJAuhXlCou.cm/ceZCQW', 'user', 3, 1, NOW(), NOW()),
('Frank Lee', 'frank@testorg.com', '$2y$10$an2qskEes.1ml13LvlH5veBQKHNqI2vt0QJAuhXlCou.cm/ceZCQW', 'manager', 3, 1, NOW(), NOW());

SET @dave_id = LAST_INSERT_ID();
SET @eve_id = @dave_id + 1;
SET @frank_id = @dave_id + 2;

-- Assign agents/managers to customer_user pivot
INSERT IGNORE INTO `customer_user` (`customer_id`, `user_id`) VALUES
(2, @alice_id), (2, @carol_id),
(3, @dave_id), (3, @frank_id);

-- DemoCorp CI Items (customer_id = 2)
INSERT INTO `ci_items` (`customer_id`, `name`, `type`, `ci_type_id`, `status`, `fqdn`, `ip_address`, `os`, `os_version`, `cpu`, `ram`, `disk_space`, `environment`, `description`, `created_at`) VALUES
(2, 'Web Server 01', 'server', 1, 'active', 'websvr01.democorp.com', '10.10.1.10', 'Ubuntu', '22.04 LTS', '4 vCPU', '16 GB', '256 GB SSD', 'production', 'Primary web server for DemoCorp website', NOW()),
(2, 'DemoCorp DB Server', 'server', 5, 'active', 'dbsvr01.democorp.com', '10.10.1.20', 'Windows Server', '2022', '8 vCPU', '32 GB', '512 GB SSD', 'production', 'Main database server for DemoCorp applications', NOW()),
(2, 'Office Switch', 'network', 2, 'active', NULL, '10.10.0.254', 'Cisco IOS', '15.2', NULL, NULL, NULL, 'production', 'Main office network switch', NOW());

-- TestOrg CI Items (customer_id = 3)
INSERT INTO `ci_items` (`customer_id`, `name`, `type`, `ci_type_id`, `status`, `fqdn`, `ip_address`, `os`, `os_version`, `cpu`, `ram`, `disk_space`, `environment`, `description`, `created_at`) VALUES
(3, 'Developer Workstation', 'workstation', 6, 'active', 'devws01.testorg.com', '192.168.1.100', 'macOS', '14.5', 'Apple M3', '16 GB', '512 GB', 'development', 'Development workstation for engineering team', NOW()),
(3, 'TestOrg ERP', 'application', 3, 'active', 'erp.testorg.com', '192.168.1.50', 'Windows Server', '2022', '8 vCPU', '64 GB', '1 TB', 'production', 'Enterprise resource planning system', NOW());

-- DemoCorp Tickets (customer_id = 2)
INSERT INTO `tickets` (`customer_id`, `ticket_number`, `type`, `status`, `title`, `description`, `priority`, `impact`, `urgency`, `source`, `category`, `reported_by`, `assigned_to`, `created_at`, `updated_at`) VALUES
(2, 'INC-26-9001', 'incident', 'in_progress', 'Email delivery failure', 'Users in the Sales department cannot send or receive emails. Error message: "Connection timed out."', 'high', 'high', 'high', 'portal', 'email', @bob_id, @alice_id, DATE_SUB(NOW(), INTERVAL 2 DAY), NOW()),
(2, 'INC-26-9002', 'incident', 'new', 'Printer not responding on floor 2', 'The network printer on floor 2 shows offline status. Paper jam cleared but still not working.', 'medium', 'medium', 'medium', 'email', 'hardware', @bob_id, @alice_id, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'SR-26-9001', 'service_request', 'open', 'New employee onboarding - laptop setup', 'New developer starting next week. Need a MacBook Pro with full development environment configured.', 'medium', 'low', 'medium', 'portal', 'hardware', @bob_id, @alice_id, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY));

-- TestOrg Tickets (customer_id = 3)
INSERT INTO `tickets` (`customer_id`, `ticket_number`, `type`, `status`, `title`, `description`, `priority`, `impact`, `urgency`, `source`, `category`, `reported_by`, `assigned_to`, `created_at`, `updated_at`) VALUES
(3, 'PBL-26-8001', 'problem', 'under_investigation', 'Intermittent VPN disconnections', 'Multiple users report VPN dropping every 30-60 minutes. Affects remote work productivity.', 'critical', 'high', 'high', 'portal', 'network', @eve_id, @dave_id, DATE_SUB(NOW(), INTERVAL 3 DAY), NOW()),
(3, 'CHG-26-8001', 'change', 'scheduled', 'ERP system upgrade to v5.2', 'Planned upgrade of TestOrg ERP from v5.1 to v5.2. Includes database migration and new module deployment.', 'medium', 'medium', 'low', 'portal', 'software', @frank_id, @dave_id, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));
