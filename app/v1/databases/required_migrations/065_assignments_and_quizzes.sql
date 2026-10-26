-- Tutor classwork: assignments and quizzes set by a tutor for all their students or a chosen few.
--  tutor_id = tutors.id; student_user_id = users.id of the student.
--  audience 'all' = every student assigned to the tutor (including ones added later); 'selected' = rows in *_targets.
--  Quiz questions: mcq (auto-marked), text and code (marked by the tutor).

CREATE TABLE IF NOT EXISTS tutor_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    instructions TEXT NULL,
    attachment_path VARCHAR(255) NULL,
    due_at DATETIME NULL,
    max_score SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    audience ENUM('all','selected') NOT NULL DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ta_tutor (tutor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_assignment_targets (
    assignment_id INT UNSIGNED NOT NULL,
    student_user_id INT NOT NULL,
    PRIMARY KEY (assignment_id, student_user_id),
    KEY idx_tat_student (student_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_assignment_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    student_user_id INT NOT NULL,
    answer_text MEDIUMTEXT NULL,
    file_path VARCHAR(255) NULL,
    link_url VARCHAR(500) NULL,
    submitted_at TIMESTAMP NULL,
    score DECIMAL(6,2) NULL,
    feedback TEXT NULL,
    graded_at TIMESTAMP NULL,
    UNIQUE KEY uq_tas (assignment_id, student_user_id),
    KEY idx_tas_student (student_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_quizzes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    due_at DATETIME NULL,
    audience ENUM('all','selected') NOT NULL DEFAULT 'all',
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tq_tutor (tutor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_quiz_targets (
    quiz_id INT UNSIGNED NOT NULL,
    student_user_id INT NOT NULL,
    PRIMARY KEY (quiz_id, student_user_id),
    KEY idx_tqt_student (student_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_quiz_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    qtype ENUM('mcq','text','code') NOT NULL DEFAULT 'mcq',
    prompt TEXT NOT NULL,
    options_json TEXT NULL,
    correct_option TINYINT NULL,
    points SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    order_index INT NOT NULL DEFAULT 0,
    KEY idx_tqq_quiz (quiz_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_quiz_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    student_user_id INT NOT NULL,
    submitted_at TIMESTAMP NULL,
    score DECIMAL(6,2) NULL,
    max_score DECIMAL(6,2) NULL,
    feedback TEXT NULL,
    graded_at TIMESTAMP NULL,
    UNIQUE KEY uq_tqa (quiz_id, student_user_id),
    KEY idx_tqa_student (student_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_quiz_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    selected_option TINYINT NULL,
    answer_text MEDIUMTEXT NULL,
    score DECIMAL(6,2) NULL,
    UNIQUE KEY uq_tqans (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
