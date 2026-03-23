-- ============================================================
-- Migration: v1.1.0 — Category Menu (show_in_menu + All Categories item)
-- Applies to: cloudkart (store tenant DB)
-- Run against the DB that holds your ck_* tables.
--
-- Replace `ck_` with your actual store table prefix if different.
-- Safe to run multiple times — each statement checks before applying.
--
-- Changes:
--   1. Add show_in_menu column to categories table
--   2. Insert "All Categories" (#category-menu) into the header menu
-- ============================================================

SET @dbname = DATABASE();
SET @prefix = 'ck_';   -- adjust prefix here if needed

-- ============================================================
-- 1. Add show_in_menu column to categories
-- ============================================================

SET @tbl = CONCAT(@prefix, 'categories');
SET @col = 'show_in_menu';
SET @sql = IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col
    ),
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `show_in_menu` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`'),
    'SELECT "show_in_menu already exists — skipped" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 2. Insert "All Categories" (#category-menu) into header menu
--    - Finds the header menu, shifts items at sort_order >= 2 down by 1,
--      then inserts "All Categories" at position 2.
--    - Skipped if the item already exists.
-- ============================================================

-- Get header menu id into a variable
SET @menu_tbl = CONCAT(@prefix, 'menus');
SET @item_tbl = CONCAT(@prefix, 'menu_items');

SET @header_menu_id = NULL;
SET @sql = CONCAT(
    'SELECT id INTO @header_menu_id FROM `', @menu_tbl,
    '` WHERE location = ''header'' LIMIT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check if #category-menu item already exists
SET @already_exists = 0;
SET @sql = IF(
    @header_menu_id IS NOT NULL,
    CONCAT(
        'SELECT COUNT(*) INTO @already_exists FROM `', @item_tbl,
        '` WHERE menu_id = ', @header_menu_id, ' AND url = ''#category-menu'''
    ),
    'SELECT 0 INTO @already_exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Shift existing items at sort_order >= 2 down by 1
SET @sql = IF(
    @header_menu_id IS NOT NULL AND @already_exists = 0,
    CONCAT(
        'UPDATE `', @item_tbl,
        '` SET sort_order = sort_order + 1',
        ' WHERE menu_id = ', @header_menu_id, ' AND sort_order >= 2'
    ),
    'SELECT "shift skipped" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Insert "All Categories" at position 2
SET @sql = IF(
    @header_menu_id IS NOT NULL AND @already_exists = 0,
    CONCAT(
        'INSERT INTO `', @item_tbl,
        '` (menu_id, title, url, sort_order, status)',
        ' VALUES (', @header_menu_id, ', ''All Categories'', ''#category-menu'', 2, ''active'')'
    ),
    'SELECT "All Categories item already exists — skipped" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Done.
SELECT 'v1.1.0 Category menu migration complete' AS migration_status;
