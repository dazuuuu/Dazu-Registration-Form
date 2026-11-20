-- Subjects the admin sets for a course (tutor_id = 0), shared by every tutor of the course, with the month of the course they belong to.
-- ADD ONLY: tutors' own subjects and every mark stay as they are.
ALTER TABLE course_subjects ADD COLUMN month_no TINYINT UNSIGNED NULL;
