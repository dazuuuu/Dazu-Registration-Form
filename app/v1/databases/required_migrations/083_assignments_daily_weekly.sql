-- Assignments update: daily and weekly work, held grades, link check, course average. ADD ONLY.
--  Take a database backup before running this. No column or table is dropped, renamed or retyped, and no mark is changed.
--  Every new column is nullable or has a default that keeps old rows behaving exactly as before:
--   kind 'daily' (old assignments become Daily by the default, no UPDATE), weight 1, allow_resubmission NULL (old Return behaviour kept), remind_enabled 0,
--   release_grades_at NULL (= "as I mark", today's behaviour), publish_at NULL (= shown when published, today's behaviour).
--  kind is an enum-like string. Allowed now: daily, weekly. Reserved: monthly, module_assessment, capstone.
--  One ADD COLUMN per statement so a re-run skips columns that already exist (MigrationService ignores error 1060).

-- ---------- tutor_assignments ----------
ALTER TABLE tutor_assignments ADD COLUMN kind VARCHAR(30) NOT NULL DEFAULT 'daily';
ALTER TABLE tutor_assignments ADD COLUMN weight DECIMAL(6,2) NOT NULL DEFAULT 1.00;
ALTER TABLE tutor_assignments ADD COLUMN publish_at DATETIME NULL;
ALTER TABLE tutor_assignments ADD COLUMN release_grades_at DATETIME NULL;
ALTER TABLE tutor_assignments ADD COLUMN grades_released_notified_at DATETIME NULL;
-- NULL = older assignment: keeps today's Return button. The new form saves 1 (default) or 0 (Return hidden).
ALTER TABLE tutor_assignments ADD COLUMN allow_resubmission TINYINT(1) NULL DEFAULT NULL;
ALTER TABLE tutor_assignments ADD COLUMN require_own_work TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN ask_ai_note TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN check_links TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE tutor_assignments ADD COLUMN block_bad_links TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN remind_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN remind_whatsapp TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN note_id INT NULL;
ALTER TABLE tutor_assignments ADD COLUMN class_session_id INT NULL;
ALTER TABLE tutor_assignments ADD COLUMN is_template TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN copied_from_id INT UNSIGNED NULL;
ALTER TABLE tutor_assignments ADD COLUMN group_size TINYINT UNSIGNED NULL;
ALTER TABLE tutor_assignments ADD COLUMN submit_code TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN submit_audio TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN submit_quiz TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN no_submission TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD KEY idx_ta_kind_order (tutor_id, course_id, kind, sort_order);
ALTER TABLE tutor_assignments ADD KEY idx_ta_publish_at (publish_at);
ALTER TABLE tutor_assignments ADD KEY idx_ta_release_at (release_grades_at);
ALTER TABLE tutor_assignments ADD KEY idx_ta_due (due_at);

-- ---------- tutor_assignment_submissions ----------
-- The existing status ENUM already has 'returned'; a resubmission is status 'submitted' with resubmitted_at set,
-- so the old ENUM is never changed. is_late (existing) stays the Late label; it is never cleared by a return.
ALTER TABLE tutor_assignment_submissions ADD COLUMN first_submitted_at DATETIME NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN resubmitted_at DATETIME NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN return_items TEXT NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN resubmit_by DATETIME NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN own_work_ack TINYINT(1) NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN ai_note TEXT NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN code_text MEDIUMTEXT NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN audio_path VARCHAR(255) NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN link_status VARCHAR(20) NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN link_checked_at DATETIME NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN link_reason VARCHAR(255) NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN receipt_sent_at DATETIME NULL;
ALTER TABLE tutor_assignment_submissions ADD KEY idx_tas_status (assignment_id, status);

-- Optional GitHub name a tutor saves in their profile, used in the "add your tutor as a collaborator" fix text.
ALTER TABLE tutors ADD COLUMN github_username VARCHAR(39) NULL;

-- ---------- New tables ----------
CREATE TABLE IF NOT EXISTS tutor_assignment_quiz_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    qtype ENUM('mcq','truefalse') NOT NULL DEFAULT 'mcq',
    prompt TEXT NOT NULL,
    options_json TEXT NULL,
    correct_option TINYINT NOT NULL DEFAULT 0,
    points DECIMAL(6,2) NOT NULL DEFAULT 1,
    KEY idx_taqq_assignment (assignment_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_assignment_quiz_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    selected_option TINYINT NULL,
    UNIQUE KEY uq_taqa (submission_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Link check history. Never stores page contents: only the result, status code, final host and time.
CREATE TABLE IF NOT EXISTS tutor_assignment_link_checks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NULL,
    draft_key VARCHAR(64) NULL,
    checked_by INT NULL,
    url_hash CHAR(64) NOT NULL,
    host VARCHAR(190) NULL,
    site VARCHAR(30) NULL,
    status VARCHAR(20) NOT NULL,
    http_code SMALLINT NULL,
    reason VARCHAR(255) NULL,
    checked_at DATETIME NOT NULL,
    KEY idx_talc_hash (url_hash, checked_at),
    KEY idx_talc_submission (submission_id, checked_at),
    KEY idx_talc_by (checked_by, checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_assignment_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    KEY idx_tag_assignment (assignment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_assignment_group_members (
    group_id INT UNSIGNED NOT NULL,
    student_user_id INT NOT NULL,
    PRIMARY KEY (group_id, student_user_id),
    KEY idx_tagm_student (student_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The tutor's own reusable comment bank.
CREATE TABLE IF NOT EXISTS tutor_feedback_snippets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    text VARCHAR(500) NOT NULL,
    sort SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    KEY idx_tfs_tutor (tutor_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Templates shared by Dazu (admin fills later; starts empty). A tutor's own templates use tutor_assignments.is_template.
CREATE TABLE IF NOT EXISTS assignment_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    course_id INT NULL,
    data_json MEDIUMTEXT NOT NULL,
    created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per reminder sent, so a re-run never sends twice. kind: 24h, 1h, whatsapp_24h.
CREATE TABLE IF NOT EXISTS tutor_assignment_reminders_log (
    assignment_id INT UNSIGNED NOT NULL,
    student_user_id INT NOT NULL,
    kind VARCHAR(20) NOT NULL,
    sent_at DATETIME NOT NULL,
    PRIMARY KEY (assignment_id, student_user_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_assignment_nudges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    student_user_id INT NOT NULL,
    tutor_id INT NOT NULL,
    sent_at DATETIME NOT NULL,
    KEY idx_tan (assignment_id, student_user_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WhatsApp / SMS queue: filled only when the switch is on; sent only when a gateway is configured.
CREATE TABLE IF NOT EXISTS tutor_assignment_message_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel VARCHAR(20) NOT NULL,
    student_user_id INT NOT NULL,
    body VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    KEY idx_tamq_pending (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
