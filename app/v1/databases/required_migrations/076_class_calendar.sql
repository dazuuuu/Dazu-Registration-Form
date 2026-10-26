-- Class Calendar (tutor) + My Classes (student) + class attendance. All times are stored in UTC.

-- Time zone of every tutor and student (user_profiles is the profile table of both), confirmed on first login.
ALTER TABLE user_profiles
    ADD COLUMN timezone VARCHAR(64) NULL,
    ADD COLUMN timezone_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN class_email_reminders TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS class_series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL COMMENT 'tutor users.id',
    title VARCHAR(200) NOT NULL,
    course_id INT NULL,
    repeat_rule ENUM('daily','weekly','monthly') NOT NULL,
    repeat_days VARCHAR(20) NULL COMMENT 'weekly: ISO days 1-7, e.g. 1,3,5',
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    group_course_id INT NULL COMMENT 'whole course class: new students of this course get the future classes',
    skipped_dates TEXT NULL COMMENT 'dates skipped for holidays (Y-m-d, comma-separated)',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cser_tutor (tutor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    series_id INT NULL,
    tutor_id INT NOT NULL COMMENT 'tutor users.id',
    title VARCHAR(200) NOT NULL,
    course_id INT NULL,
    topic VARCHAR(200) NULL,
    subtopics TEXT NULL,
    notes TEXT NULL,
    start_utc DATETIME NOT NULL,
    end_utc DATETIME NOT NULL,
    tutor_timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Nairobi',
    mode ENUM('physical','online','hybrid') NOT NULL DEFAULT 'physical',
    campus VARCHAR(60) NULL,
    room VARCHAR(80) NULL,
    meeting_link VARCHAR(500) NULL,
    status ENUM('scheduled','cancelled','moved') NOT NULL DEFAULT 'scheduled',
    cancel_reason VARCHAR(255) NULL,
    moved_from_utc DATETIME NULL,
    moved_reason VARCHAR(255) NULL,
    makeup_for_class_id INT NULL,
    reminders_on TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cls_tutor (tutor_id, start_utc),
    KEY idx_cls_series (series_id),
    KEY idx_cls_start (start_utc),
    KEY idx_cls_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Who is in a class. mode_override is set when this student's mode differs from the class mode.
CREATE TABLE IF NOT EXISTS class_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL COMMENT 'student users.id',
    mode_override ENUM('physical','online','hybrid') NULL,
    added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cstu (class_id, student_id),
    KEY idx_cstu_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    file_path VARCHAR(255) NULL COMMENT 'private storage',
    link VARCHAR(500) NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cmat_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('present','late','absent','excused') NULL COMMENT 'NULL = only a self check-in so far',
    note VARCHAR(255) NULL,
    self_checkin_at DATETIME NULL,
    marked_by INT NULL,
    marked_at DATETIME NULL,
    UNIQUE KEY uq_catt (class_id, student_id),
    KEY idx_catt_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_attendance_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    old_status VARCHAR(20) NULL,
    new_status VARCHAR(20) NULL,
    note VARCHAR(255) NULL,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cah_class (class_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_reminders_sent (
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    type VARCHAR(10) NOT NULL COMMENT '24h | 1h',
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id, student_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS calendar_feed_tokens (
    student_id INT PRIMARY KEY,
    token CHAR(48) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cft_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Public holidays and term breaks (a single day: start_date = end_date). Recurring classes skip them.
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    KEY idx_hol_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value VARCHAR(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO class_settings (setting_key, setting_value) VALUES
    ('join_open_minutes', '10'), ('reminder_24h', '1'), ('reminder_1h', '1'), ('campuses', 'Nairobi,Thika'),
    ('rooms', 'Room 1,Room 2,Computer Lab,Board Room'), ('self_checkin', '0'), ('late_counts', '1');

-- Emails are queued so a big series never slows the page; failures are retried (cron/class_reminders.php).
CREATE TABLE IF NOT EXISTS class_email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(190) NOT NULL,
    to_name VARCHAR(150) NULL,
    subject VARCHAR(255) NOT NULL,
    html MEDIUMTEXT NOT NULL,
    attempts TINYINT NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ceq_pending (sent_at, attempts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS class_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL,
    class_id INT NULL,
    series_id INT NULL,
    action VARCHAR(60) NOT NULL,
    detail VARCHAR(500) NULL,
    ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_caud_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
