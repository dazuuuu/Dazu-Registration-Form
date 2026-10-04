-- Migration 046: Carry existing course-based tutor assignments over to direct tutor-student assignments.
-- Tutors that were assigned to courses (course_tutors) get every approved student of those courses
-- assigned to them in tutor_students. Tutors that already have direct student assignments are skipped,
-- so nothing an admin set after migration 045 is overwritten. The course_tutors table is left untouched.
INSERT IGNORE INTO `tutor_students` (`tutor_id`, `user_id`)
SELECT DISTINCT ct.`tutor_id`, r.`user_id`
FROM `course_tutors` ct
JOIN `registrations` r ON r.`course_id` = ct.`course_id` AND r.`status` = 'approved'
WHERE r.`user_id` IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `tutor_students` ts WHERE ts.`tutor_id` = ct.`tutor_id`);
