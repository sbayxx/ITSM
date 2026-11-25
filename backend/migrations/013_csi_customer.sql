ALTER TABLE `csi_register`
  ADD COLUMN `customer_id` INT DEFAULT NULL AFTER `id`,
  ADD FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL;

-- Move existing CSI entries to GovernIT
UPDATE `csi_register` SET `customer_id` = 1 WHERE `customer_id` IS NULL;

-- GovernIT CSI entries (customer_id = 1)
INSERT INTO `csi_register` (`customer_id`, `title`, `description`, `category`, `source`, `status`, `priority`, `expected_benefit`, `kpi_measure`, `baseline_value`, `target_value`, `current_value`, `created_at`) VALUES
(1, 'Reduce average incident resolution time', 'Analyze and optimize the incident management process to reduce MTTR from 8 hours to 4 hours over the next quarter.', 'process', 'incident_analysis', 'in_progress', 'high', 'Faster restoration of services, improved user satisfaction, reduced business impact', 'Mean Time To Resolve (MTTR)', '8 hours', '4 hours', '5.2 hours', NOW()),
(1, 'Improve Change Success Rate', 'Increase the percentage of successful changes by improving CAB review process and implementation planning.', 'process', 'change_review', 'in_progress', 'high', 'Fewer service disruptions from failed changes, increased change velocity', 'Change Success Rate (%)', '85%', '95%', '91%', NOW()),
(1, 'Reduce Password Reset Volume', 'Implement self-service password reset solution to reduce the number of password reset tickets handled by service desk.', 'technology', 'ticket_trends', 'planned', 'medium', 'Reduced service desk workload, faster password resets for users, 24/7 availability', 'Monthly password reset tickets', '45/month', '10/month', '42/month', NOW());

-- DemoCorp CSI entries (customer_id = 2)
INSERT INTO `csi_register` (`customer_id`, `title`, `description`, `category`, `source`, `status`, `priority`, `expected_benefit`, `kpi_measure`, `baseline_value`, `target_value`, `current_value`, `created_at`) VALUES
(2, 'Standardize device provisioning', 'Create a standardized process for onboarding new devices including imaging, software installation, and security baselines.', 'process', 'service_desk', 'identified', 'medium', 'Consistent device setup, reduced onboarding time, improved security posture', 'New device provisioning time', '4 hours', '1 hour', NULL, NOW()),
(2, 'Deploy endpoint detection and response', 'Evaluate and deploy an EDR solution across all managed endpoints to improve threat detection and response capabilities.', 'technology', 'security_assessment', 'identified', 'critical', 'Improved security visibility, faster threat response, compliance with industry standards', 'Endpoint coverage (%)', '0%', '100%', '15%', NOW()),
(2, 'Network infrastructure documentation', 'Document all network devices, VLANs, firewall rules, and cabling infrastructure to improve troubleshooting and change planning.', 'knowledge', 'network_audit', 'in_review', 'low', 'Faster network troubleshooting, reduced risk during network changes, clearer asset inventory', 'Network devices documented (%)', '20%', '100%', '20%', NOW());

-- TestOrg CSI entries (customer_id = 3)
INSERT INTO `csi_register` (`customer_id`, `title`, `description`, `category`, `source`, `status`, `priority`, `expected_benefit`, `kpi_measure`, `baseline_value`, `target_value`, `current_value`, `created_at`) VALUES
(3, 'Automate software deployment', 'Implement software deployment automation using Microsoft Intune or similar tool to eliminate manual installation.', 'technology', 'service_improvement', 'planned', 'high', 'Reduced manual effort, faster software rollouts, consistent configuration across devices', 'Software deployments automated (%)', '10%', '90%', '10%', NOW()),
(3, 'Improve backup verification process', 'Establish a monthly backup restore testing procedure to ensure backups are actually recoverable.', 'process', 'audit_finding', 'in_progress', 'high', 'Guaranteed backup recoverability, reduced risk of data loss', 'Backup restore tests passed (%)', '60%', '100%', '75%', NOW()),
(3, 'Create service catalog and SLA framework', 'Define a comprehensive service catalog with corresponding SLAs for all IT services offered to internal departments.', 'process', 'management_initiative', 'identified', 'medium', 'Clear service expectations, improved customer satisfaction, measurable service levels', 'Services with defined SLAs', '0', '15', '0', NOW());
