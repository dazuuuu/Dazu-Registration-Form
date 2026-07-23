-- SmS module — student management, prospects, client projects, finance & sales
USE registration_system;

CREATE TABLE IF NOT EXISTS sms_students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    course_id INT DEFAULT NULL,
    duration VARCHAR(50) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    parent_phone VARCHAR(30) DEFAULT NULL,
    parent_email VARCHAR(150) DEFAULT NULL,
    cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','completed','withdrawn') NOT NULL DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    registration_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sms_students_status (status),
    INDEX idx_sms_students_course (course_id),
    INDEX idx_sms_students_email (email),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    receipt_no VARCHAR(30) NOT NULL,
    receipt_type ENUM('receipt','arrears') NOT NULL DEFAULT 'receipt',
    amount_delta DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    file_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sms_receipt_no (receipt_no),
    INDEX idx_sms_receipts_student (student_id),
    FOREIGN KEY (student_id) REFERENCES sms_students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_student_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    receipt_id INT UNSIGNED DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_payments_student (student_id),
    FOREIGN KEY (student_id) REFERENCES sms_students(id) ON DELETE CASCADE,
    FOREIGN KEY (receipt_id) REFERENCES sms_receipts(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_prospects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    interest_type ENUM('course','project') NOT NULL DEFAULT 'course',
    intake_or_course VARCHAR(200) DEFAULT NULL,
    follow_up_date DATE DEFAULT NULL,
    status ENUM('open','converted','lost') NOT NULL DEFAULT 'open',
    notes TEXT DEFAULT NULL,
    reminder_sent_2day TINYINT(1) NOT NULL DEFAULT 0,
    reminder_sent_due TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sms_prospects_status (status),
    INDEX idx_sms_prospects_follow_up (follow_up_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    client_name VARCHAR(150) DEFAULT NULL,
    client_email VARCHAR(150) DEFAULT NULL,
    client_phone VARCHAR(30) DEFAULT NULL,
    client_company VARCHAR(150) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    estimated_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','in_progress','completed','cancelled') NOT NULL DEFAULT 'active',
    follow_up_date DATE DEFAULT NULL,
    is_sale TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sms_projects_status (status),
    INDEX idx_sms_projects_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_project_meetings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    meeting_date DATETIME NOT NULL,
    location VARCHAR(200) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('scheduled','done','cancelled') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_meetings_project (project_id),
    INDEX idx_sms_meetings_date (meeting_date),
    FOREIGN KEY (project_id) REFERENCES sms_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_project_followups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    follow_up_date DATE NOT NULL,
    note TEXT DEFAULT NULL,
    followup_type ENUM('followup','email') NOT NULL DEFAULT 'followup',
    emailed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_pfollowups_project (project_id),
    INDEX idx_sms_pfollowups_date (follow_up_date),
    FOREIGN KEY (project_id) REFERENCES sms_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('students','projects') NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    sale_date DATE NOT NULL,
    student_id INT UNSIGNED DEFAULT NULL,
    project_id INT UNSIGNED DEFAULT NULL,
    payment_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_sales_category (category),
    INDEX idx_sms_sales_date (sale_date),
    FOREIGN KEY (student_id) REFERENCES sms_students(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES sms_projects(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_id) REFERENCES sms_student_payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_activity (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    description VARCHAR(500) NOT NULL,
    actor_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_activity_created (created_at),
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_receipt_counter (
    year SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    last_number INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
