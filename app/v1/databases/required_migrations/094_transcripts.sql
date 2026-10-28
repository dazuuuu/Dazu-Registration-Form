-- 094_transcripts.sql
-- Academic transcript for courses of 12 months and longer. ADD ONLY: no mark, average, assignment or certificate is changed.
--  course_subjects.subject_no: 101, 102, ... per course, given once by the system when the subject is created (never typed, never changed).
--  transcripts: ONE released transcript per student and course. Everything printed is copied in at release (rows, average, names,
--    recommendation, logo, stamp, signature, watermark), so it is locked: later changes to marks or Settings never touch it.
--    transcript_no = DT-<year>-<00001>; the count starts again each January and a number is never reused (transcript_counters).
--  transcript_drafts: the only editable parts before release: the names and the recommendation wording (NULL = the automatic wording).
--  transcript_settings: stamp, transcript logo, watermark on/off and strength (Light, Medium, Strong).
--  transcript_events: audit log of every change.
ALTER TABLE course_subjects ADD COLUMN subject_no SMALLINT UNSIGNED NULL;
ALTER TABLE course_subjects ADD UNIQUE KEY uq_cs_course_no (course_id, subject_no);

CREATE TABLE IF NOT EXISTS transcripts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT NOT NULL,
    course_id INT NOT NULL,
    registration_id INT NULL,
    transcript_no VARCHAR(20) NOT NULL,
    release_year SMALLINT UNSIGNED NOT NULL,
    release_seq INT UNSIGNED NOT NULL,
    verify_token CHAR(40) NOT NULL,
    surname VARCHAR(80) NOT NULL,
    other_names VARCHAR(120) NOT NULL,
    student_no VARCHAR(40) NULL,
    course_title VARCHAR(200) NOT NULL,
    campus VARCHAR(60) NULL,
    admitted_on DATE NULL,
    rows_json MEDIUMTEXT NOT NULL,
    average DECIMAL(5,2) NULL,
    average_word VARCHAR(20) NULL,
    recommendation VARCHAR(300) NOT NULL,
    logo_path VARCHAR(255) NULL,
    stamp_path VARCHAR(255) NOT NULL,
    signature_path VARCHAR(255) NOT NULL,
    director_name VARCHAR(150) NULL,
    director_title VARCHAR(80) NULL,
    watermark VARCHAR(10) NOT NULL DEFAULT 'off',
    released_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tr_student_course (student_user_id, course_id),
    UNIQUE KEY uq_tr_no (transcript_no),
    UNIQUE KEY uq_tr_token (verify_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transcript_counters (
    release_year SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    last_seq INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transcript_drafts (
    student_user_id INT NOT NULL,
    course_id INT NOT NULL,
    surname VARCHAR(80) NULL,
    other_names VARCHAR(120) NULL,
    recommendation VARCHAR(300) NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_user_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transcript_settings (
    k VARCHAR(40) NOT NULL PRIMARY KEY,
    v VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transcript_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transcript_id INT NULL,
    student_user_id INT NULL,
    course_id INT NULL,
    actor_user_id INT NULL,
    event VARCHAR(40) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tre_student (student_user_id, course_id),
    KEY idx_tre_transcript (transcript_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
