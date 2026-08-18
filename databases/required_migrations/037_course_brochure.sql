-- Course brochure PDF. Uploaded from admin course create/edit.
USE registration_system;

ALTER TABLE courses
    ADD COLUMN brochure_path VARCHAR(255) DEFAULT NULL AFTER cover_image;
