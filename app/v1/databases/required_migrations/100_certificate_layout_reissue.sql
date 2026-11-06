-- 100_certificate_layout_reissue.sql
-- The certificate layout with editable wording, NITA accreditation and reissue. ADD ONLY.
--  certificates.wording_text: this certificate's default wording (NULL = the wording in Settings). The tutor's extra lines stay in taught_skills.
--  certificates.issued_snapshot: everything an issued certificate prints, frozen when it is issued (and again on Reissue).
--  certificate_history: the old version each time a certificate is reissued, with who reissued it and when.
ALTER TABLE certificates ADD COLUMN wording_text VARCHAR(200) NULL;
ALTER TABLE certificates ADD COLUMN issued_snapshot MEDIUMTEXT NULL;
CREATE TABLE IF NOT EXISTS certificate_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificate_id INT NOT NULL,
    layout VARCHAR(10) NOT NULL,
    snapshot_json MEDIUMTEXT NULL,
    record_json MEDIUMTEXT NULL,
    reissued_by INT NULL,
    reissued_at DATETIME NOT NULL,
    KEY idx_ch_cert (certificate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS certificate_template_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NULL,
    certificate_id INT NULL,
    event VARCHAR(20) NOT NULL,
    old_json MEDIUMTEXT NULL,
    new_json MEDIUMTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
