-- Add customer scoping to approval chains (multi-tenant isolation)
-- approvals was created in 007 and never received customer_id in 010_customers.sql,
-- which left the only ticket sub-table outside the tenant boundary.
ALTER TABLE `approvals`
  ADD COLUMN `customer_id` INT DEFAULT NULL AFTER `ticket_id`,
  ADD INDEX `idx_approvals_customer` (`customer_id`),
  ADD FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL;

-- Backfill every existing approval from its parent ticket
UPDATE `approvals` a
JOIN `tickets` t ON t.id = a.ticket_id
SET a.customer_id = t.customer_id
WHERE a.customer_id IS NULL;

-- Fallback for orphaned approvals whose ticket has no customer
UPDATE `approvals` SET `customer_id` = 1 WHERE `customer_id` IS NULL;
