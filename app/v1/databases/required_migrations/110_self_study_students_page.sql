-- 110_self_study_students_page.sql
-- Self-study (step 7): the "Students" page where a tutor sees how the students are doing and answers reset requests. NEW COLUMNS on tables created by migration 106; no table of the live site is changed.
--  self_study_resets.note              the tutor's own note on a reset (the reason is why it was needed; the note is anything the tutor wants to add)
--  self_study_reset_requests.handled_note   the optional note when the tutor presses "Not now"
-- The comment rules (Working well / Slowing down / Not working) use the numbers that already live in progress_settings (migration 103): the working days (7) and the
-- not-working days (14) shared with Student Progress, plus three new keys saved there by admin Settings > Self-study (defaults in the code, so no rows are needed):
--   ss_failed_in_row (2), ss_reset_quiz_month (2), ss_reset_exam_month (2).
ALTER TABLE self_study_resets ADD COLUMN note VARCHAR(300) NULL;
ALTER TABLE self_study_reset_requests ADD COLUMN handled_note VARCHAR(300) NULL;
