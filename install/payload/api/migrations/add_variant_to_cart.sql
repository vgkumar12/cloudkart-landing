-- Add variant_id to cart table
-- This allows cart items to reference specific product variants

ALTER TABLE `cart` 
ADD COLUMN `variant_id` INT(11) UNSIGNED NULL AFTER `product_id`,
ADD KEY `idx_variant_id` (`variant_id`);

-- Note: Foreign key constraint to product_variants table
-- will be added after product_variants table is created
-- Run this after running add_product_variants.sql:
-- ALTER TABLE `cart` 
-- ADD CONSTRAINT `fk_cart_variant` 
--     FOREIGN KEY (`variant_id`) 
--     REFERENCES `product_variants` (`id`) 
--     ON DELETE SET NULL;
