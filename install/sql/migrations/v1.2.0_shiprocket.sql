-- ============================================================
-- Migration: v1.2.0 — Shiprocket Integration
-- Applies to: cloudkart (store tenant DB)
-- Run against the DB that holds your ck_* tables.
--
-- Replace `ck_` with your actual store table prefix if different.
-- Safe to run multiple times — each statement uses IF NOT EXISTS / IGNORE.
-- ============================================================

-- 1. Add Shiprocket columns to shipments table
--    (skip if already present)

SET @dbname = DATABASE();
SET @tbl    = CONCAT('ck_', 'shipments');   -- adjust prefix here if needed

-- shiprocket_order_id
SET @col = 'shiprocket_order_id';
SET @sql = IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col
    ),
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `shiprocket_order_id` VARCHAR(50) DEFAULT NULL AFTER `notes`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- shiprocket_shipment_id
SET @col = 'shiprocket_shipment_id';
SET @sql = IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col
    ),
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `shiprocket_shipment_id` VARCHAR(50) DEFAULT NULL AFTER `shiprocket_order_id`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- shiprocket_awb
SET @col = 'shiprocket_awb';
SET @sql = IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col
    ),
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `shiprocket_awb` VARCHAR(100) DEFAULT NULL AFTER `shiprocket_shipment_id`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- label_url
SET @col = 'label_url';
SET @sql = IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col
    ),
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `label_url` VARCHAR(500) DEFAULT NULL AFTER `shiprocket_awb`'),
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Add index on shiprocket_awb (for webhook lookups)
SET @idx = 'idx_shipment_sr_awb';
SET @sql = IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND INDEX_NAME = @idx
    ),
    CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `idx_shipment_sr_awb` (`shiprocket_awb`)'),
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Done.
SELECT 'v1.2.0 Shiprocket migration complete' AS migration_status;
