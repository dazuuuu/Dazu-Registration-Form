-- Assignments feature (build specification v1.0): extends the tutor_assignments tables from 065 instead of adding a second copy.
--  tutor_id = tutors.id; student_user_id / *_user_id = users.id.
--  Every *_at DATETIME written by this feature is UTC; pages show East Africa Time.
--  One ADD COLUMN per statement so a re-run skips columns that already exist (MigrationService ignores error 1060).

-- ---------- tutor_assignments: one row per assignment ----------
ALTER TABLE tutor_assignments ADD COLUMN course_id INT NULL AFTER tutor_id;
ALTER TABLE tutor_assignments ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER course_id;
ALTER TABLE tutor_assignments ADD COLUMN description VARCHAR(500) NULL AFTER title;
ALTER TABLE tutor_assignments ADD COLUMN details MEDIUMTEXT NULL AFTER description;
ALTER TABLE tutor_assignments ADD COLUMN sequential_lock TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE tutor_assignments ADD COLUMN close_after_due TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE tutor_assignments ADD COLUMN max_extension_days SMALLINT UNSIGNED NOT NULL DEFAULT 7;
ALTER TABLE tutor_assignments ADD COLUMN submit_link TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN submit_text TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN submit_images TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN submit_file TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignments ADD COLUMN link_sites VARCHAR(255) NULL;
ALTER TABLE tutor_assignments ADD COLUMN grading_mode ENUM('marks','pass','feedback') NOT NULL DEFAULT 'marks';
ALTER TABLE tutor_assignments ADD COLUMN pass_mark_percent TINYINT UNSIGNED NOT NULL DEFAULT 50;
ALTER TABLE tutor_assignments ADD COLUMN status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft';
ALTER TABLE tutor_assignments ADD COLUMN published_at DATETIME NULL;
ALTER TABLE tutor_assignments ADD COLUMN archived_at DATETIME NULL;
ALTER TABLE tutor_assignments ADD COLUMN updated_at DATETIME NULL;
ALTER TABLE tutor_assignments ADD KEY idx_ta_course_order (tutor_id, course_id, sort_order);

-- Assignments set before this feature: keep them visible and unlocked, accept every answer type, never close them.
-- Their due dates were typed in East Africa Time but saved as if UTC, so move them back 3 hours.
-- Safe to re-run (schema_guard may): assignments made with the new form always have a description, so they never match.
UPDATE tutor_assignments
   SET status = 'published', published_at = created_at, sort_order = id,
       submit_link = 1, submit_text = 1, submit_file = 1, sequential_lock = 0, close_after_due = 0,
       due_at = IF(due_at IS NULL, NULL, DATE_SUB(due_at, INTERVAL 3 HOUR))
 WHERE published_at IS NULL AND status = 'draft' AND description IS NULL AND details IS NULL;

-- ---------- Questions, attachments, rubric ----------
CREATE TABLE IF NOT EXISTS tutor_assignment_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    text TEXT NOT NULL,
    KEY idx_taq_assignment (assignment_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_assignment_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    kind ENUM('image','pdf','link') NOT NULL,
    file_path VARCHAR(255) NULL,
    url VARCHAR(500) NULL,
    original_name VARCHAR(255) NULL,
    label VARCHAR(150) NOT NULL,
    position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    KEY idx_taa_assignment (assignment_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_assignment_rubric (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    name VARCHAR(150) NOT NULL,
    max_marks DECIMAL(6,2) NOT NULL,
    KEY idx_tar_assignment (assignment_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reusable rubric sets a tutor saves once and loads into any assignment.
CREATE TABLE IF NOT EXISTS tutor_rubric_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    criteria_json TEXT NOT NULL,
    created_at DATETIME NULL,
    KEY idx_trt_tutor (tutor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Who sees it, and each student's unlock / due / close state ----------
CREATE TABLE IF NOT EXISTS tutor_assignment_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    student_user_id INT NOT NULL,
    added_at DATETIME NULL,
    notified_at DATETIME NULL,
    unlocked_at DATETIME NULL,
    current_due_at DATETIME NULL,
    closed_at DATETIME NULL,
    skipped_by INT NULL,
    skipped_at DATETIME NULL,
    removed_at DATETIME NULL,
    reminded_at DATETIME NULL,
    UNIQUE KEY uq_tarc (assignment_id, student_user_id),
    KEY idx_tarc_student (student_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing audiences become recipient rows ('all' = everyone assigned to the tutor today).
INSERT IGNORE INTO tutor_assignment_recipients (assignment_id, student_user_id, added_at, notified_at, unlocked_at)
SELECT t.assignment_id, t.student_user_id, a.created_at, a.created_at, a.created_at
  FROM tutor_assignment_targets t JOIN tutor_assignments a ON a.id = t.assignment_id;

INSERT IGNORE INTO tutor_assignment_recipients (assignment_id, student_user_id, added_at, notified_at, unlocked_at)
SELECT a.id, ts.user_id, a.created_at, a.created_at, a.created_at
  FROM tutor_assignments a JOIN tutor_students ts ON ts.tutor_id = a.tutor_id
 WHERE a.audience = 'all';

-- ---------- Extension and unlock requests ----------
CREATE TABLE IF NOT EXISTS tutor_assignment_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    student_user_id INT NOT NULL,
    type ENUM('extension','unlock') NOT NULL,
    reason_code VARCHAR(40) NULL,
    reason_text TEXT NULL,
    requested_due_at DATETIME NULL,
    status ENUM('pending','approved','declined','cancelled') NOT NULL DEFAULT 'pending',
    decided_by INT NULL,
    decided_at DATETIME NULL,
    decision_note TEXT NULL,
    new_due_at DATETIME NULL,
    reminded_at DATETIME NULL,
    created_at DATETIME NULL,
    KEY idx_tareq_assignment (assignment_id, student_user_id),
    KEY idx_tareq_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- tutor_assignment_submissions: one current row per student per assignment ----------
-- score = marks; graded_at = last time the tutor saved a grade (draft or published); published_at = when the student can see it.
ALTER TABLE tutor_assignment_submissions ADD COLUMN status ENUM('draft','submitted','returned','graded') NOT NULL DEFAULT 'draft';
ALTER TABLE tutor_assignment_submissions ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignment_submissions ADD COLUMN links_json TEXT NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN note TEXT NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN is_late TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutor_assignment_submissions ADD COLUMN draft_saved_at DATETIME NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN grade_label VARCHAR(30) NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN pass_result ENUM('pass','not_yet','reviewed') NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN feedback_why TEXT NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN feedback_well TEXT NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN feedback_improve TEXT NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN graded_by INT NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN published_at DATETIME NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN returned_at DATETIME NULL;
ALTER TABLE tutor_assignment_submissions ADD COLUMN grade_version INT UNSIGNED NOT NULL DEFAULT 0;

UPDATE tutor_assignment_submissions
   SET status = IF(graded_at IS NULL, 'submitted', 'graded'), version = 1,
       published_at = graded_at, feedback_why = feedback, grade_version = IF(graded_at IS NULL, 0, 1),
       links_json = IF(link_url IS NULL OR link_url = '', NULL, JSON_ARRAY(link_url))
 WHERE submitted_at IS NOT NULL AND version = 0;

CREATE TABLE IF NOT EXISTS tutor_assignment_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    answer_text MEDIUMTEXT NULL,
    UNIQUE KEY uq_tans (submission_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Images and files a student sends. Removing one only stamps removed_at so older versions can still show it.
CREATE TABLE IF NOT EXISTS tutor_assignment_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    kind ENUM('image','file') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime VARCHAR(120) NOT NULL,
    bytes INT UNSIGNED NOT NULL DEFAULT 0,
    added_at DATETIME NULL,
    removed_at DATETIME NULL,
    KEY idx_taf_submission (submission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_assignment_criterion_scores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    criterion_id INT UNSIGNED NOT NULL,
    score DECIMAL(6,2) NULL,
    comment VARCHAR(500) NULL,
    UNIQUE KEY uq_tacs (submission_id, criterion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every send (kind submission) and every published grade or return (kind grade), as a JSON snapshot.
CREATE TABLE IF NOT EXISTS tutor_assignment_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    kind ENUM('submission','grade') NOT NULL,
    snapshot MEDIUMTEXT NOT NULL,
    actor_user_id INT NULL,
    created_at DATETIME NULL,
    KEY idx_tav_submission (submission_id, kind, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tutor_assignment_versions (submission_id, version, kind, snapshot, created_at)
SELECT s.id, 1, 'submission',
       JSON_OBJECT('links', IF(s.link_url IS NULL OR s.link_url = '', JSON_ARRAY(), JSON_ARRAY(s.link_url)),
                   'answers', JSON_ARRAY(), 'files', JSON_ARRAY(), 'note', NULL,
                   'legacy_text', s.answer_text, 'legacy_file', s.file_path),
       s.submitted_at
  FROM tutor_assignment_submissions s
 WHERE s.submitted_at IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM tutor_assignment_versions v WHERE v.submission_id = s.id);

-- ---------- Audit and email log ----------
CREATE TABLE IF NOT EXISTS tutor_assignment_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NULL,
    actor_user_id INT NULL,
    student_user_id INT NULL,
    event VARCHAR(40) NOT NULL,
    details TEXT NULL,
    created_at DATETIME NULL,
    KEY idx_tae_assignment (assignment_id),
    KEY idx_tae_actor (actor_user_id, event, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
