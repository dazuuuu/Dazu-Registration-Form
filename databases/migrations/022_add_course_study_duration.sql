-- Active: 1784479981582@@127.0.0.1@3306@registration_system
-- Adds weeks/hours of study to courses (duration_months already covers months of study).
ALTER TABLE courses
    ADD COLUMN weeks_of_study SMALLINT UNSIGNED DEFAULT NULL AFTER duration_months,
    ADD COLUMN hours_of_study SMALLINT UNSIGNED DEFAULT NULL AFTER weeks_of_study;
