-- Payment Tables for Razorpay Integration
-- FreshChicken App Database

-- Create payment_orders table
CREATE TABLE IF NOT EXISTS `payment_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `razorpay_order_id` varchar(255) NOT NULL,
  `razorpay_payment_id` varchar(255) DEFAULT NULL,
  `razorpay_signature` varchar(512) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'INR',
  `status` enum('created','paid','failed','cancelled') DEFAULT 'created',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `razorpay_order_id` (`razorpay_order_id`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create saved_payment_methods table
CREATE TABLE IF NOT EXISTS `saved_payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `method_type` enum('card','upi','netbanking','wallet') DEFAULT 'card',
  `card_network` varchar(50) DEFAULT NULL COMMENT 'Visa, Mastercard, Rupay, etc.',
  `last_4_digits` varchar(4) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create payment_failures table for logging
CREATE TABLE IF NOT EXISTS `payment_failures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `error_code` varchar(100) DEFAULT NULL,
  `error_description` text,
  `error_source` varchar(100) DEFAULT NULL,
  `error_step` varchar(100) DEFAULT NULL,
  `error_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `error_code` (`error_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update orders table to add payment fields
ALTER TABLE `orders`
ADD COLUMN `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending' AFTER `status`,
ADD COLUMN `payment_id` varchar(255) DEFAULT NULL AFTER `payment_status`,
ADD COLUMN `payment_method` varchar(50) DEFAULT NULL AFTER `payment_id`,
ADD COLUMN `payment_date` timestamp NULL DEFAULT NULL AFTER `payment_method`;

-- Add indexes for better performance
ALTER TABLE `orders`
ADD INDEX `idx_payment_status` (`payment_status`),
ADD INDEX `idx_payment_id` (`payment_id`);

-- Insert sample payment methods (optional - for testing)
-- INSERT INTO `saved_payment_methods` (`user_id`, `method_type`, `card_network`, `last_4_digits`, `is_default`)
-- VALUES
-- (1, 'card', 'Visa', '4242', 1),
-- (1, 'upi', NULL, NULL, 0);

-- Show created tables
SHOW TABLES LIKE '%payment%';

-- Describe payment_orders table
DESC payment_orders;

-- Success message
SELECT 'Payment tables created successfully!' AS message;
