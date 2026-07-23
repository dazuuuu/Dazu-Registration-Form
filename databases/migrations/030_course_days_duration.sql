-- Standalone course duration: hours, days, weeks, or months (at least one required in app).
USE registration_system;

ALTER TABLE courses
    ADD COLUMN days_of_study SMALLINT UNSIGNED DEFAULT NULL AFTER weeks_of_study;

ALTER TABLE courses
    MODIFY COLUMN duration_months TINYINT UNSIGNED NOT NULL DEFAULT 0;
