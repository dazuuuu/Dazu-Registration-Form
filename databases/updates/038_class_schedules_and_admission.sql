-- Class schedules, admission documents, simplified enrollment columns.

CREATE TABLE IF NOT EXISTS class_schedule_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    study_mode ENUM('online', 'physical') NOT NULL,
    day_label VARCHAR(80) NOT NULL,
    time_label VARCHAR(80) NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mode_active (study_mode, is_active, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admission_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    registration_no VARCHAR(30) NOT NULL,
    student_name VARCHAR(150) NOT NULL,
    course_title VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_registration (registration_id),
    INDEX idx_reg_no (registration_no),
    CONSTRAINT fk_admission_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE registrations
    MODIFY phone VARCHAR(20) NULL DEFAULT NULL,
    MODIFY id_document_path VARCHAR(255) NULL DEFAULT NULL,
    MODIFY payment_reference VARCHAR(150) NULL DEFAULT NULL;

UPDATE courses SET last_sequence = GREATEST(last_sequence, 49) WHERE last_sequence < 49;
