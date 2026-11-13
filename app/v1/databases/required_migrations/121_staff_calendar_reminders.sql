-- 121_staff_calendar_reminders.sql
-- Staff Calendar: sending the reminders (app/v1/tools/staffcalendar_reminders.php, run from cron every minute). NEW TABLES ONLY; no existing table, file or setting is changed.
-- Safe to run twice (CREATE TABLE IF NOT EXISTS).
--  staff_calendar_reminder_log: one row for every reminder that is claimed, per person, source (event, birthday, gift or task), item, occurrence, reminder kind and channel (push, email or app).
--                               UNIQUE on those six, so a reminder can never go out twice, even if the script runs twice or two runs overlap: a run first claims the row (INSERT), and only the run
--                               that got it sends. status: claimed, sent, failed or skipped. detail says why (for example "push subscription gone (410)"). A failed send is logged and is not retried.
--  staff_calendar_reminders:    the in-app inbox behind the small "Reminders" bell of the Staff Calendar (title, text, link, unread or read). The portal's own notification table is not used.
CREATE TABLE IF NOT EXISTS staff_calendar_reminder_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    source VARCHAR(12) NOT NULL,
    item_id INT NOT NULL,
    occ_key VARCHAR(20) NOT NULL DEFAULT '',
    kind VARCHAR(16) NOT NULL,
    channel VARCHAR(8) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'claimed',
    detail VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scrl_once (user_id, source, item_id, occ_key, kind, channel),
    KEY idx_scrl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_calendar_reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    kind VARCHAR(16) NOT NULL,
    title VARCHAR(160) NOT NULL,
    body VARCHAR(500) NOT NULL,
    url VARCHAR(300) NOT NULL DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_scr_user (user_id, is_read, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
