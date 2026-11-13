-- 113_staff_calendar_form.sql
-- Staff Calendar: the add / edit form. NEW COLUMNS on the table made by migration 111 and ONE NEW TABLE; no table of the live site is changed. Safe to run twice
-- (a column that already exists is skipped by the updater; the table is CREATE TABLE IF NOT EXISTS).
--  staff_calendar_events.remind_day_before / remind_on_day / remind_hour_before / remind_email: the reminder switches of the form (1 = on, the form's default). They are only
--     saved for now; sending the reminders comes later. Existing events keep all four on.
--  staff_calendar_checklist: the optional checklist of an event, one row per item (label, ticked or not, order). Removed together with the event by the page.
ALTER TABLE staff_calendar_events ADD COLUMN remind_day_before TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE staff_calendar_events ADD COLUMN remind_on_day TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE staff_calendar_events ADD COLUMN remind_hour_before TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE staff_calendar_events ADD COLUMN remind_email TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS staff_calendar_checklist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    label VARCHAR(200) NOT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    KEY idx_scc_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
