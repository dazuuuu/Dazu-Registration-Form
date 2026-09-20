-- Migration 042: Homepage course selection setting and category featured image/description
-- Supports selecting which courses appear on the front page and category-level branding for shareable school pages.

ALTER TABLE `courses`
  ADD COLUMN `show_on_homepage` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

ALTER TABLE `course_categories`
  ADD COLUMN `description` TEXT NULL AFTER `icon_path`,
  ADD COLUMN `featured_image` VARCHAR(255) NULL AFTER `description`;
