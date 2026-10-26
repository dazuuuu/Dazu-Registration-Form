-- Intern Portal: the intern role, interns and departments, tasks, attendance, learning, reports, evaluations, messages,
-- documents, settings, audit log, and the tutor-assisted teaching tables (private to the tutor and the intern).

-- The intern role. Migration 073 called it 'attachee'; rename it so existing intern accounts keep working.
UPDATE `roles` SET `role_name` = 'intern' WHERE `role_name` = 'attachee'
  AND NOT EXISTS (SELECT 1 FROM (SELECT `role_name` FROM `roles`) r WHERE r.`role_name` = 'intern');
INSERT INTO `roles` (`role_name`) SELECT 'intern' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `role_name` = 'intern');

CREATE TABLE IF NOT EXISTS intern_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_intern_dept_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS interns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NULL,
    supervisor_id INT NULL COMMENT 'users.id of the supervising admin',
    institution VARCHAR(150) NULL,
    course VARCHAR(150) NULL,
    year_of_study VARCHAR(20) NULL,
    student_id_no VARCHAR(50) NULL,
    phone VARCHAR(30) NULL,
    emergency_name VARCHAR(120) NULL,
    emergency_phone VARCHAR(30) NULL,
    photo_path VARCHAR(255) NULL COMMENT 'private storage',
    start_date DATE NULL,
    end_date DATE NULL,
    status ENUM('active','completed','suspended','withdrawn') NOT NULL DEFAULT 'active',
    skills TEXT NULL COMMENT 'comma-separated skills acquired',
    final_eval_open TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_interns_user (user_id),
    KEY idx_interns_supervisor (supervisor_id),
    KEY idx_interns_status (status),
    KEY idx_interns_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Work hours, late rule, location, progress weights … as key/value pairs.
CREATE TABLE IF NOT EXISTS intern_settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO intern_settings (setting_key, setting_value) VALUES
    ('attendance_enabled', '1'), ('work_start', '08:00'), ('work_end', '17:00'), ('late_after_minutes', '10'),
    ('work_days', '1,2,3,4,5'), ('save_location', '0'), ('daily_hours', '9'),
    ('weight_attendance', '25'), ('weight_tasks', '35'), ('weight_training', '20'), ('weight_assessments', '20');

CREATE TABLE IF NOT EXISTS intern_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intern_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    priority ENUM('normal','medium','high') NOT NULL DEFAULT 'normal',
    deadline DATE NULL,
    status ENUM('todo','in_progress','submitted','revision','completed') NOT NULL DEFAULT 'todo',
    assigned_by INT NOT NULL,
    reminded_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_itask_intern (intern_id, status),
    KEY idx_itask_deadline (deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_task_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    submission_id INT NULL COMMENT 'NULL = attachment from the supervisor',
    path VARCHAR(255) NOT NULL COMMENT 'private storage',
    name VARCHAR(200) NOT NULL,
    mime VARCHAR(100) NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_itf_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_task_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    intern_id INT NOT NULL,
    notes TEXT NULL,
    link VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_its_task (task_id),
    KEY idx_its_intern (intern_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_task_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    kind ENUM('comment','revision','approved') NOT NULL DEFAULT 'comment',
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_itc_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intern_id INT NOT NULL,
    work_date DATE NOT NULL,
    clock_in DATETIME NULL,
    clock_out DATETIME NULL,
    status ENUM('on_time','late','early_out','absent') NOT NULL DEFAULT 'on_time',
    excused TINYINT(1) NOT NULL DEFAULT 0,
    in_ip VARCHAR(45) NULL,
    out_ip VARCHAR(45) NULL,
    latitude DECIMAL(9,6) NULL,
    longitude DECIMAL(9,6) NULL,
    note VARCHAR(255) NULL,
    corrected_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_iatt_day (intern_id, work_date),
    KEY idx_iatt_date (work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_learning_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_learning_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    type ENUM('video','pdf','quiz','assignment','link') NOT NULL,
    title VARCHAR(200) NOT NULL,
    url VARCHAR(500) NULL,
    file_path VARCHAR(255) NULL COMMENT 'private storage',
    quiz_json TEXT NULL COMMENT '[{"q":"…","options":["…"],"answer":0}]',
    instructions TEXT NULL,
    position INT NOT NULL DEFAULT 0,
    KEY idx_ili_module (module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_learning_assignments (
    module_id INT NOT NULL,
    intern_id INT NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (module_id, intern_id),
    KEY idx_ila_intern (intern_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_learning_progress (
    item_id INT NOT NULL,
    intern_id INT NOT NULL,
    score TINYINT NULL,
    answer TEXT NULL,
    done_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id, intern_id),
    KEY idx_ilp_intern (intern_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intern_id INT NOT NULL,
    type ENUM('daily','weekly','monthly') NOT NULL,
    period_start DATE NOT NULL,
    activities TEXT NOT NULL,
    skills TEXT NULL,
    challenges TEXT NULL,
    lessons TEXT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    supervisor_comment TEXT NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ireport_period (intern_id, type, period_start),
    KEY idx_ireport_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- self / skills: filled by the intern. midterm / final: written by the supervisor, shown once released.
CREATE TABLE IF NOT EXISTS intern_evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intern_id INT NOT NULL,
    kind ENUM('self','skills','midterm','final') NOT NULL,
    professionalism TINYINT NULL,
    technical TINYINT NULL,
    communication TINYINT NULL,
    teamwork TINYINT NULL,
    feedback TEXT NULL,
    status ENUM('draft','submitted','released') NOT NULL DEFAULT 'draft',
    evaluator_id INT NULL,
    released_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ieval (intern_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intern_id INT NOT NULL,
    sender_id INT NOT NULL,
    body TEXT NOT NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_imsg_intern (intern_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intern_id INT NOT NULL,
    category ENUM('agreement','id','cv','certificate','report','form','completion_letter','other') NOT NULL DEFAULT 'other',
    title VARCHAR(200) NOT NULL,
    path VARCHAR(255) NOT NULL COMMENT 'private storage',
    mime VARCHAR(100) NULL,
    size INT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_idoc_intern (intern_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL,
    intern_id INT NULL,
    action VARCHAR(60) NOT NULL,
    detail VARCHAR(500) NULL,
    ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_iaudit_intern (intern_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Announcements (migration 073) gain a priority, an optional deadline and a "Confirm attendance" button.
ALTER TABLE announcements
    ADD COLUMN priority ENUM('normal','important','urgent') NOT NULL DEFAULT 'normal' AFTER summary,
    ADD COLUMN deadline DATE NULL AFTER priority,
    ADD COLUMN needs_confirm TINYINT(1) NOT NULL DEFAULT 0 AFTER deadline;
ALTER TABLE announcement_recipients
    ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN confirmed_at TIMESTAMP NULL DEFAULT NULL;

-- ── Tutor-assisted teaching ───────────────────────────────────────────────────────────────────────────────
-- Only the tutor and the assigned intern ever read these tables (services/InternTeachingService.php).
-- Admin and student code must never query them.
CREATE TABLE IF NOT EXISTS intern_teaching_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL COMMENT 'tutor users.id',
    intern_id INT NOT NULL,
    group_label VARCHAR(150) NULL,
    student_reg_ids TEXT NOT NULL COMMENT 'comma-separated registration ids of the tutor''s own students',
    status ENUM('active','closed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_ita_tutor (tutor_id, status),
    KEY idx_ita_intern (intern_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_teaching_topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    subtopics TEXT NULL COMMENT 'one per line',
    starts_at DATETIME NULL,
    duration_min SMALLINT NULL,
    notes TEXT NULL,
    resources TEXT NULL COMMENT 'links, one per line',
    file_path VARCHAR(255) NULL COMMENT 'private storage',
    file_name VARCHAR(200) NULL,
    position INT NOT NULL DEFAULT 0,
    status ENUM('planned','taught','cancelled') NOT NULL DEFAULT 'planned',
    taught_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_itt_assignment (assignment_id, position),
    KEY idx_itt_status (status, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_teaching_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    intern_id INT NOT NULL,
    covered TEXT NOT NULL,
    went_well TEXT NULL,
    challenges TEXT NULL,
    questions TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_itr_topic (topic_id),
    KEY idx_itr_intern (intern_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS intern_teaching_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    tutor_id INT NOT NULL,
    preparation TINYINT NOT NULL,
    clarity TINYINT NOT NULL,
    confidence TINYINT NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_itrate_topic (topic_id),
    KEY idx_itrate_tutor (tutor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Private record of every teaching action (who assigned whom, who marked what). Never shown on any screen.
CREATE TABLE IF NOT EXISTS intern_teaching_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NOT NULL,
    assignment_id INT NULL,
    topic_id INT NULL,
    action VARCHAR(60) NOT NULL,
    detail VARCHAR(500) NULL,
    ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_itaud_assignment (assignment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
