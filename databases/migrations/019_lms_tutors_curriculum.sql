-- databases/migrations/019_lms_tutors_curriculum.sql
-- Tutors, course assignments, curriculum (units/sub-units/topics), tests, projects, submissions, progress.

USE registration_system;

CREATE TABLE IF NOT EXISTS tutors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    bio TEXT,
    photo_path VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    credentials_sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS course_tutors (
    course_id INT NOT NULL,
    tutor_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id, tutor_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES tutors(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS curriculum_units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_course_sort (course_id, sort_order)
);

CREATE TABLE IF NOT EXISTS curriculum_sub_units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unit_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES curriculum_units(id) ON DELETE CASCADE,
    INDEX idx_unit_sort (unit_id, sort_order)
);

CREATE TABLE IF NOT EXISTS curriculum_topics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sub_unit_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sub_unit_id) REFERENCES curriculum_sub_units(id) ON DELETE CASCADE,
    INDEX idx_subunit_sort (sub_unit_id, sort_order)
);

CREATE TABLE IF NOT EXISTS unit_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unit_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    instructions TEXT,
    max_marks DECIMAL(6,2) NOT NULL DEFAULT 100,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES curriculum_units(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS unit_projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unit_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    instructions TEXT,
    max_marks DECIMAL(6,2) NOT NULL DEFAULT 100,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES curriculum_units(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_id INT NOT NULL,
    submission_type ENUM('test','project') NOT NULL,
    test_id INT DEFAULT NULL,
    project_id INT DEFAULT NULL,
    answer_text TEXT,
    file_path VARCHAR(255) DEFAULT NULL,
    status ENUM('submitted','graded') NOT NULL DEFAULT 'submitted',
    marks DECIMAL(6,2) DEFAULT NULL,
    feedback TEXT,
    graded_by INT DEFAULT NULL,
    graded_at TIMESTAMP NULL DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES unit_tests(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES unit_projects(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reg (registration_id),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS student_unit_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_id INT NOT NULL,
    unit_id INT NOT NULL,
    is_unlocked TINYINT(1) NOT NULL DEFAULT 0,
    test_completed TINYINT(1) NOT NULL DEFAULT 0,
    project_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_reg_unit (registration_id, unit_id),
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES curriculum_units(id) ON DELETE CASCADE
);
