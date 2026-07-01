-- Diagnose + fix course abbreviation issues (phpMyAdmin → dazutech_registration)

-- 1) See every course currently in the database
SELECT id, title, abbreviation, is_active, created_by, created_at
FROM courses
ORDER BY id;

-- 2) Find duplicate abbreviations (should return zero rows)
SELECT UPPER(abbreviation) AS abbr, COUNT(*) AS cnt, GROUP_CONCAT(id) AS ids
FROM courses
GROUP BY UPPER(abbreviation)
HAVING cnt > 1;

-- 3) Clear invalid created_by (fixes FK errors after DB reset / re-import)
UPDATE courses c
LEFT JOIN users u ON u.id = c.created_by
SET c.created_by = NULL
WHERE c.created_by IS NOT NULL AND u.id IS NULL;

-- 4) Activate all courses for public pages
UPDATE courses SET is_active = 1;

-- 5) OPTIONAL — only if you want a completely fresh start (deletes ALL courses!)
-- SET FOREIGN_KEY_CHECKS = 0;
-- DELETE FROM course_tutors;
-- DELETE FROM curriculum_units;
-- DELETE FROM courses;
-- SET FOREIGN_KEY_CHECKS = 1;
