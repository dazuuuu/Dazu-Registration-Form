-- Profile photos and downloadable ID/certificate documents
USE realestate_db;

ALTER TABLE user_profiles
    ADD COLUMN profile_photo_path VARCHAR(255) NULL DEFAULT NULL AFTER address,
    ADD COLUMN profile_photo_submitted_at TIMESTAMP NULL DEFAULT NULL AFTER profile_photo_path,
    ADD COLUMN profile_photo_status ENUM('none', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'none' AFTER profile_photo_submitted_at;

ALTER TABLE registrations
    ADD COLUMN fee_status ENUM('current', 'partial', 'overdue', 'paid_in_full') NOT NULL DEFAULT 'current' AFTER fee_balance_note;

CREATE TABLE IF NOT EXISTS user_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    registration_id INT NULL DEFAULT NULL,
    doc_type ENUM('id_card', 'certificate') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    title VARCHAR(120) DEFAULT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_user_doc (user_id, doc_type),
    INDEX idx_registration_doc (registration_id, doc_type)
);
