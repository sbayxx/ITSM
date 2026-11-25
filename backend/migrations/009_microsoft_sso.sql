ALTER TABLE `users`
  ADD COLUMN `microsoft_sub` VARCHAR(255) DEFAULT NULL AFTER `verification_token`,
  ADD INDEX `idx_microsoft_sub` (`microsoft_sub`);
