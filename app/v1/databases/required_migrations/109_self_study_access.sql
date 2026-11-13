-- 109_self_study_access.sql
-- Self-study (step 6): the "Who sees it" and "Sharing" tabs. NEW COLUMNS on tables created by migrations 104 and 107, and ONE NEW TABLE; no table of the live site is changed.
-- The data itself already lives in self_study_access (students) and self_study_course_editors (tutors).
--  self_study_access:  added_by_tutor_id  the tutor who added the student (empty when admin did). A tutor can only remove the students they added themselves;
--                                         added_by (migration 104) keeps the user id of whoever added them.
--                      removed_by         who removed the student (the row stays, so progress is kept and re-adding opens exactly where they stopped)
--                      notified_at        when the student was told about the course (a draft tells nobody; publishing tells everyone not told yet)
--  self_study_activity_log:  every change to students or tutors on a course, with who did it.
--  Notification types used (portal_notifications.type is free text, so no change is needed there): selfstudy_course (to a student), selfstudy_share (to a tutor).
ALTER TABLE self_study_access ADD COLUMN added_by_tutor_id INT NULL;
ALTER TABLE self_study_access ADD COLUMN removed_by INT NULL;
ALTER TABLE self_study_access ADD COLUMN notified_at TIMESTAMP NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS self_study_activity_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    actor_user_id INT NOT NULL,
    actor_name VARCHAR(150) NOT NULL,
    actor_role VARCHAR(20) NOT NULL,
    action VARCHAR(40) NOT NULL,
    item_count INT NOT NULL DEFAULT 0,
    detail TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_ssal_course (course_id, created_at),
    KEY idx_ssal_actor (actor_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
