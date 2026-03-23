-- Product Variants System Migration
-- This adds support for product variants with individual stock management

-- Create product_variants table
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `sku` VARCHAR(100) DEFAULT NULL,
  `stock_quantity` INT(11) NOT NULL DEFAULT 0,
  `price` DECIMAL(10,2) DEFAULT NULL COMMENT 'Override product price if needed',
  `sale_price` DECIMAL(10,2) DEFAULT NULL,
  `weight` DECIMAL(10,3) DEFAULT NULL COMMENT 'Override product weight if needed',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sku` (`sku`),
  KEY `product_id` (`product_id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `fk_variant_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create product_variant_attributes junction table
CREATE TABLE IF NOT EXISTS `product_variant_attributes` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `variant_id` INT(11) UNSIGNED NOT NULL,
  `attribute_value_id` INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_variant_attribute` (`variant_id`, `attribute_value_id`),
  KEY `variant_id` (`variant_id`),
  KEY `attribute_value_id` (`attribute_value_id`),
  CONSTRAINT `fk_variant_attr_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_variant_attr_value` FOREIGN KEY (`attribute_value_id`) REFERENCES `attribute_values` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add has_variants column to products table
ALTER TABLE `products` 
ADD COLUMN IF NOT EXISTS `has_variants` TINYINT(1) NOT NULL DEFAULT 0 AFTER `requires_shipping`;

-- Update existing products to mark them as simple products (no variants)
UPDATE `products` SET `has_variants` = 0 WHERE `has_variants` IS NULL;

-- Add index for better performance
ALTER TABLE `products` ADD INDEX `idx_has_variants` (`has_variants`);
