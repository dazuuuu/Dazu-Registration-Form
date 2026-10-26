-- Tutor ID numbers in the format DTI/TRN/001/26: the tutor's position in registration order (first registered = 001)
-- and the year they were registered. Renumbers only the old automatic numbers (DTI-STF-0001 …); numbers the office
-- typed by hand are kept. Also: the email printed on the card (tutor and office can edit it) and the private ID photo.
ALTER TABLE tutors
    ADD COLUMN card_email VARCHAR(190) NULL,
    ADD COLUMN id_photo_path VARCHAR(255) NULL;

UPDATE tutors t
JOIN (SELECT a.id, COUNT(b.id) AS rn FROM tutors a JOIN tutors b ON b.id <= a.id GROUP BY a.id) r ON r.id = t.id
SET t.staff_no = CONCAT('DTI/TRN/', LPAD(r.rn, 3, '0'), '/', DATE_FORMAT(COALESCE(t.created_at, NOW()), '%y'))
WHERE t.staff_no LIKE 'DTI-STF-%';
