-- Announcements (full-screen pop-up + notification + email + phone push) and the attachee role.

INSERT INTO `roles` (`role_name`) VALUES ('attachee') ON DUPLICATE KEY UPDATE `role_name` = `role_name`;

-- One announcement. Deleted for good (with its recipients and notifications) once expires_at passes.
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NOT NULL,
    author_name VARCHAR(150) NOT NULL,
    author_role VARCHAR(30) NOT NULL,
    title VARCHAR(150) NOT NULL,
    summary VARCHAR(300) NOT NULL,
    message TEXT NULL,
    media_link VARCHAR(500) NULL COMMENT 'YouTube / TikTok / Facebook video link',
    media_path VARCHAR(255) NULL COMMENT 'Uploaded image, audio or video (public/uploads/announcements)',
    media_kind VARCHAR(10) NULL COMMENT 'image | audio | video',
    audience VARCHAR(20) NOT NULL COMMENT 'students | tutors | admins | all',
    course_id INT NULL COMMENT 'Only students of this course',
    recipient_count INT NOT NULL DEFAULT 0,
    expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ann_author (author_id),
    KEY idx_ann_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcement_recipients (
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    dismissed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (announcement_id, user_id),
    KEY idx_annr_user (user_id, dismissed_at),
    CONSTRAINT fk_annr_announcement FOREIGN KEY (announcement_id) REFERENCES announcements (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A tutor asks an attachee to take over one of their students; the attachee approves or declines.
-- The tutor keeps the student (tutor_students is unchanged) and still follows their progress.
CREATE TABLE IF NOT EXISTS attachee_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attachee_user_id INT NOT NULL,
    registration_id INT NOT NULL,
    student_user_id INT NOT NULL,
    tutor_user_id INT NOT NULL,
    status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
    note VARCHAR(255) NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_attachee_reg (attachee_user_id, registration_id),
    KEY idx_att_reg (registration_id),
    KEY idx_att_status (attachee_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
