-- Default admin user (password: admin123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `is_active`, `email_verified_at`) VALUES
('System Administrator', 'admin@itsm.local', '$2y$12$LJ3m4ys3Lk0TSwHnbfOMiOXPmUq3s5x5n5W5c5Y5g5j5k5l5m5n5', 'admin', 1, NOW()),
('IT Support Agent', 'agent@itsm.local', '$2y$12$LJ3m4ys3Lk0TSwHnbfOMiOXPmUq3s5x5n5W5c5Y5g5j5k5l5m5n5', 'agent', 1, NOW()),
('IT Manager', 'manager@itsm.local', '$2y$12$LJ3m4ys3Lk0TSwHnbfOMiOXPmUq3s5x5n5W5c5Y5g5j5k5l5m5n5', 'manager', 1, NOW());

-- Roles
INSERT INTO `roles` (`name`, `slug`, `description`) VALUES
('Administrator', 'admin', 'Full system access'),
('IT Agent', 'agent', 'IT support agent with ticket management'),
('IT Manager', 'manager', 'Can manage teams and approve changes'),
('End User', 'user', 'Can submit tickets and view own requests');

-- Permissions
INSERT INTO `permissions` (`name`, `slug`) VALUES
('Create Tickets', 'create_tickets'),
('View All Tickets', 'view_all_tickets'),
('Assign Tickets', 'assign_tickets'),
('Edit Tickets', 'edit_tickets'),
('Delete Tickets', 'delete_tickets'),
('Approve Changes', 'approve_changes'),
('Manage Users', 'manage_users'),
('Manage CMDB', 'manage_cmdb'),
('Manage Knowledge', 'manage_knowledge'),
('Manage CSI', 'manage_csi'),
('Manage SLAs', 'manage_slas'),
('View Reports', 'view_reports'),
('Manage GDPR', 'manage_gdpr'),
('Manage Settings', 'manage_settings');

-- Admin gets all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'admin';

-- Agent permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'agent' AND p.slug IN ('create_tickets', 'view_all_tickets', 'assign_tickets', 'edit_tickets', 'manage_knowledge');

-- Manager permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'manager' AND p.slug IN ('create_tickets', 'view_all_tickets', 'assign_tickets', 'edit_tickets', 'approve_changes', 'manage_csi', 'view_reports', 'manage_knowledge');

-- User permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'user' AND p.slug IN ('create_tickets');

-- SLA Contracts
INSERT INTO `sla_contracts` (`name`, `priority`, `response_time_minutes`, `resolution_time_minutes`, `escalation_minutes`, `business_hours_only`, `is_active`) VALUES
('Critical SLA', 'critical', 15, 240, 30, 0, 1),
('High SLA', 'high', 30, 480, 60, 1, 1),
('Medium SLA', 'medium', 60, 1440, 120, 1, 1),
('Low SLA', 'low', 240, 4320, 480, 1, 1);

-- Data Retention Policies
INSERT INTO `data_retention_policies` (`entity_type`, `retention_days`, `action`) VALUES
('audit_log', 2555, 'archive'),
('tickets', 1825, 'anonymize'),
('sessions', 90, 'delete'),
('gdpr_consent_log', 2555, 'archive'),
('erasure_requests', 3650, 'archive');

-- Service Catalog items
INSERT INTO `service_catalog` (`name`, `description`, `category`, `requires_approval`, `estimated_fulfillment_hours`) VALUES
('New User Account', 'Create a new user account with standard access', 'User Management', 0, 2.00),
('Password Reset', 'Reset forgotten password', 'User Management', 0, 0.50),
('Software Installation', 'Install approved software on workstation', 'Software', 0, 1.00),
('Hardware Request', 'Request new hardware (laptop, monitor, etc.)', 'Hardware', 1, 48.00),
('Network Access', 'Grant network access to a user or device', 'Network', 0, 2.00),
('VPN Access', 'Configure VPN access for remote work', 'Network', 1, 4.00),
('Email Account', 'Create new email account / mailbox', 'User Management', 0, 1.00),
('Mobile Device Setup', 'Configure mobile device for corporate access', 'Hardware', 0, 2.00);

-- Sample CI Items
INSERT INTO `ci_items` (`name`, `type`, `status`, `location`, `description`) VALUES
('Web Server 01', 'server', 'active', 'DC1-RackA', 'Primary web server for production'),
('Database Server 01', 'server', 'active', 'DC1-RackB', 'Primary MySQL database server'),
('Mail Server 01', 'server', 'active', 'DC1-RackA', 'Corporate email server'),
('Core Switch 01', 'network', 'active', 'DC1-RackC', 'Core network switch'),
('Firewall 01', 'network', 'active', 'DC1-RackC', 'Perimeter firewall'),
('ERP Application', 'application', 'active', 'DC1', 'Enterprise Resource Planning system'),
('CRM Application', 'application', 'active', 'DC1', 'Customer Relationship Management'),
('Backup System', 'storage', 'active', 'DC1-RackB', 'Automated backup solution');
