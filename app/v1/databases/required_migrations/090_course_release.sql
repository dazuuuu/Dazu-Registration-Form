-- 090_course_release.sql
-- When a student is Ready (course finished + fees 100% + capstone passed and released + course average 50+), the transcript and the
-- letter of recommendation are released together. One row per student and course. Only adds a table.
-- transcript_released_at / letter_released_at: set when released, cleared (hidden_at set) if the student stops being Ready before a certificate is issued.
CREATE TABLE IF NOT EXISTS course_release (
    student_user_id INT NOT NULL,
    course_id INT NOT NULL,
    transcript_released_at DATETIME NULL,
    letter_released_at DATETIME NULL,
    letter_hash CHAR(32) NULL,
    letter_note VARCHAR(300) NULL,
    letter_dated_at DATETIME NULL,
    released_notified_at DATETIME NULL,
    hidden_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_user_id, course_id),
    UNIQUE KEY uq_course_release_letter_hash (letter_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
