-- Add account lockout columns to users table
ALTER TABLE users
  ADD COLUMN failed_attempts INT DEFAULT 0 AFTER is_active,
  ADD COLUMN locked_until DATETIME NULL AFTER failed_attempts;
