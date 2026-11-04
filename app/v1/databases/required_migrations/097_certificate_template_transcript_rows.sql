-- 097_certificate_template_transcript_rows.sql
-- 1 certificate_template_events: log of every save / reset of the certificate text template (Admin Settings → Certificate).
--   The template itself is stored in site_settings 'cert_template' (JSON).
-- 2 transcript_drafts.rows_json: the tutor's or admin's edits to one student's transcript rows before release (NULL = the automatic rows).
--   ADD ONLY: no mark, assignment, average or certificate is changed.
CREATE TABLE IF NOT EXISTS certificate_template_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NULL,
    event VARCHAR(20) NOT NULL,
    old_json MEDIUMTEXT NULL,
    new_json MEDIUMTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE transcript_drafts ADD COLUMN rows_json MEDIUMTEXT NULL;
