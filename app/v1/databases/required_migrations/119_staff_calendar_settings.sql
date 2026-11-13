-- 119_staff_calendar_settings.sql
-- Staff Calendar settings page and holidays. A NEW COLUMN on the type table made by migration 111 and NEW TABLES; no table of the live site is changed. Safe to run twice
-- (a column that already exists is skipped by the updater, tables are CREATE TABLE IF NOT EXISTS, the team defaults are added only when missing).
--  staff_calendar_event_types.is_active   1 = shown. Switching a type off hides its events everywhere on the calendar and removes it from the New event form. The events are not deleted.
--  staff_calendar_user_prefs              a person's own switches (reminders and notices). No row means the switch is on: every switch starts on. They only turn things off for that person.
--  staff_calendar_holidays                the Kenyan public holidays. holiday_date is the day the holiday is kept (a Sunday holiday is kept on the Monday after it), base_date the day it falls on,
--                                         is_confirmed 0 for Eid ul-Fitr until the super admin sets the announced date, source_key makes the yearly fill-in safe to run twice (empty for holidays added by hand).
--  staff_calendar_settings (migration 117): the six team defaults for reminders and notices are added here (team_remind_day_before, team_remind_on_day, team_remind_hour_before,
--                                         team_remind_email, team_notice_birthday, team_notice_gift), all on. They only save choices for now: nothing is sent from here.
ALTER TABLE staff_calendar_event_types ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS staff_calendar_user_prefs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pref_key VARCHAR(40) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scup_user_key (user_id, pref_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_calendar_holidays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL,
    name VARCHAR(120) NOT NULL,
    kind VARCHAR(12) NOT NULL DEFAULT 'fixed',
    is_confirmed TINYINT(1) NOT NULL DEFAULT 1,
    base_date DATE NULL,
    note VARCHAR(160) NOT NULL DEFAULT '',
    source_key VARCHAR(60) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sch_source (source_key),
    KEY idx_sch_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO staff_calendar_settings (setting_key, setting_value)
SELECT x.k, '1' FROM (
    SELECT 'team_remind_day_before' AS k
    UNION ALL SELECT 'team_remind_on_day'
    UNION ALL SELECT 'team_remind_hour_before'
    UNION ALL SELECT 'team_remind_email'
    UNION ALL SELECT 'team_notice_birthday'
    UNION ALL SELECT 'team_notice_gift'
) x
WHERE NOT EXISTS (SELECT 1 FROM staff_calendar_settings s WHERE s.setting_key = x.k);
