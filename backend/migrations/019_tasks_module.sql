-- Tasks module: promote yw_tasks from a Year-Wheel-only table to a general
-- worklist that optionally feeds the wheel.
--
-- The dashboard needs one place holding *all* of a user's tasks, including
-- tasks that must never appear on the wheel. Rather than introduce a second
-- table (which would need a sync path into the wheel and could hold two
-- divergent copies of the same work), yw_tasks is reused and gated by
-- show_in_wheel.

-- DEFAULT 1 is deliberate: every task that exists today was created for the
-- wheel, so all 60 must stay visible. The Tasks UI sends show_in_wheel = 0
-- explicitly, so new non-wheel tasks are opt-in on the wheel, never opt-out.
ALTER TABLE `yw_tasks`
  ADD COLUMN `show_in_wheel` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_recurring`,
  ADD INDEX `idx_yw_tasks_wheel` (`show_in_wheel`, `status`, `start_date`);

-- start_date was NOT NULL because the wheel is a calendar. A general task may
-- have no deadline, and the Tasks list is driven by due date / status rather
-- than by a calendar position, so a missing start date must be legal.
ALTER TABLE `yw_tasks` MODIFY COLUMN `start_date` DATE DEFAULT NULL;

-- The Tasks list is queried per assignee and the wheel per (customer, year);
-- neither existing index serves those access paths.
ALTER TABLE `yw_tasks` ADD INDEX `idx_yw_tasks_assignee` (`assigned_to`, `status`);

-- "Tasks" category. 015 seeded six wheel categories, none of which describes a
-- general work item. Idempotent on (customer_id, name) so re-running the
-- migration cannot produce duplicates; customer_id stays NULL because
-- categories are global and shared across all customers. sort_order 100 puts it
-- after the six seeded planning categories.
INSERT INTO `yw_categories` (`name`, `color`, `icon`, `sort_order`, `customer_id`)
SELECT 'Tasks', '#64748B', '✅', 100, NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `yw_categories` WHERE `name` = 'Tasks' AND `customer_id` IS NULL
);
