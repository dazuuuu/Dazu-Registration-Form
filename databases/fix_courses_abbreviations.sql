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

-- Clear invalid reviewed_by / created_by FK values after DB re-import

UPDATE registrations r
LEFT JOIN users u ON u.id = r.reviewed_by
SET r.reviewed_by = NULL
WHERE r.reviewed_by IS NOT NULL AND u.id IS NULL;

UPDATE courses c
LEFT JOIN users u ON u.id = c.created_by
SET c.created_by = NULL
WHERE c.created_by IS NOT NULL AND u.id IS NULL;

UPDATE payments p
LEFT JOIN users u ON u.id = p.recorded_by
SET p.recorded_by = (SELECT MIN(u2.id) FROM users u2 INNER JOIN roles r ON r.id = u2.role_id WHERE r.role_name IN ('superadmin','admin') AND u2.is_active = 1)
WHERE p.recorded_by IS NOT NULL AND u.id IS NULL;
