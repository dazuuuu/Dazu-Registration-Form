-- Student ID cards (CR80, built-in design — see helpers/id_card_pdf.php).
--  status: requested (student asked) → generated (PDF in the student's portal, card being printed)
--          → ready (printed, waiting at pickup_location) → collected. declined = request turned down.
--  mode_waived: generated for an online student (ID cards are normally for physical students only).
--  qr_hash: random key in the QR code on the back; verify/id.php?k=<hash> shows name, course and status only.

CREATE TABLE IF NOT EXISTS student_id_cards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    user_id INT NULL,
    qr_hash VARCHAR(64) NOT NULL,
    status ENUM('requested','generated','ready','collected','declined') NOT NULL DEFAULT 'requested',
    snapshot_name VARCHAR(150) NULL,
    snapshot_course VARCHAR(150) NULL,
    snapshot_reg_no VARCHAR(30) NULL,
    valid_until SMALLINT UNSIGNED NULL,
    mode_waived TINYINT(1) NOT NULL DEFAULT 0,
    pickup_location ENUM('nairobi','thika') NULL,
    request_note VARCHAR(255) NULL,
    admin_note VARCHAR(255) NULL,
    requested_at TIMESTAMP NULL,
    generated_at TIMESTAMP NULL,
    ready_at TIMESTAMP NULL,
    collected_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_idcard_hash (qr_hash),
    KEY idx_idcard_reg (registration_id),
    KEY idx_idcard_user (user_id),
    KEY idx_idcard_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
