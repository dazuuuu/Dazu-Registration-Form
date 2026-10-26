CREATE TABLE certificates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    course_id INT NOT NULL,
    certificate_no VARCHAR(50) NOT NULL UNIQUE,
    qr_code_hash VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('requested', 'returned', 'being_prepared', 'awaiting_director_signature', 'issued', 'withdrawn') NOT NULL DEFAULT 'requested',
    
    snapshot_name VARCHAR(150) NOT NULL,
    snapshot_course VARCHAR(150) NOT NULL,
    snapshot_duration_months INT NOT NULL,
    snapshot_dates VARCHAR(100) NOT NULL,
    
    taught_skills TEXT NULL,
    
    tutor_id INT NULL,
    tutor_signature_path VARCHAR(255) NULL,
    
    director_id INT NULL,
    director_signature_path VARCHAR(255) NULL,
    
    issued_at TIMESTAMP NULL,
    withdrawn_reason TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE certificate_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificate_id INT UNSIGNED NOT NULL,
    taught_skills TEXT NULL,
    snapshot_name VARCHAR(150) NOT NULL,
    snapshot_course VARCHAR(150) NOT NULL,
    snapshot_duration_months INT NOT NULL,
    snapshot_dates VARCHAR(100) NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (certificate_id) REFERENCES certificates(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE certificate_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificate_id INT UNSIGNED NOT NULL,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (certificate_id) REFERENCES certificates(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_signatures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    signature_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
