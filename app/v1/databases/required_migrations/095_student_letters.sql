-- 095_student_letters.sql
-- Letter of recommendation for EVERY student (any course length). ADD ONLY: no mark, certificate, transcript or older letter is changed.
--  student_letters: ONE final letter per enrolment, made by the system when the course is finished and the fees are paid in full
--    (and the director signature and the official stamp exist). Everything printed is copied in when it is made (wording, name, dates,
--    subjects sentence, footer, signature, stamp, badges), so nobody can change it afterwards.
--  course_letter_topics: the topics a tutor types once on a course shorter than 12 months (one per line); used in the subjects sentence.
--  letter_settings: the footer (email, website, LinkedIn) the admin can change in Settings; new letters only.
--  letter_events: audit log of every change.
CREATE TABLE IF NOT EXISTS student_letters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    student_user_id INT NULL,
    course_id INT NOT NULL,
    student_no VARCHAR(40) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    course_title VARCHAR(200) NOT NULL,
    paragraphs_json MEDIUMTEXT NOT NULL,
    footer_email VARCHAR(160) NOT NULL,
    footer_website VARCHAR(160) NOT NULL,
    footer_linkedin VARCHAR(200) NOT NULL,
    logo_path VARCHAR(255) NULL,
    signature_path VARCHAR(255) NOT NULL,
    stamp_path VARCHAR(255) NOT NULL,
    badges_json TEXT NULL,
    generated_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sl_registration (registration_id),
    KEY idx_sl_student (student_user_id),
    KEY idx_sl_no (student_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_letter_topics (
    course_id INT NOT NULL PRIMARY KEY,
    topics TEXT NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS letter_settings (
    k VARCHAR(40) NOT NULL PRIMARY KEY,
    v VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS letter_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    letter_id INT NULL,
    registration_id INT NULL,
    course_id INT NULL,
    actor_user_id INT NULL,
    event VARCHAR(40) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_le_reg (registration_id),
    KEY idx_le_letter (letter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
