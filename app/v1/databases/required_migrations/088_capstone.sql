-- 088_capstone.sql
-- Capstone project for every course: set up (hidden / scheduled / open), deliverables, milestones, attempts, returns (max 2),
-- marks out of 100 in four parts, retakes (max 5, paid again), presentation slots, course finished, retake fees, audit.
-- Only adds tables. All times are stored in UTC (shown in EAT). Nothing existing is changed.

CREATE TABLE IF NOT EXISTS capstones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tutor_id INT NOT NULL,
    course_id INT NOT NULL,
    intake_id INT NULL,
    title VARCHAR(200) NOT NULL,
    brief TEXT NULL,
    instructions TEXT NULL,
    requirements TEXT NULL,
    example_link VARCHAR(500) NULL,
    example_file VARCHAR(255) NULL,
    example_file_name VARCHAR(200) NULL,
    mode VARCHAR(12) NOT NULL DEFAULT 'individual',
    team_size SMALLINT NOT NULL DEFAULT 3,
    status VARCHAR(12) NOT NULL DEFAULT 'draft',
    opens_at DATETIME NULL,
    opened_manually_at DATETIME NULL,
    closed_at DATETIME NULL,
    due_at DATETIME NULL,
    w_project SMALLINT NOT NULL DEFAULT 50,
    w_docs SMALLINT NOT NULL DEFAULT 20,
    w_present SMALLINT NOT NULL DEFAULT 20,
    w_time SMALLINT NOT NULL DEFAULT 10,
    weight DECIMAL(6,2) NOT NULL DEFAULT 5,
    notified_open_at DATETIME NULL,
    notified_soon_at DATETIME NULL,
    copied_from INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_capstones_tutor (tutor_id),
    INDEX idx_capstones_course (course_id),
    INDEX idx_capstones_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    capstone_id INT NOT NULL,
    student_user_id INT NOT NULL,
    team_no SMALLINT NULL,
    own_due_at DATETIME NULL,
    removed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_capstone_student (capstone_id, student_user_id),
    INDEX idx_capstone_students_student (student_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_deliverables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    capstone_id INT NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'custom',
    label VARCHAR(120) NOT NULL,
    item_type VARCHAR(8) NOT NULL DEFAULT 'link',
    is_required SMALLINT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_capstone_deliverables_capstone (capstone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_milestones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    capstone_id INT NOT NULL,
    title VARCHAR(160) NOT NULL,
    kind VARCHAR(14) NOT NULL DEFAULT 'work',
    due_at DATETIME NOT NULL,
    marks DECIMAL(5,1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    reminded_2d_at DATETIME NULL,
    reminded_1d_at DATETIME NULL,
    INDEX idx_capstone_milestones_capstone (capstone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per student per try. attempt_no 1 is the first try; 2 to 6 are retakes (at most 5).
-- shared_attempt_id: in a team, every member points to the owner attempt that holds the shared work.
CREATE TABLE IF NOT EXISTS capstone_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    capstone_id INT NOT NULL,
    student_user_id INT NOT NULL,
    attempt_no SMALLINT NOT NULL DEFAULT 1,
    shared_attempt_id INT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'not_started',
    returns_used SMALLINT NOT NULL DEFAULT 0,
    confirmed_final_rules_at DATETIME NULL,
    first_submitted_at DATETIME NULL,
    submitted_at DATETIME NULL,
    retake_fee DECIMAL(10,2) NULL,
    retake_paid_at DATETIME NULL,
    retake_started_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_capstone_attempt (capstone_id, student_user_id, attempt_no),
    INDEX idx_capstone_attempts_student (student_user_id),
    INDEX idx_capstone_attempts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_own_work (
    attempt_id INT NOT NULL,
    student_user_id INT NOT NULL,
    version INT NOT NULL,
    ticked_at DATETIME NOT NULL,
    ai_note VARCHAR(500) NULL,
    PRIMARY KEY (attempt_id, student_user_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    version INT NOT NULL DEFAULT 1,
    status VARCHAR(10) NOT NULL DEFAULT 'draft',
    submitted_by INT NULL,
    submitted_at DATETIME NULL,
    draft_saved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_capstone_submission_version (attempt_id, version),
    INDEX idx_capstone_submissions_attempt (attempt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_submission_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    deliverable_id INT NULL,
    label VARCHAR(120) NOT NULL,
    item_type VARCHAR(8) NOT NULL DEFAULT 'link',
    url VARCHAR(500) NULL,
    file_path VARCHAR(255) NULL,
    file_name VARCHAR(200) NULL,
    link_status VARCHAR(20) NULL,
    link_message VARCHAR(300) NULL,
    same_as_example SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_capstone_items_submission (submission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_milestone_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    milestone_id INT NOT NULL,
    attempt_id INT NOT NULL,
    note TEXT NULL,
    url VARCHAR(500) NULL,
    file_path VARCHAR(255) NULL,
    file_name VARCHAR(200) NULL,
    submitted_at DATETIME NOT NULL,
    approved_at DATETIME NULL,
    tutor_comment VARCHAR(500) NULL,
    UNIQUE KEY uq_capstone_milestone_sub (milestone_id, attempt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_marks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    submission_id INT NULL,
    p_project DECIMAL(5,1) NOT NULL DEFAULT 0,
    p_docs DECIMAL(5,1) NOT NULL DEFAULT 0,
    p_present DECIMAL(5,1) NOT NULL DEFAULT 0,
    p_time DECIMAL(5,1) NOT NULL DEFAULT 0,
    c_project TEXT NULL,
    c_docs TEXT NULL,
    c_present TEXT NULL,
    c_time TEXT NULL,
    feedback TEXT NULL,
    total DECIMAL(5,1) NOT NULL DEFAULT 0,
    result VARCHAR(12) NOT NULL DEFAULT 'Fail',
    is_final SMALLINT NOT NULL DEFAULT 0,
    held_until DATETIME NULL,
    released_notified_at DATETIME NULL,
    time_evidence TEXT NULL,
    marked_by INT NULL,
    marked_at DATETIME NOT NULL,
    UNIQUE KEY uq_capstone_mark_attempt (attempt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_returns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    submission_id INT NULL,
    return_no SMALLINT NOT NULL,
    instructions TEXT NOT NULL,
    send_by DATETIME NULL,
    returned_by INT NULL,
    returned_at DATETIME NOT NULL,
    UNIQUE KEY uq_capstone_return (attempt_id, return_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_example_ack (
    capstone_id INT NOT NULL,
    student_user_id INT NOT NULL,
    acked_at DATETIME NOT NULL,
    PRIMARY KEY (capstone_id, student_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Presentation slots. Each slot is also a real class on the existing Class Calendar (classes.id = class_id).
CREATE TABLE IF NOT EXISTS capstone_slots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    capstone_id INT NOT NULL,
    class_id INT NULL,
    starts_at DATETIME NOT NULL,
    minutes SMALLINT NOT NULL DEFAULT 20,
    mode VARCHAR(10) NOT NULL DEFAULT 'online',
    place VARCHAR(200) NULL,
    capacity SMALLINT NOT NULL DEFAULT 1,
    cancelled_at DATETIME NULL,
    INDEX idx_capstone_slots_capstone (capstone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_slot_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slot_id INT NOT NULL,
    attempt_id INT NOT NULL,
    booked_by INT NOT NULL,
    booked_at DATETIME NOT NULL,
    done_at DATETIME NULL,
    late_minutes SMALLINT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    cancelled_at DATETIME NULL,
    INDEX idx_capstone_bookings_slot (slot_id),
    INDEX idx_capstone_bookings_attempt (attempt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tutor marks a student's course as finished (can be earlier than planned). Undo is allowed until a certificate is issued.
CREATE TABLE IF NOT EXISTS course_finish (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_user_id INT NOT NULL,
    course_id INT NOT NULL,
    registration_id INT NULL,
    tutor_id INT NOT NULL,
    planned_months SMALLINT NULL,
    finished_after_months DECIMAL(5,1) NULL,
    finished_on DATE NOT NULL,
    note VARCHAR(500) NULL,
    undone_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_course_finish_student (student_user_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For the transcript built later: "Tutor allows admin to preview the transcript" (default off).
CREATE TABLE IF NOT EXISTS capstone_transcript_pref (
    student_user_id INT NOT NULL,
    course_id INT NOT NULL,
    allow_admin_preview SMALLINT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_user_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Retake fee per course (empty by default: a retake cannot start until the admin sets an amount).
CREATE TABLE IF NOT EXISTS capstone_retake_fees (
    course_id INT PRIMARY KEY,
    amount DECIMAL(10,2) NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Retake payments are kept apart from course fees, so they never reduce a student's fee balance.
CREATE TABLE IF NOT EXISTS capstone_retake_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference VARCHAR(80) NULL,
    recorded_by INT NULL,
    paid_at DATETIME NOT NULL,
    UNIQUE KEY uq_capstone_retake_payment (attempt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_reminders_sent (
    kind VARCHAR(24) NOT NULL,
    ref_id INT NOT NULL,
    student_user_id INT NOT NULL,
    sent_at DATETIME NOT NULL,
    PRIMARY KEY (kind, ref_id, student_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capstone_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    capstone_id INT NULL,
    attempt_id INT NULL,
    actor_user_id INT NULL,
    student_user_id INT NULL,
    event VARCHAR(40) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_capstone_events_capstone (capstone_id),
    INDEX idx_capstone_events_event (event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
