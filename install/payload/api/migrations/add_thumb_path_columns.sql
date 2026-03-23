-- Migration to add thumb_path columns for performance optimization
ALTER TABLE products ADD COLUMN thumb_path VARCHAR(255) NULL AFTER image_path;
ALTER TABLE categories ADD COLUMN thumb_path VARCHAR(255) NULL AFTER image_path;
