-- Tutor ID and certificate preview: the name and role printed on the staff ID card and certificates (kept separate from
-- the login account), photo and signature checks, the card's print status, and a history of every change.
ALTER TABLE tutors
    ADD COLUMN display_name VARCHAR(150) NULL,
    ADD COLUMN display_role VARCHAR(120) NULL,
    ADD COLUMN photo_status ENUM('none','ok','needs_update','approved','requested') NOT NULL DEFAULT 'none',
    ADD COLUMN photo_check_notes TEXT NULL,
    ADD COLUMN signature_status ENUM('none','ok','needs_update','requested') NOT NULL DEFAULT 'none',
    ADD COLUMN signature_check_notes TEXT NULL,
    ADD COLUMN id_status ENUM('not_created','preview_ready','sent_to_print','printed') NOT NULL DEFAULT 'not_created',
    ADD COLUMN edited_by_tutor TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN request_note VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS tutor_id_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    field VARCHAR(40) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    changed_by INT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tih_tutor (tutor_id)
);
