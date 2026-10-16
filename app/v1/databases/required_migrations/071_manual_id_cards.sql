-- Manually entered ID cards (Admin → Student ID Cards → Generate ID card → Manual entry).
-- For anyone, with or without a portal account: students, tutors, administrators, directors, employees, attachees.
-- The QR on the back opens verify/id.php?k=<qr_hash> like every other card.
CREATE TABLE IF NOT EXISTS manual_id_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('student','tutor','administrator','director','employee','attachee') NOT NULL,
    id_no VARCHAR(50) NOT NULL,
    title VARCHAR(150) NULL COMMENT 'Course for a student, job title or department for everyone else',
    photo_path VARCHAR(255) NOT NULL,
    valid_until SMALLINT NOT NULL,
    qr_hash CHAR(32) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_manual_id_qr (qr_hash),
    UNIQUE KEY uq_manual_id_no (id_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
