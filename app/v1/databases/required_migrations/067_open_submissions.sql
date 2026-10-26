-- Open submissions: a student hands in work without the tutor creating an assignment first.
-- The student gives the title and either a GitHub link, a Google Drive/Docs link or a document, plus an optional note.
-- The tutor (tutors.id) then grades it or returns it for changes. student_user_id / graded_by = users.id. *_at are server time.

CREATE TABLE IF NOT EXISTS open_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT NOT NULL,
    tutor_id INT NULL,
    course_id INT NULL,
    title VARCHAR(150) NOT NULL,
    kind ENUM('github','drive','file') NOT NULL,
    url VARCHAR(500) NULL,
    link_meta TEXT NULL,
    file_path VARCHAR(255) NULL,
    original_name VARCHAR(255) NULL,
    mime VARCHAR(120) NULL,
    bytes INT UNSIGNED NULL,
    note TEXT NULL,
    status ENUM('submitted','graded','returned') NOT NULL DEFAULT 'submitted',
    score DECIMAL(6,2) NULL,
    max_score DECIMAL(6,2) NOT NULL DEFAULT 100,
    feedback TEXT NULL,
    graded_by INT NULL,
    graded_at DATETIME NULL,
    submitted_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    KEY idx_os_student (student_user_id, submitted_at),
    KEY idx_os_tutor (tutor_id, status, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
