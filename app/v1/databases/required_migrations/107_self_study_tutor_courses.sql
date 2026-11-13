-- 107_self_study_tutor_courses.sql
-- Self-study (step 4): the tutor side, starting with creating a course and its Details. NEW COLUMNS on self_study_courses (a table created by migration 104)
-- and ONE NEW TABLE; no table of the live site is changed.
--  self_study_courses (new columns):
--     crop_x, crop_y, crop_w, crop_h  where the 16:9 crop frame sits on the original picture, in the original's pixels
--     cover_orig_w, cover_orig_h      the original picture's size (so the screen does not have to open the file)
--     archived_at                     set when the course is archived (hidden from students, every progress row kept)
--     published_at                    the first time the course was published (an archived course comes back as published if it ever was)
--     copied_from_id                  the course this one was duplicated from
--     created_by_user_id              the user who created it (owner_tutor_id, from migration 104, is the owner who can move to another tutor)
--  self_study_course_editors: tutors the owner shared the course with: 'view' can only open it, 'edit' can change it. The sharing step fills it; this step already checks it.
ALTER TABLE self_study_courses ADD COLUMN crop_x INT NULL;
ALTER TABLE self_study_courses ADD COLUMN crop_y INT NULL;
ALTER TABLE self_study_courses ADD COLUMN crop_w INT NULL;
ALTER TABLE self_study_courses ADD COLUMN crop_h INT NULL;
ALTER TABLE self_study_courses ADD COLUMN cover_orig_w INT NULL;
ALTER TABLE self_study_courses ADD COLUMN cover_orig_h INT NULL;
ALTER TABLE self_study_courses ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE self_study_courses ADD COLUMN published_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE self_study_courses ADD COLUMN copied_from_id INT UNSIGNED NULL;
ALTER TABLE self_study_courses ADD COLUMN created_by_user_id INT NULL;

CREATE TABLE IF NOT EXISTS self_study_course_editors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    tutor_id INT NOT NULL,
    access VARCHAR(4) NOT NULL DEFAULT 'view',
    given_by INT NULL,
    given_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_ssce_course_tutor (course_id, tutor_id),
    KEY idx_ssce_tutor (tutor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
