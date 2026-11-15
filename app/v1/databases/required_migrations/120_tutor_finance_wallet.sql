-- 120_tutor_finance_wallet.sql
-- Tutor Finance page: a 6-digit wallet PIN per tutor, and "missed payment" claims sent to the superadmin.
ALTER TABLE tutor_payroll_requests MODIFY type ENUM('unassign','complete','adjust','claim') NOT NULL;
ALTER TABLE tutor_payroll_requests ADD COLUMN months TINYINT UNSIGNED NULL AFTER amount;
CREATE TABLE IF NOT EXISTS tutor_wallet_pins (
    tutor_id INT NOT NULL PRIMARY KEY,
    pin_hash VARCHAR(255) NOT NULL,
    failed_tries TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
