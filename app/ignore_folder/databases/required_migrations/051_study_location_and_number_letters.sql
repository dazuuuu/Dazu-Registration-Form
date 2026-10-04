-- Migration 051: where a student learns (Thika or Nairobi), and a re-run of the registration-number letters so
-- every number matches its course: DCH for certificate courses, DDH for diploma courses.
ALTER TABLE registrations
    ADD COLUMN study_location ENUM('thika','nairobi') DEFAULT NULL AFTER study_mode;

-- Sign-in usernames follow the registration number, so they are updated first (while the old number still matches).
UPDATE users u
JOIN registrations r ON r.user_id = u.id AND r.registration_no = u.username
JOIN courses c ON c.id = r.course_id
SET u.username = CONCAT('D', IF(COALESCE(c.qualification, IF(CASE WHEN c.duration_months > 0 THEN c.duration_months
                                         WHEN c.weeks_of_study > 0 THEN CEIL(c.weeks_of_study / 4)
                                         WHEN c.days_of_study > 0 THEN CEIL(c.days_of_study / 30)
                                         ELSE 1 END < 18, 'certificate', 'diploma')) = 'diploma', 'D', 'C'), 'H', SUBSTRING(u.username, LOCATE('H', u.username) + 1))
WHERE r.registration_no REGEXP '^D[CD]?H[0-9]+/[0-9]{2}$';

UPDATE registrations r
JOIN courses c ON c.id = r.course_id
SET r.registration_no = CONCAT('D', IF(COALESCE(c.qualification, IF(CASE WHEN c.duration_months > 0 THEN c.duration_months
                                         WHEN c.weeks_of_study > 0 THEN CEIL(c.weeks_of_study / 4)
                                         WHEN c.days_of_study > 0 THEN CEIL(c.days_of_study / 30)
                                         ELSE 1 END < 18, 'certificate', 'diploma')) = 'diploma', 'D', 'C'), 'H', SUBSTRING(r.registration_no, LOCATE('H', r.registration_no) + 1))
WHERE r.registration_no REGEXP '^D[CD]?H[0-9]+/[0-9]{2}$';
