-- 099_certificate_own_edits.sql
-- An issued certificate can be edited on its own in Admin Settings → Certificate (certificates.template_json; NULL = it follows the
-- template for every certificate). The log records which certificate was changed. ADD ONLY.
ALTER TABLE certificates ADD COLUMN template_json MEDIUMTEXT NULL;
ALTER TABLE certificate_template_events ADD COLUMN certificate_id INT NULL;
