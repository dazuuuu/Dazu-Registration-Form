-- 105_self_study_study_screen.sql
-- Self-study (step 2): the study screen a student opens from the Self-study list. NEW TABLES ONLY: no existing table, column or setting is changed.
-- The tutor screens that fill these tables come in a later step; the study screen only reads them (and saves the student's own progress).
--  self_study_modules:        the modules of a course, in order. pass_mark is the module quiz pass mark in percent.
--  self_study_topics:         the topics, in order. is_intro marks the Course Introduction (it has no module). content_html is cleaned before it is shown.
--                             video_id is a YouTube id or link (the screen accepts only YouTube and reduces it to the id); video_duration (seconds) is optional, the player reports it when it is empty.
--  self_study_files:          files for the Resources tab. file_path is relative to app/v1/storage/selfstudy (private, served by file.php after an access check).
--  self_study_questions:      one bank for every place: knowledge_check, topic, module_quiz, checkpoint_exam, final_exam.
--                             options_json is a JSON list of answers, correct_index the right one; accepted_answers is a JSON list for short answers.
--  self_study_course_rules:   pass marks of the Checkpoint Exam and the Final Exam (a course without a row uses 70).
--  self_study_topic_progress: one row per student and topic. question_set keeps the six questions drawn for that student and how their options were shuffled.
--  self_study_video_progress: the furthest second reached and the seconds really watched, as checked by the server.
--  self_study_answers:        what the student answered; attempt counts the tries of a quiz, exam or the Knowledge Check. is_correct stays empty for long answers.
CREATE TABLE IF NOT EXISTS self_study_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    pass_mark INT NOT NULL DEFAULT 50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ssm_course (course_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_topics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    content_html TEXT NULL,
    video_id VARCHAR(255) NULL,
    video_duration INT NULL,
    is_intro SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sst_course (course_id, position),
    KEY idx_sst_module (module_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    topic_id INT UNSIGNED NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ssf_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    place VARCHAR(20) NOT NULL,
    topic_id INT UNSIGNED NULL,
    module_id INT UNSIGNED NULL,
    qtype VARCHAR(16) NOT NULL DEFAULT 'multiple_choice',
    question_text TEXT NOT NULL,
    options_json TEXT NULL,
    correct_index INT NULL,
    accepted_answers TEXT NULL,
    position INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ssq_course (course_id, place),
    KEY idx_ssq_topic (topic_id),
    KEY idx_ssq_module (module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_course_rules (
    course_id INT UNSIGNED NOT NULL PRIMARY KEY,
    checkpoint_pass INT NOT NULL DEFAULT 70,
    final_pass INT NOT NULL DEFAULT 70
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_topic_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    topic_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    last_seen_at TIMESTAMP NULL DEFAULT NULL,
    video_watched SMALLINT NOT NULL DEFAULT 0,
    questions_submitted SMALLINT NOT NULL DEFAULT 0,
    score_correct INT NULL,
    score_total INT NULL,
    question_set TEXT NULL,
    done_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_sstp_student_topic (student_id, topic_id),
    KEY idx_sstp_course (student_id, course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_video_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    topic_id INT UNSIGNED NOT NULL,
    furthest_second INT NOT NULL DEFAULT 0,
    seconds_watched INT NOT NULL DEFAULT 0,
    duration INT NOT NULL DEFAULT 0,
    completed SMALLINT NOT NULL DEFAULT 0,
    last_tick_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ssvp_student_topic (student_id, topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS self_study_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    answer TEXT NULL,
    is_correct SMALLINT NULL,
    attempt INT NOT NULL DEFAULT 1,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ssans_student (student_id, course_id),
    KEY idx_ssans_question (student_id, question_id, attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
