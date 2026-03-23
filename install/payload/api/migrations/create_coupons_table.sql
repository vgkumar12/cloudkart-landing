-- Create coupons table
CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(10, 2) NOT NULL,
    `minimum_order_amount` DECIMAL(10, 2) DEFAULT 0,
    `maximum_discount_amount` DECIMAL(10, 2) NULL,
    `usage_limit` INT(11) NULL COMMENT 'Total number of times coupon can be used',
    `usage_limit_per_user` INT(11) DEFAULT 1 COMMENT 'Number of times a single user can use this coupon',
    `used_count` INT(11) DEFAULT 0,
    `valid_from` DATETIME NOT NULL,
    `valid_until` DATETIME NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `applicable_to` ENUM('all', 'products', 'combo_packs', 'specific') DEFAULT 'all',
    `applicable_ids` TEXT NULL COMMENT 'JSON array of product/combo pack IDs if applicable_to is specific',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_code` (`code`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_valid_dates` (`valid_from`, `valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create coupon_usages table to track usage per user
CREATE TABLE IF NOT EXISTS `coupon_usages` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `coupon_id` INT(11) UNSIGNED NOT NULL,
    `user_id` INT(11) UNSIGNED NULL,
    `order_id` INT(11) UNSIGNED NULL,
    `discount_amount` DECIMAL(10, 2) NOT NULL,
    `used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_coupon_id` (`coupon_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_order_id` (`order_id`),
    FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
