-- Signatures on certificates and staff ID cards for tutors.
--  tutors.signature_path: private file under app/v1/storage (written as "storage:signatures/…"), printed on the certificates
--  of the students the tutor trained. tutors.id_*: the tutor's staff ID card, generated automatically once they have a photo.
--  directors: people who sign certificates as director/founder. The one with on_certificates = 1 signs new certificates.
-- One ADD COLUMN per statement so a re-run skips columns that already exist (MigrationService ignores error 1060).

ALTER TABLE tutors ADD COLUMN signature_path VARCHAR(255) NULL;
ALTER TABLE tutors ADD COLUMN staff_no VARCHAR(30) NULL;
ALTER TABLE tutors ADD COLUMN id_qr_hash VARCHAR(64) NULL;
ALTER TABLE tutors ADD COLUMN id_issued_at DATETIME NULL;
ALTER TABLE tutors ADD COLUMN id_emailed_at DATETIME NULL;
ALTER TABLE tutors ADD UNIQUE KEY uq_tutor_id_hash (id_qr_hash);

ALTER TABLE student_id_cards ADD COLUMN emailed_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS directors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    title VARCHAR(80) NOT NULL DEFAULT 'Director',
    signature_path VARCHAR(255) NULL,
    on_certificates TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The director already printed on certificates, so nothing changes until a signature is added.
INSERT INTO directors (full_name, title, on_certificates, created_at)
SELECT 'Simon Mwangi', 'Founder & Director', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM directors);
