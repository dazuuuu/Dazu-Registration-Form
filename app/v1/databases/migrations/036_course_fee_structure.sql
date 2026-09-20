-- Per-course fee breakdown. See databases/required_migrations/036_course_fee_structure.sql.
USE registration_system;

ALTER TABLE courses
    ADD COLUMN fee_structure TEXT DEFAULT NULL AFTER course_fee;
