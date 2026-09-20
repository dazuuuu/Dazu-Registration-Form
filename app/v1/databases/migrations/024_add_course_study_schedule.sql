-- Adds a free-text study schedule to courses (e.g. "Mon, Wed, Fri evenings"),
-- alongside the existing optional weeks_of_study / hours_of_study.
ALTER TABLE courses
    ADD COLUMN study_schedule VARCHAR(150) DEFAULT NULL AFTER hours_of_study;
