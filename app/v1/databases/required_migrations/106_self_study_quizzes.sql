-- 106_self_study_quizzes.sql
-- Self-study (step 3): module quizzes, the Checkpoint Exam, the Final Exam and the result screen. NEW TABLES, plus five new columns on
-- self_study_courses (a table created in migration 104); no table of the live site is changed.
--  self_study_attempts:        one row per try. A try counts the moment it starts. question_ids holds the questions drawn and how their options were shuffled.
--                              ends_at is the time limit kept by the server (exams only). tries_cleared_at is set when a granted reset clears the try.
--  self_study_attempt_answers: the answer saved as the student goes, and whether it was right (filled in when the attempt is marked).
--  self_study_reset_requests:  "Ask my tutor for a reset" (waiting, granted or declined). The tutor screen that handles them comes in a later step.
--  self_study_resets:          every granted reset kept as a record: who, why and when.
--  self_study_courses (new columns): how many questions each kind draws and the exam time limits; the tutor sets them per course in a later step.
CREATE TABLE IF NOT EXISTS self_study_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    kind VARCHAR(16) NOT NULL,
    module_id INT UNSIGNED NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    ends_at TIMESTAMP NULL DEFAULT NULL,
    submitted_at TIMESTAMP NULL DEFAULT NULL,
    score_percent INT NULL,
    correct_count INT NULL,
    total_count INT NULL,
    passed SMALLINT NOT NULL DEFAULT 0,
    status VARCHAR(12) NOT NULL DEFAULT 'in_progress',
    question_ids TEXT NULL,
    tries_cleared_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_ssat_student (student_id, course_id, kind, started_at),
    KEY idx_ssat_open (status, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_attempt_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    answer TEXT NULL,
    is_correct SMALLINT NULL,
    saved_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_ssaa_attempt_question (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_reset_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    kind VARCHAR(16) NOT NULL,
    module_id INT UNSIGNED NULL,
    reason VARCHAR(200) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'waiting',
    handled_by INT NULL,
    handled_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_ssrr_student (student_id, course_id, kind),
    KEY idx_ssrr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    kind VARCHAR(16) NOT NULL,
    module_id INT UNSIGNED NULL,
    reset_by INT NOT NULL,
    reason VARCHAR(200) NOT NULL DEFAULT '',
    request_id INT UNSIGNED NULL,
    tries_cleared INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_ssrs_student (student_id, course_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE self_study_courses ADD COLUMN quiz_questions INT NOT NULL DEFAULT 12;
ALTER TABLE self_study_courses ADD COLUMN checkpoint_questions INT NOT NULL DEFAULT 30;
ALTER TABLE self_study_courses ADD COLUMN final_questions INT NOT NULL DEFAULT 40;
ALTER TABLE self_study_courses ADD COLUMN checkpoint_minutes INT NOT NULL DEFAULT 45;
ALTER TABLE self_study_courses ADD COLUMN final_minutes INT NOT NULL DEFAULT 60;
