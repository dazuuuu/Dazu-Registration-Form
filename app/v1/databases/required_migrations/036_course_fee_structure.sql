-- Per-course fee breakdown (tuition, materials, exam, etc.).
-- JSON array of { "label": "Tuition", "amount": 40000 }.
-- Empty/NULL means the course only has the existing course_fee total.
-- Parsed by app/v1/helpers/course_fee_structure.php.
USE registration_system;

ALTER TABLE courses
    ADD COLUMN fee_structure TEXT DEFAULT NULL AFTER course_fee;
