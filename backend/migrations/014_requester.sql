ALTER TABLE `tickets`
  ADD COLUMN `requester_id` INT DEFAULT NULL AFTER `reported_by`,
  ADD FOREIGN KEY (`requester_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
