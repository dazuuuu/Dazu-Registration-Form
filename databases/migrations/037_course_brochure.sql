-- Course brochure PDF. See databases/required_migrations/037_course_brochure.sql.
USE registration_system;

ALTER TABLE courses
    ADD COLUMN brochure_path VARCHAR(255) DEFAULT NULL AFTER cover_image;
