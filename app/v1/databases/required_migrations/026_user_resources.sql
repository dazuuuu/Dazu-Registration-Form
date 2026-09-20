-- databases/required_migrations/026_user_resources.sql
-- User profile photos and downloadable student/tutor documents used by ResourceModel.

ALTER TABLE user_profiles
    ADD COLUMN profile_photo_path VARCHAR(255) NULL AFTER address;

ALTER TABLE user_profiles
    ADD COLUMN profile_photo_submitted_at TIMESTAMP NULL AFTER profile_photo_path;

ALTER TABLE user_profiles
    ADD COLUMN profile_photo_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER profile_photo_submitted_at;

CREATE TABLE IF NOT EXISTS user_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    registration_id INT NULL,
    doc_type ENUM('id_card','certificate') NOT NULL,
    title VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_documents_user (user_id),
    INDEX idx_user_documents_registration (registration_id),
    INDEX idx_user_documents_type (doc_type),
    UNIQUE KEY unique_user_document (user_id, registration_id, doc_type),
    CONSTRAINT fk_user_documents_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_documents_registration
        FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE SET NULL,
    CONSTRAINT fk_user_documents_uploaded_by
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
