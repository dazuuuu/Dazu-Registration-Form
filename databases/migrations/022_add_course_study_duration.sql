-- Adds weeks/hours of study to courses (duration_months already covers months of study).
ALTER TABLE courses
    ADD COLUMN weeks_of_study SMALLINT UNSIGNED DEFAULT NULL AFTER duration_months,
    ADD COLUMN hours_of_study SMALLINT UNSIGNED DEFAULT NULL AFTER weeks_of_study;
