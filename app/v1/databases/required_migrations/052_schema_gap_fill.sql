-- Migration 052: columns and a table the application uses that no earlier migration created (they only existed on sites
-- that had been set up from a database dump). Without them a fresh install cannot enrol students or reset passwords.
-- On a database that already has them, each ALTER is skipped.
ALTER TABLE registrations ADD COLUMN enrollment_type ENUM('new','continuing') NOT NULL DEFAULT 'new';
ALTER TABLE registrations ADD COLUMN prior_registration_id INT DEFAULT NULL;
ALTER TABLE registrations ADD COLUMN current_learning_month TINYINT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE registrations ADD COLUMN fee_balance_note VARCHAR(255) DEFAULT NULL;
ALTER TABLE registrations ADD COLUMN fee_status ENUM('current','partial','overdue','paid_in_full') NOT NULL DEFAULT 'current';

CREATE TABLE IF NOT EXISTS password_reset_otps (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  email VARCHAR(100) NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_created (email, created_at),
  KEY idx_user_active (user_id, used_at),
  KEY idx_expires (expires_at),
  CONSTRAINT password_reset_otps_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
