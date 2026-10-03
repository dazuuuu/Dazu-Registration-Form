-- Add certificate template to courses
ALTER TABLE courses ADD COLUMN certificate_template VARCHAR(255) NULL AFTER brochure_path;

-- Table for Course Modules
CREATE TABLE course_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    tutor_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for Module Content (Assignments, Questions, Materials)
CREATE TABLE module_contents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    type ENUM('assignment', 'question', 'material') NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NULL,
    file_path VARCHAR(255) NULL,
    max_score INT NULL,
    due_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES course_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for Student Submissions
CREATE TABLE student_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_id INT UNSIGNED NOT NULL,
    registration_id INT NOT NULL,
    submitted_content TEXT NULL,
    file_path VARCHAR(255) NULL,
    score INT NULL,
    feedback TEXT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    graded_at TIMESTAMP NULL,
    FOREIGN KEY (content_id) REFERENCES module_contents(id) ON DELETE CASCADE,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for Timetable
CREATE TABLE timetables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    tutor_id INT NULL,
    title VARCHAR(150) NOT NULL,
    study_weeks INT NOT NULL DEFAULT 4,
    total_hours INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE timetable_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timetable_id INT UNSIGNED NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    topic VARCHAR(200) NOT NULL,
    location VARCHAR(150) NULL,
    FOREIGN KEY (timetable_id) REFERENCES timetables(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
