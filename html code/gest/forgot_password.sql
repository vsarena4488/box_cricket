-- Add OTP and reset attempt columns to users table
ALTER TABLE `users` 
ADD COLUMN `reset_otp` VARCHAR(6) DEFAULT NULL,
ADD COLUMN `reset_otp_expiry` DATETIME DEFAULT NULL,
ADD COLUMN `reset_attempts` INT DEFAULT 0,
ADD COLUMN `last_reset_attempt` DATETIME DEFAULT NULL;

-- Create password reset logs table
CREATE TABLE IF NOT EXISTS `password_reset_logs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `otp` VARCHAR(6) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT,
    `status` ENUM('sent', 'verified', 'expired', 'failed') DEFAULT 'sent',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;