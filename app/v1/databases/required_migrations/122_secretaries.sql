-- 122_secretaries.sql
-- Secretary role: the role itself and the secretaries table. NEW ROLE ROW AND NEW TABLE ONLY; no existing table, column or row is changed.
-- Safe to run twice (the role is inserted only when missing, CREATE TABLE IF NOT EXISTS).
--  secretaries: one row per secretary, linked to her login (users.id) the way tutors are. staff_no is her ID number (DTI/SEC/001/26): made by the system, UNIQUE, never edited.
--               branch is nairobi, thika or online. status is active, on_leave or left (checked by the application). A secretary is never deleted, she is marked Left, so her number is never reused.
--               birth_day and birth_month are enough for the date of birth (birth_year is optional). added_by is the user who added her.
INSERT INTO roles (role_name) SELECT 'secretary' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_name = 'secretary');

CREATE TABLE IF NOT EXISTS secretaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    staff_no VARCHAR(24) NOT NULL,
    job_title VARCHAR(80) NOT NULL DEFAULT 'Secretary',
    phone VARCHAR(30) DEFAULT NULL,
    branch VARCHAR(10) NOT NULL DEFAULT 'nairobi',
    birth_day TINYINT DEFAULT NULL,
    birth_month TINYINT DEFAULT NULL,
    birth_year SMALLINT DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    emergency_name VARCHAR(120) DEFAULT NULL,
    emergency_phone VARCHAR(30) DEFAULT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'active',
    added_by INT DEFAULT NULL,
    credentials_sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_secretaries_user (user_id),
    UNIQUE KEY uq_secretaries_staff_no (staff_no),
    KEY idx_secretaries_status (status),
    CONSTRAINT fk_secretaries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
