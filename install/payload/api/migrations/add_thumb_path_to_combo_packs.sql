-- Migration to add thumb_path column to combo_packs
ALTER TABLE combo_packs ADD COLUMN thumb_path VARCHAR(255) NULL AFTER image_path;
