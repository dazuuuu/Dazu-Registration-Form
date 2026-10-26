-- Subjects for assignments. ADD ONLY: no column or table is dropped, renamed or retyped, and no mark is changed.
--  A subject belongs to one course and one tutor (the tutor who set it up). weight NULL = the default equal share of what is left after the capstone.
--  tutor_assignments.subject_id is NULL for every existing assignment, so nothing changes for them or for their marks.
CREATE TABLE IF NOT EXISTS course_subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    tutor_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(20) NULL,
    weight DECIMAL(6,2) NULL,
    monthly_expected TINYINT UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    KEY idx_cs_course (course_id, tutor_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit log: subject created, changed, reordered, removed, and every change of an assignment's subject.
CREATE TABLE IF NOT EXISTS course_subject_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NULL,
    course_id INT NULL,
    assignment_id INT NULL,
    actor_user_id INT NULL,
    event VARCHAR(40) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cse_subject (subject_id),
    KEY idx_cse_assignment (assignment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE tutor_assignments ADD COLUMN subject_id INT UNSIGNED NULL;
ALTER TABLE tutor_assignments ADD KEY idx_ta_subject (subject_id);
