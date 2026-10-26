-- Tutor course builder (tutor/modules/), student module progress, course completion, and personal documents.
-- Each tutor keeps their own materials per course; every student of that course assigned to the tutor sees them.
-- tutor_id in these tables is the tutor's users.id, the same as course_modules.tutor_id and certificates.tutor_id.
-- One statement per change, so anything that already exists only skips its own statement.

-- Course overview, intro video and instructions a tutor writes for a course.
CREATE TABLE IF NOT EXISTS tutor_course_overviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    course_id INT NOT NULL,
    overview TEXT NULL,
    instructions TEXT NULL,
    video_path VARCHAR(255) NULL,
    video_url VARCHAR(500) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tutor_course (tutor_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Course-wide resources (a file or a link) a tutor shares with their students of that course.
CREATE TABLE IF NOT EXISTS tutor_course_resources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    file_path VARCHAR(255) NULL,
    link_url VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tcr_tutor_course (tutor_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE course_modules ADD COLUMN instructions TEXT NULL AFTER description;
ALTER TABLE course_modules ADD COLUMN video_url VARCHAR(500) NULL AFTER instructions;

-- How a question is answered: text, choice (auto-marked), file (zip of a folder, PDF...) or github (repo link).
ALTER TABLE module_contents ADD COLUMN answer_mode VARCHAR(20) NOT NULL DEFAULT 'text' AFTER type;
ALTER TABLE module_contents ADD COLUMN options_json TEXT NULL AFTER content;
ALTER TABLE module_contents ADD COLUMN correct_option INT NULL AFTER options_json;
ALTER TABLE module_contents ADD COLUMN link_url VARCHAR(500) NULL AFTER file_path;
ALTER TABLE module_contents ADD COLUMN order_index INT NOT NULL DEFAULT 0;

-- Submissions against a tutor module item use submission_type 'module'.
ALTER TABLE student_submissions MODIFY submission_type ENUM('test','project','module') NOT NULL;
ALTER TABLE student_submissions ADD COLUMN github_url VARCHAR(500) NULL AFTER file_path;

CREATE TABLE IF NOT EXISTS student_module_progress (
    registration_id INT NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    completed_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (registration_id, module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Start date of the tutor's study timeline for a student (tutor/students/timeline.php).
ALTER TABLE tutor_students ADD COLUMN start_date DATE NULL;

-- Studies completed: set automatically when every module is done, or ticked by the tutor.
ALTER TABLE registrations ADD COLUMN studies_completed_at TIMESTAMP NULL;
ALTER TABLE registrations ADD COLUMN studies_completed_by INT NULL;

-- Academic documents (PDF) uploaded by students and tutors, for the admin.
CREATE TABLE IF NOT EXISTS personal_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doc_name VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    file_size INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_personal_docs_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE user_profiles ADD COLUMN tshirt_size VARCHAR(10) NULL;
