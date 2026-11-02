-- Adds semester count and shared class schedules for dynamic course fee structures.
-- The detailed online/physical tuition totals and additional charges remain in
-- courses.fee_structure JSON so older fee data stays compatible.
USE registration_system;

ALTER TABLE courses
    ADD COLUMN semester_count INT NOT NULL DEFAULT 1 AFTER study_schedule,
    ADD COLUMN class_schedule_online VARCHAR(180) DEFAULT NULL AFTER semester_count,
    ADD COLUMN class_schedule_physical VARCHAR(180) DEFAULT NULL AFTER class_schedule_online;
