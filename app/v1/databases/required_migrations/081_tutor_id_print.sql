-- Tutor ID card (portrait design): QR verification token, admin photo crop, the versioned director signature,
-- editable card text (phone, websites, branches), print batches for the admin's bulk download, and an audit log.
-- Adds only; nothing existing is changed or removed.
ALTER TABLE tutors
    ADD COLUMN verify_token VARCHAR(64) NULL,
    ADD COLUMN verify_scans INT NOT NULL DEFAULT 0,
    ADD COLUMN last_scanned_at DATETIME NULL,
    ADD COLUMN verified_at DATETIME NULL,
    ADD COLUMN photo_crop VARCHAR(255) NULL;

CREATE UNIQUE INDEX uq_tutors_verify_token ON tutors (verify_token);
CREATE INDEX idx_tutors_id_status ON tutors (id_status);
CREATE INDEX idx_tutors_photo_status ON tutors (photo_status);

CREATE TABLE IF NOT EXISTS director_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_path VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    title VARCHAR(80) NOT NULL,
    version INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    check_notes TEXT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ds_active (is_active)
);

-- Certificates keep the director signature version they were issued with.
ALTER TABLE certificates ADD COLUMN director_signature_id INT NULL;

CREATE TABLE IF NOT EXISTS id_settings (
    k VARCHAR(60) PRIMARY KEY,
    v TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS id_print_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    count INT NOT NULL DEFAULT 0,
    options TEXT NULL,
    file_path VARCHAR(255) NULL,
    file_name VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS id_print_batch_items (
    batch_id INT NOT NULL,
    tutor_id INT NOT NULL,
    PRIMARY KEY (batch_id, tutor_id)
);

CREATE TABLE IF NOT EXISTS id_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL,
    tutor_id INT NULL,
    action VARCHAR(40) NOT NULL,
    detail VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_idaudit_tutor (tutor_id, created_at)
);
