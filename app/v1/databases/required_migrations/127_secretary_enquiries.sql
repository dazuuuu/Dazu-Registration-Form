-- 127_secretary_enquiries.sql
-- The secretary team's enquiries log: people who ASKED about a course. NEW TABLES ONLY; nothing existing is changed. Safe to run twice (CREATE TABLE IF NOT EXISTS).
-- This log is for prospects only. It has no link to any student record: no student id, no registration id, no payment. "Registered" is a status the secretary sets by hand.
-- The course is plain text (typed, or copied from the existing course names), not a link to the courses table.
--  secretary_enquiries:        one row per enquiry. added_by is the secretary who added it (secretaries.id); added_by_name is her name when she added it (name only). handled_by is the secretary who took it
--                              over (empty until someone does). status: pending, called_back, registered, not_interested. source: phone, walk_in, website, social, other.
--  secretary_enquiry_notes:    every note, with who wrote it (name only) and when. The first note is written when the enquiry is added.
--  secretary_enquiry_history:  every change and action with the name and the time: created, status, follow_up, note, taken_over (action; old_value and new_value say what changed). This is the record the activity page reads.
CREATE TABLE IF NOT EXISTS secretary_enquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    course VARCHAR(150) NOT NULL DEFAULT '',
    source VARCHAR(10) NOT NULL DEFAULT 'phone',
    enquiry_date DATE NOT NULL,
    follow_up_date DATE DEFAULT NULL,
    status VARCHAR(14) NOT NULL DEFAULT 'pending',
    added_by INT NOT NULL,
    added_by_name VARCHAR(160) NOT NULL DEFAULT '',
    handled_by INT DEFAULT NULL,
    handled_by_name VARCHAR(160) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_se_status (status, id),
    KEY idx_se_follow (follow_up_date),
    KEY idx_se_added (added_by),
    CONSTRAINT fk_se_added FOREIGN KEY (added_by) REFERENCES secretaries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS secretary_enquiry_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enquiry_id INT NOT NULL,
    secretary_id INT NOT NULL,
    author_name VARCHAR(160) NOT NULL DEFAULT '',
    body VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sen_enquiry (enquiry_id, id),
    CONSTRAINT fk_sen_enquiry FOREIGN KEY (enquiry_id) REFERENCES secretary_enquiries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS secretary_enquiry_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enquiry_id INT NOT NULL,
    secretary_id INT DEFAULT NULL,
    actor_name VARCHAR(160) NOT NULL DEFAULT '',
    action VARCHAR(16) NOT NULL,
    old_value VARCHAR(200) DEFAULT NULL,
    new_value VARCHAR(200) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_seh_enquiry (enquiry_id, id),
    KEY idx_seh_created (created_at),
    CONSTRAINT fk_seh_enquiry FOREIGN KEY (enquiry_id) REFERENCES secretary_enquiries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
