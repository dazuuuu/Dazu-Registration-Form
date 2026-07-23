-- Dazu Hub — core tables if full import stopped early (phpMyAdmin)
-- 1. Select database: dazutech_registration
-- 2. Import this file OR paste into SQL tab
-- Safe to re-run (IF NOT EXISTS / ON DUPLICATE KEY)

-- Roles + tutor/student
INSERT INTO roles (role_name) VALUES ('tutor'), ('student')
ON DUPLICATE KEY UPDATE role_name = role_name;

-- Courses (homepage + registration)
CREATE TABLE IF NOT EXISTS courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    abbreviation VARCHAR(10) NOT NULL UNIQUE,
    cover_image VARCHAR(255) DEFAULT NULL,
    description TEXT,
    overview TEXT,
    duration_months TINYINT UNSIGNED NOT NULL,
    weeks_of_study SMALLINT UNSIGNED DEFAULT NULL,
    hours_of_study SMALLINT UNSIGNED DEFAULT NULL,
    course_fee DECIMAL(10,2) NOT NULL,
    last_sequence INT UNSIGNED NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_active (is_active)
);

-- Services
CREATE TABLE IF NOT EXISTS services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    short_description TEXT,
    cover_image VARCHAR(255),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    view_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_slug (slug)
);

-- Settings / hero
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('site_name', 'Dazu Hub Institute'),
    ('logo_path', NULL),
    ('logo_alt',  'Dazu Hub Institute')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

CREATE TABLE IF NOT EXISTS hero_slides (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    image_path  VARCHAR(255) NOT NULL,
    caption     VARCHAR(255) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hero_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_headers (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    page_key    VARCHAR(60)  NOT NULL UNIQUE,
    title       VARCHAR(150) NULL,
    subtitle    VARCHAR(255) NULL,
    image_path  VARCHAR(255) NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registrations + payments
CREATE TABLE IF NOT EXISTS registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_no VARCHAR(30) DEFAULT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    course_id INT NOT NULL,
    study_mode ENUM('physical','online') NOT NULL,
    id_document_path VARCHAR(255) NOT NULL,
    registration_fee DECIMAL(10,2) NOT NULL,
    course_fee DECIMAL(10,2) NOT NULL,
    payment_plan ENUM('monthly','3months','full') NOT NULL,
    installment_count TINYINT UNSIGNED NOT NULL,
    installment_amount DECIMAL(10,2) NOT NULL,
    payment_reference VARCHAR(150) NOT NULL,
    possible_returning_student BOOLEAN NOT NULL DEFAULT FALSE,
    registration_fee_waived BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_phone (phone)
);

CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payer_reference VARCHAR(150) NOT NULL,
    recorded_by INT NOT NULL,
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_registration (registration_id)
);
