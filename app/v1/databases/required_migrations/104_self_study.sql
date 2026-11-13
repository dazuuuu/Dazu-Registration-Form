-- 104_self_study.sql
-- Self-study for students (step 1: the list the student sees). NEW TABLES ONLY: no existing table, column or setting is changed.
-- Tutors will build the courses in a later step; for now the student page only reads these tables.
--  self_study_courses:  a short online course a tutor builds. status is draft, published or archived; level is beginner, intermediate or advanced.
--                       The cover is stored as the original upload plus WebP copies 400, 800 and 1600 pixels wide (a size is left empty when the original is narrower).
--                       module_count is kept here so the card can say "5 modules" without another query; the next step keeps it in step with the modules.
--  self_study_access:   which student may study which course. A student is "removed" by setting removed_at (the row stays, so adding them back keeps their progress).
--  self_study_progress: percent complete, last studied and completed dates, one row per course and student. The card percentage always comes from here.
CREATE TABLE IF NOT EXISTS self_study_courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_tutor_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    short_description VARCHAR(160) NOT NULL DEFAULT '',
    level VARCHAR(12) NOT NULL DEFAULT 'beginner',
    status VARCHAR(10) NOT NULL DEFAULT 'draft',
    module_count INT NOT NULL DEFAULT 0,
    cover_original VARCHAR(255) NULL,
    cover_400 VARCHAR(255) NULL,
    cover_800 VARCHAR(255) NULL,
    cover_1600 VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ssc_status (status),
    KEY idx_ssc_owner (owner_tutor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_access (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    student_id INT NOT NULL,
    added_by INT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    removed_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_ssa_course_student (course_id, student_id),
    KEY idx_ssa_student (student_id, removed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    student_id INT NOT NULL,
    percent_complete INT NOT NULL DEFAULT 0,
    last_studied_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_ssp_course_student (course_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
