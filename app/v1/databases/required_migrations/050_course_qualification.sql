-- Migration 050: course qualification (certificate / diploma) and matching registration numbers.
-- NULL qualification = automatic: under 18 months is a certificate, otherwise a diploma.
ALTER TABLE courses
    ADD COLUMN qualification ENUM('certificate','diploma') DEFAULT NULL;

-- Sign-in usernames follow the registration number, so update them first (while the old number still matches).
UPDATE users u
JOIN registrations r ON r.user_id = u.id AND r.registration_no = u.username
JOIN courses c ON c.id = r.course_id
SET u.username = CONCAT('D',
    IF(COALESCE(c.qualification, IF(CASE WHEN c.duration_months > 0 THEN c.duration_months
                                         WHEN c.weeks_of_study > 0 THEN CEIL(c.weeks_of_study / 4)
                                         WHEN c.days_of_study > 0 THEN CEIL(c.days_of_study / 30)
                                         ELSE 1 END < 18, 'certificate', 'diploma')) = 'diploma', 'D', 'C'),
    SUBSTRING(u.username, 2))
WHERE r.registration_no REGEXP '^DH[0-9]+/[0-9]{2}$';

UPDATE registrations r
JOIN courses c ON c.id = r.course_id
SET r.registration_no = CONCAT('D',
    IF(COALESCE(c.qualification, IF(CASE WHEN c.duration_months > 0 THEN c.duration_months
                                         WHEN c.weeks_of_study > 0 THEN CEIL(c.weeks_of_study / 4)
                                         WHEN c.days_of_study > 0 THEN CEIL(c.days_of_study / 30)
                                         ELSE 1 END < 18, 'certificate', 'diploma')) = 'diploma', 'D', 'C'),
    SUBSTRING(r.registration_no, 2))
WHERE r.registration_no REGEXP '^DH[0-9]+/[0-9]{2}$';
