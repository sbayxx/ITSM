-- Seed customers
INSERT INTO `customers` (`name`, `slug`, `description`, `is_active`, `settings`) VALUES
('GovernIT', 'governit', 'GovernIT - Primary customer with Azure AD integration', 1, '{\"azure_ad\":{\"tenant_id\":\"e4ae34ca-6e95-4065-9b5f-244d7d6df828\",\"client_id\":\"dc105afa-e48f-47d3-92ae-19f4263fec5a\",\"client_secret\":\"REDACTED_AZURE_CLIENT_SECRET\",\"sync_users\":true,\"licensed_only\":true,\"user_type_filter\":\"\"},\"intune\":{\"enabled\":true},\"sync\":{\"schedule\":\"manual\"}}'),
('DemoCorp', 'democorp', 'Demo customer for testing and demonstrations', 1, '{\"azure_ad\":{\"sync_users\":false,\"licensed_only\":true},\"intune\":{\"enabled\":false},\"sync\":{\"schedule\":\"manual\"}}'),
('TestOrg', 'testorg', 'Test organization for development', 1, '{\"azure_ad\":{\"sync_users\":false,\"licensed_only\":true},\"intune\":{\"enabled\":false},\"sync\":{\"schedule\":\"manual\"}}');

-- Migrate existing data to GovernIT (customer_id = 1)
UPDATE `users` SET `customer_id` = 1 WHERE `customer_id` IS NULL;
UPDATE `tickets` SET `customer_id` = 1 WHERE `customer_id` IS NULL;
UPDATE `ci_items` SET `customer_id` = 1 WHERE `customer_id` IS NULL;
UPDATE `intune_devices` SET `customer_id` = 1 WHERE `customer_id` IS NULL;

-- Assign existing admin to GovernIT
INSERT IGNORE INTO `customer_user` (`customer_id`, `user_id`)
  SELECT 1, `id` FROM `users` WHERE `role` = 'admin' AND `deleted_at` IS NULL;
