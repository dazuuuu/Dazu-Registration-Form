-- Continuing student enrollment: learning month, prior registration link, fee tracking
USE realestate_db;

ALTER TABLE registrations
    ADD COLUMN enrollment_type ENUM('new', 'continuing') NOT NULL DEFAULT 'new' AFTER possible_returning_student,
    ADD COLUMN prior_registration_id INT NULL DEFAULT NULL AFTER enrollment_type,
    ADD COLUMN current_learning_month TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER prior_registration_id,
    ADD COLUMN fee_balance_note VARCHAR(255) NULL DEFAULT NULL AFTER current_learning_month,
    ADD INDEX idx_enrollment_type (enrollment_type),
    ADD INDEX idx_prior_registration (prior_registration_id);
