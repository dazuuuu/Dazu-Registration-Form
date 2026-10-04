-- Columns the code uses that no earlier migration added (they existed only on the development database).
-- One ALTER per column, so a column that already exists only skips its own statement.

-- Second phone number on a registration (admin student list/view, enrol, admission form).
ALTER TABLE registrations ADD COLUMN secondary_phone VARCHAR(20) NULL AFTER phone;

-- Course timeline the tutor sets per student (student dashboard progress bar, tutor timeline page).
ALTER TABLE tutor_students ADD COLUMN timeline_days INT NULL DEFAULT 0;

-- Submissions made against a tutor module item (student module page, tutor submissions).
ALTER TABLE student_submissions ADD COLUMN module_content_id INT NULL;
