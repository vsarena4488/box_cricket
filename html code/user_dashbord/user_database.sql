-- Create database
CREATE DATABASE IF NOT EXISTS box_cricket;
USE box_cricket;

-- Users table for login page and user management
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `fullname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(15) DEFAULT NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `status` ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample user (password: user123)
INSERT INTO `users` (`fullname`, `email`, `password`, `phone`, `role`, `status`) VALUES
('John Doe', 'user@boxcricket.com', 'user123', '9876543210', 'user', 'active');

-- Insert sample admin (password: admin123)
INSERT INTO `users` (`fullname`, `email`, `password`, `phone`, `role`, `status`) VALUES
('Admin User', 'admin@boxcricket.com', 'admin123', '9876543211', 'admin', 'active');

-- Insert sample contact messages
INSERT INTO `contact_messages` (`name`, `email`, `message`) VALUES
('Vishal Patel', 'vishal@gmail.com', 'I want to know about tournament booking process.'),
('Rahul Sharma', 'rahul@gmail.com', 'Is slot booking available for weekends?');

=====================================================================================================================================================================================


-- Matches table 
CREATE TABLE IF NOT EXISTS `matches` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `team1_name` VARCHAR(100) NOT NULL,
    `team2_name` VARCHAR(100) NOT NULL,
    `match_type` ENUM('premium', 'standard', 'practice') NOT NULL DEFAULT 'standard',
    `match_date` DATE NOT NULL,
    `match_time` TIME NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `venue` VARCHAR(200) DEFAULT 'Box Cricket Ground',
    `available_slots` INT(11) NOT NULL DEFAULT 10,
    `total_slots` INT(11) NOT NULL DEFAULT 10,
    `status` ENUM('upcoming', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert additional matches for testing
INSERT INTO `matches` (`team1_name`, `team2_name`, `match_type`, `match_date`, `match_time`, `price`, `available_slots`, `total_slots`, `venue`, `status`) VALUES
('Titan Kings', 'Lion Hearts', 'premium', DATE_ADD(CURDATE(), INTERVAL 10 DAY), '17:00:00', 450, 5, 10, 'Arena 1, Sector 62', 'upcoming'),
('Desert Storm', 'Mountain Eagles', 'standard', DATE_ADD(CURDATE(), INTERVAL 11 DAY), '19:30:00', 500, 1, 10, 'Arena 2, Sector 62', 'upcoming'),
('Ocean Waves', 'Fire Birds', 'practice', DATE_ADD(CURDATE(), INTERVAL 12 DAY), '18:00:00', 550, 8, 10, 'Arena 3, Sector 18', 'upcoming'),
('Crimson Tigers', 'Golden Lions', 'premium', DATE_ADD(CURDATE(), INTERVAL 14 DAY), '20:00:00', 699, 10, 10, 'Main Arena, Sector 62', 'upcoming'),
('Shadow Knights', 'Phoenix Riders', 'standard', DATE_ADD(CURDATE(), INTERVAL 15 DAY), '16:30:00', 499, 6, 10, 'Arena 1, Sector 62', 'upcoming'),
('Thunder Wolves', 'Storm Breakers', 'practice', DATE_ADD(CURDATE(), INTERVAL 16 DAY), '21:00:00', 399, 12, 15, 'Arena 2, Sector 62', 'upcoming');


===================================================================================================================================================================== 
-- Bookings table
CREATE TABLE IF NOT EXISTS `bookings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `match_id` INT(11) NOT NULL,
    `booking_date` DATE NOT NULL,
    `booking_time` TIME NOT NULL,
    `slots_booked` INT(11) NOT NULL DEFAULT 1,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `payment_status` ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    `booking_status` ENUM('confirmed', 'pending', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`match_id`) REFERENCES `matches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add seat_number column to bookings table
ALTER TABLE `bookings` 
ADD COLUMN `seat_number` VARCHAR(10) AFTER `slots_booked`,
ADD COLUMN `payment_method` ENUM('qr', 'cash', 'card') DEFAULT 'qr' AFTER `payment_status`;

-- Update existing bookings with seat numbers (if any)
UPDATE `bookings` SET `seat_number` = CONCAT('A', id) WHERE seat_number IS NULL;

-- Insert sample bookings for the user
INSERT INTO `bookings` (`user_id`, `match_id`, `booking_date`, `booking_time`, `slots_booked`, `total_amount`, `payment_status`, `booking_status`) VALUES
(1, 1, CURDATE(), CURTIME(), 2, 1298, 'completed', 'confirmed'),
(1, 2, CURDATE(), CURTIME(), 1, 549, 'completed', 'confirmed');



=======================================================================================================================================================



-- Create grounds table
CREATE TABLE IF NOT EXISTS `grounds` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(200) NOT NULL,
    `ground_type` ENUM('floodlight', 'covered', 'premium', 'practice', 'indoor', 'vip') NOT NULL DEFAULT 'floodlight',
    `description` TEXT,
    `price_per_hour` DECIMAL(10,2) NOT NULL,
    `rating` DECIMAL(2,1) DEFAULT 4.0,
    `total_reviews` INT(11) DEFAULT 0,
    `image_url` VARCHAR(500) NOT NULL,
    `amenities` TEXT,
    `capacity` INT(11) DEFAULT 10,
    `status` ENUM('active', 'inactive', 'maintenance') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample grounds
INSERT INTO `grounds` (`name`, `location`, `ground_type`, `description`, `price_per_hour`, `rating`, `total_reviews`, `image_url`, `amenities`, `capacity`) VALUES
('Arena 1 - Floodlight', 'Sector 62, Noida', 'floodlight', 'Professional floodlight cricket ground with international standard pitch', 500, 4.5, 128, 'https://images.unsplash.com/photo-1531415074968-036ba1b575da?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80', 'Floodlights, Changing Rooms, Parking, Water Cooler', 10),
('Arena 2 - Covered', 'Sector 62, Noida', 'covered', 'Fully covered ground perfect for rainy days', 600, 4.0, 95, 'https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80', 'Covered Area, AC Lounge, Snacks Counter, Parking', 12),
('Arena 3 - Premium', 'Sector 18, Noida', 'premium', 'Premium cricket ground with international facilities', 800, 5.0, 256, 'https://images.unsplash.com/photo-1589807789213-7b6fc5c0f35a?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80', 'Premium Pitch, LED Lights, VIP Lounge, Commentary Box', 15),
('Arena 4 - Practice', 'Greater Noida', 'practice', 'Practice ground for regular practice sessions', 400, 3.5, 67, 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80', 'Practice Nets, Bowling Machine, Basic Facilities', 8),
('Arena 5 - Indoor', 'Sector 62, Noida', 'indoor', 'Indoor cricket ground with air conditioning', 700, 4.5, 189, 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80', 'Air Conditioned, Indoor Lighting, Lounge Area, Cafe', 12),
('Arena 6 - VIP', 'Sector 18, Noida', 'vip', 'VIP cricket ground with premium facilities', 1000, 5.0, 342, 'https://images.unsplash.com/photo-1624886032001-26a1d3fdeadc?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80', 'VIP Lounge, Premium Catering, Personal Staff, Luxury Seating', 20);




-- ==================================
-- User favorites table
CREATE TABLE IF NOT EXISTS `user_favorites` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `ground_id` INT(11) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_favorite` (`user_id`, `ground_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ground_id`) REFERENCES `grounds`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ground images table
CREATE TABLE IF NOT EXISTS `ground_images` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `ground_id` INT(11) NOT NULL,
    `image_url` VARCHAR(500) NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`ground_id`) REFERENCES `grounds`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ground reviews table
CREATE TABLE IF NOT EXISTS `ground_reviews` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `ground_id` INT(11) NOT NULL,
    `rating` INT(1) NOT NULL,
    `review_text` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ground_id`) REFERENCES `grounds`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample ground images
INSERT INTO `ground_images` (`ground_id`, `image_url`, `is_primary`) VALUES
(1, 'https://images.unsplash.com/photo-1531415074968-036ba1b575da?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80', 1),
(1, 'https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80', 0),
(1, 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80', 0),
(1, 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80', 0);

-- Insert sample reviews
INSERT INTO `ground_reviews` (`user_id`, `ground_id`, `rating`, `review_text`) VALUES
(1, 1, 5, 'Excellent ground! Perfect lighting and well maintained pitch. Highly recommended!'),
(1, 1, 4, 'Good experience. Staff was helpful. Would visit again.');



=====================================================================================================================================================================================

-- 4. Ground Bookings Table (Main booking table for your form)
CREATE TABLE IF NOT EXISTS `ground_bookings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `ground_id` INT(11) NOT NULL,
    `booking_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `duration_hours` INT(11) NOT NULL DEFAULT 1,
    `price_per_hour` DECIMAL(10,2) NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('qr', 'cash', 'card') NOT NULL DEFAULT 'qr',
    `payment_status` ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    `booking_status` ENUM('confirmed', 'pending', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
    `special_request` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ground_booking_slot` (`ground_id`, `booking_date`, `start_time`),
    KEY `idx_ground_booking_user` (`user_id`),
    CONSTRAINT `fk_ground_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ground_bookings_ground` FOREIGN KEY (`ground_id`) REFERENCES `grounds`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample bookings
INSERT INTO `ground_bookings` (`user_id`, `ground_id`, `booking_date`, `start_time`, `duration_hours`, `price_per_hour`, `total_amount`, `payment_method`, `payment_status`, `booking_status`, `special_request`) VALUES
(1, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '18:30:00', 2, 500, 1000, 'qr', 'completed', 'confirmed', 'Need parking space'),
(1, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '17:00:00', 1, 600, 600, 'cash', 'pending', 'pending', ''),
(1, 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '20:00:00', 2, 800, 1600, 'qr', 'completed', 'confirmed', 'Celebration party after match');

--====================================================================================================================================================================

-- Create feedback table
CREATE TABLE IF NOT EXISTS `feedback` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT DEFAULT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(15) DEFAULT NULL,
    `rating` INT NOT NULL,
    `feedback_type` ENUM('suggestion', 'complaint', 'praise', 'issue', 'other') NOT NULL,
    `message` TEXT NOT NULL,
    `recommend` ENUM('yes', 'no') NOT NULL,
    `status` ENUM('pending', 'read', 'replied', 'resolved') NOT NULL DEFAULT 'pending',
    `admin_reply` TEXT DEFAULT NULL,
    `replied_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample feedback (optional)
INSERT INTO `feedback` (`name`, `email`, `phone`, `rating`, `feedback_type`, `message`, `recommend`, `status`) VALUES
('John Doe', 'john@example.com', '9876543210', 5, 'praise', 'Excellent ground and facilities! Highly recommended.', 'yes', 'read'),
('Jane Smith', 'jane@example.com', '9876543211', 4, 'suggestion', 'Would be great if you could add more floodlights.', 'yes', 'pending'),
('Mike Johnson', 'mike@example.com', NULL, 3, 'complaint', 'Booking process was a bit confusing.', 'no', 'pending');


--=======================================================================================================================================================================================

-- Create payments table
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `booking_id` INT NOT NULL,
    `booking_type` ENUM('match', 'ground') NOT NULL,
    `user_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('qr', 'cash', 'card') NOT NULL,
    `payment_status` ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    `transaction_id` VARCHAR(100) DEFAULT NULL,
    `payment_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_booking` (`booking_id`, `booking_type`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`payment_status`),
    CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample payments (optional)
INSERT INTO `payments` (`booking_id`, `booking_type`, `user_id`, `amount`, `payment_method`, `payment_status`, `transaction_id`) VALUES
(1, 'match', 1, 1298, 'qr', 'completed', 'TXN123456789'),
(2, 'match', 1, 549, 'cash', 'pending', NULL);