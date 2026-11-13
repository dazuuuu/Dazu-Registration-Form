-- 117_staff_calendar_gifts.sql
-- Staff Calendar: the birthday gift tracker. NEW TABLES ONLY (the user and profile tables are not touched). It only records who says they sent a gift and who received it.
-- Dazu does not move any money and nothing here connects to M-Pesa or any payment system. Safe to run twice (CREATE TABLE IF NOT EXISTS, and the setting is added only when missing).
--  staff_calendar_settings:        simple settings of the Staff Calendar. gift_amount (KES, default 1500) is changed by the super admin and applies to new gift lists only.
--  staff_calendar_gift_exclusions: "Excluded from gifts" with a short note such as "on leave". Set by the super admin only. Checked when a list is made.
--  staff_calendar_gift_lists:      one list per birthday person and year (UNIQUE on user_id + gift_year, so it can only be made once). amount is the gift amount saved at that time.
--                                  last_reminder_on is the day the "Remind those who have not sent" button was last used (once a day per birthday).
--                                  The list stays open for 30 days after birthday_date, then it is read-only history.
--  staff_calendar_gift_items:      one row per person on a list, status not_yet, says_sent, received or excluded. confirmed_by is who pressed "Confirm received" (the birthday person or the super admin).
--  staff_calendar_gift_notes:      the gentle in-app reminder notes shown on the Staff Calendar page (the existing notification tables are not used).
CREATE TABLE IF NOT EXISTS staff_calendar_settings (
    setting_key VARCHAR(40) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO staff_calendar_settings (setting_key, setting_value)
SELECT 'gift_amount', '1500' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM staff_calendar_settings WHERE setting_key = 'gift_amount');

CREATE TABLE IF NOT EXISTS staff_calendar_gift_exclusions (
    user_id INT NOT NULL PRIMARY KEY,
    is_excluded TINYINT(1) NOT NULL DEFAULT 1,
    note VARCHAR(120) NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_calendar_gift_lists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gift_year SMALLINT NOT NULL,
    birthday_date DATE NOT NULL,
    amount INT NOT NULL,
    last_reminder_on DATE NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scgl_user_year (user_id, gift_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_calendar_gift_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    list_id INT UNSIGNED NOT NULL,
    giver_user_id INT NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'not_yet',
    excluded_note VARCHAR(120) NULL,
    sent_at DATETIME NULL,
    received_at DATETIME NULL,
    confirmed_by INT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scgi_list_giver (list_id, giver_user_id),
    KEY idx_scgi_giver (giver_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_calendar_gift_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    list_id INT UNSIGNED NOT NULL,
    to_user_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    dismissed_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_scgn_to (to_user_id, dismissed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
