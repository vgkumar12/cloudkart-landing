-- =====================================================
-- Brands and Product Attributes Migration
-- =====================================================
-- This migration adds brands and product attributes functionality
-- Includes Package Size attribute with weight values for shipping

-- =====================================================
-- 1. CREATE BRANDS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `brands` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `logo_path` VARCHAR(255) NULL,
    `website_url` VARCHAR(255) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `display_order` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_slug` (`slug`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. CREATE ATTRIBUTES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `attributes` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'e.g., Color, Size, Material, Package Size',
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `type` ENUM('select', 'multiselect', 'text', 'number') DEFAULT 'select',
    `is_required` TINYINT(1) DEFAULT 0,
    `is_filterable` TINYINT(1) DEFAULT 1 COMMENT 'Show in product filters',
    `is_visible` TINYINT(1) DEFAULT 1 COMMENT 'Show on product page',
    `display_order` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_slug` (`slug`),
    INDEX `idx_filterable` (`is_filterable`),
    INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. CREATE ATTRIBUTE VALUES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `attribute_values` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `attribute_id` INT(11) UNSIGNED NOT NULL,
    `value` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `color_code` VARCHAR(7) NULL COMMENT 'Hex color for color attributes',
    `weight_value` DECIMAL(10,3) NULL COMMENT 'Numeric weight in kg for Package Size attributes',
    `display_order` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_attribute_value` (`attribute_id`, `value`),
    INDEX `idx_attribute_id` (`attribute_id`),
    INDEX `idx_slug` (`slug`),
    FOREIGN KEY (`attribute_id`) REFERENCES `attributes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. CREATE PRODUCT-ATTRIBUTE VALUES JUNCTION TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `product_attribute_values` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) UNSIGNED NOT NULL,
    `attribute_value_id` INT(11) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_product_attribute_value` (`product_id`, `attribute_value_id`),
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_attribute_value_id` (`attribute_value_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`attribute_value_id`) REFERENCES `attribute_values`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. ALTER PRODUCTS TABLE - ADD BRAND_ID
-- =====================================================
ALTER TABLE `products` 
ADD COLUMN `brand_id` INT(11) UNSIGNED NULL AFTER `category_id`,
ADD INDEX `idx_brand_id` (`brand_id`),
ADD CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE SET NULL;

-- =====================================================
-- 6. INSERT SAMPLE DATA
-- =====================================================

-- Sample Brands
INSERT INTO `brands` (`name`, `slug`, `description`, `is_active`, `display_order`) VALUES
('Suncrackers Premium', 'suncrackers-premium', 'Premium quality crackers and fireworks', 1, 1),
('Suncrackers Standard', 'suncrackers-standard', 'Standard quality crackers for everyday celebrations', 1, 2),
('Suncrackers Kids', 'suncrackers-kids', 'Safe and fun crackers for children', 1, 3);

-- Sample Package Size Attribute with Weight Values
INSERT INTO `attributes` (`name`, `slug`, `type`, `is_required`, `is_filterable`, `is_visible`, `display_order`) 
VALUES ('Package Size', 'package-size', 'select', 1, 1, 1, 1);

SET @package_size_id = LAST_INSERT_ID();

INSERT INTO `attribute_values` (`attribute_id`, `value`, `slug`, `weight_value`, `display_order`) VALUES
(@package_size_id, '250g', '250g', 0.250, 1),
(@package_size_id, '500g', '500g', 0.500, 2),
(@package_size_id, '1kg', '1kg', 1.000, 3),
(@package_size_id, '2kg', '2kg', 2.000, 4),
(@package_size_id, '5kg', '5kg', 5.000, 5);

-- Sample Color Attribute
INSERT INTO `attributes` (`name`, `slug`, `type`, `is_required`, `is_filterable`, `is_visible`, `display_order`) 
VALUES ('Color', 'color', 'select', 0, 1, 1, 2);

SET @color_id = LAST_INSERT_ID();

INSERT INTO `attribute_values` (`attribute_id`, `value`, `slug`, `color_code`, `display_order`) VALUES
(@color_id, 'Red', 'red', '#FF0000', 1),
(@color_id, 'Green', 'green', '#00FF00', 2),
(@color_id, 'Blue', 'blue', '#0000FF', 3),
(@color_id, 'Yellow', 'yellow', '#FFFF00', 4),
(@color_id, 'Multi-Color', 'multi-color', NULL, 5);

-- Sample Type Attribute
INSERT INTO `attributes` (`name`, `slug`, `type`, `is_required`, `is_filterable`, `is_visible`, `display_order`) 
VALUES ('Type', 'type', 'select', 0, 1, 1, 3);

SET @type_id = LAST_INSERT_ID();

INSERT INTO `attribute_values` (`attribute_id`, `value`, `slug`, `display_order`) VALUES
(@type_id, 'Ground Spinner', 'ground-spinner', 1),
(@type_id, 'Aerial', 'aerial', 2),
(@type_id, 'Sparkler', 'sparkler', 3),
(@type_id, 'Fountain', 'fountain', 4),
(@type_id, 'Rocket', 'rocket', 5);

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================
-- Tables created: brands, attributes, attribute_values, product_attribute_values
-- Products table updated: added brand_id column
-- Sample data inserted: 3 brands, 3 attributes with values
