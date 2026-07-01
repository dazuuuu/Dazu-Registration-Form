-- databases/migrations/016_create_registrations_payments_tables.sql
USE realestate_db;

CREATE TABLE IF NOT EXISTS registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_no VARCHAR(30) DEFAULT NULL,        -- e.g. DH-PY01/2026. Assigned only on approval, not at submission.

    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,

    course_id INT NOT NULL,
    study_mode ENUM('physical','online') NOT NULL,
    id_document_path VARCHAR(255) NOT NULL,           -- PDF: National ID or result slip

    -- Snapshots taken at submission time. Never re-read from courses table later, so a course
    -- price change next term doesn't retroactively alter what an already-submitted student owes.
    registration_fee DECIMAL(10,2) NOT NULL,
    course_fee DECIMAL(10,2) NOT NULL,
    payment_plan ENUM('monthly','3months','full') NOT NULL,
    installment_count TINYINT UNSIGNED NOT NULL,
    installment_amount DECIMAL(10,2) NOT NULL,        -- the regular installment; last one absorbs rounding remainder

    payment_reference VARCHAR(150) NOT NULL,           -- name/number/M-Pesa code the student typed in, for the admin to manually verify
    possible_returning_student BOOLEAN NOT NULL DEFAULT FALSE, -- flagged if email/phone matches a prior approved registration
    registration_fee_waived BOOLEAN NOT NULL DEFAULT FALSE,    -- admin sets this explicitly on approval; never automatic

    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    user_id INT DEFAULT NULL,                          -- linked once approved and the student account is created

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
    payer_reference VARCHAR(150) NOT NULL,   -- name/number the student says they paid with; admin cross-checks manually
    recorded_by INT NOT NULL,                -- admin who logged it
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_registration (registration_id)
);