-- Allows homepage hero slides to be either images or videos.
USE registration_system;

ALTER TABLE hero_slides
    ADD COLUMN media_type ENUM('image','video') NOT NULL DEFAULT 'image' AFTER image_path;
