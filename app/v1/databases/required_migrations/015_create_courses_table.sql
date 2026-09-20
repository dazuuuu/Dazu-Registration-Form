-- Active: 1780050571987@@127.0.0.1@3306@registration_system
-- databases/migrations/015_create_courses_table.sql
USE realestate_db;

CREATE TABLE IF NOT EXISTS courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    abbreviation VARCHAR(10) NOT NULL UNIQUE,        -- e.g. "PY" for Python. Set manually by admin, never auto-derived.
    cover_image VARCHAR(255) DEFAULT NULL,
    description TEXT,
    overview TEXT,
    duration_months TINYINT UNSIGNED NOT NULL,
    course_fee DECIMAL(10,2) NOT NULL,               -- enforced >= 16000 in application code (MySQL CHECK support is inconsistent pre-8.0.16)
    last_sequence INT UNSIGNED NOT NULL DEFAULT 0,    -- incremented atomically per registration; never recalculated from COUNT()
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_active (is_active)
);

-- Add the two roles this system needs beyond the existing superadmin/admin/user.
INSERT INTO roles (role_name) VALUES ('tutor'), ('student')
ON DUPLICATE KEY UPDATE role_name = role_name;

-- account_status drives the admin's suspend/unlock feature. Nothing else reads or writes it
-- except Middleware::requireAuth(), so behaviour stays in exactly one place.
-- NOTE: run this once. "IF NOT EXISTS" on ADD COLUMN needs MySQL 8.0.29+/recent MariaDB and
-- many local stacks (Ampps included) ship older — so if you re-run this migration by accident,
-- MySQL will throw "Duplicate column name", not silently skip it. That's expected, not a bug.
ALTER TABLE users
    ADD COLUMN account_status ENUM('active','suspended') NOT NULL DEFAULT 'active' AFTER is_active;