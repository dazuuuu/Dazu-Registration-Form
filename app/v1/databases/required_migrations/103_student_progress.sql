-- 103_student_progress.sql
-- Student Progress page for tutors and admin. NEW TABLES ONLY: no existing table, column, mark or setting is changed.
-- The page reads marks, assignments and monthly projects the system already holds. It saves only what is below.
--  progress_settings:     the day and count numbers behind "Working well / Slowing down / Not working" (admin changes them in Settings;
--                         the defaults live in the code, so an empty table means the defaults).
--  progress_notes:        a tutor's private notes on a student, with the date and the tutor's name. Seen by the author and admin only.
--  progress_nudges:       every "Send a nudge" a tutor pressed (who, to whom, when, and whether the student's notification choices let it through).
--  progress_activity_log: who downloaded Student Progress cards, and for which students.
CREATE TABLE IF NOT EXISTS progress_settings (
    setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(20) NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS progress_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT NOT NULL,
    author_user_id INT NOT NULL,
    author_name VARCHAR(150) NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pn_student (student_user_id, created_at),
    KEY idx_pn_author (author_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS progress_nudges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_user_id INT NOT NULL,
    sent_by_user_id INT NOT NULL,
    sent_by_name VARCHAR(150) NOT NULL,
    message VARCHAR(500) NOT NULL,
    delivered TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pnu_student (student_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS progress_activity_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NOT NULL,
    actor_name VARCHAR(150) NOT NULL,
    actor_role VARCHAR(20) NOT NULL,
    action VARCHAR(40) NOT NULL,
    student_count INT NOT NULL DEFAULT 0,
    student_ids TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pal_actor (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
