-- 115_staff_calendar_birthdays.sql
-- Staff Calendar: birthdays and the birthday messages. NEW TABLES ONLY: the profile and user tables are not touched. Safe to run twice (CREATE TABLE IF NOT EXISTS).
--  staff_calendar_birthdays:         one row per person (a tutor or the super admin): day, month, an optional year and the "show my age" flag.
--                                    Birthdays are worked out for every year from this table (a 29 February birthday shows on 28 February in other years). They are not stored as events.
--                                    The year is only used for the age, and the age is only shown when the person switched "show my age" on.
--  staff_calendar_birthday_messages: the messages. tag is Funny, Cherished, Tech, Sheng or Blessing. status is approved, pending (a tutor's suggestion waiting for the super admin) or rejected.
--                                    is_active switches a message off without deleting it, so the log below stays correct. suggested_by is the users.id of whoever suggested it (empty for the starter messages).
--                                    The text may use {first_name}.
--  staff_calendar_birthday_log:      the message a person got on a birthday. UNIQUE (user_id, birth_year): two people opening the calendar at the same moment can never make two messages.
--                                    (the column is named birth_year because it holds the year of that birthday)
CREATE TABLE IF NOT EXISTS staff_calendar_birthdays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    birth_day TINYINT NOT NULL,
    birth_month TINYINT NOT NULL,
    birth_year SMALLINT NULL,
    show_age TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scb_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_calendar_birthday_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    body VARCHAR(500) NOT NULL,
    tag VARCHAR(12) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    status VARCHAR(10) NOT NULL DEFAULT 'approved',
    suggested_by INT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_scbm_status (status, is_active),
    KEY idx_scbm_tag (tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_calendar_birthday_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    birth_year SMALLINT NOT NULL,
    message_id INT UNSIGNED NOT NULL,
    shown_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scbl_user_year (user_id, birth_year),
    KEY idx_scbl_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
