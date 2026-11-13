-- 111_staff_calendar.sql
-- Staff Calendar: one calendar shared by the tutors and the super admin. NEW TABLES ONLY: no existing table, column or setting is changed (the Class Calendar is untouched).
-- Safe to run twice: every statement is CREATE TABLE IF NOT EXISTS or an insert that first checks the row is not there.
--  staff_calendar_event_types:  the kinds of event and their colours (type_key is what an event stores). The 11 starter types are added below.
--  staff_calendar_events:       one row per event. Times are stored as Nairobi time (EAT, no daylight saving), so what is saved is what people read.
--     all_day        1 = the event covers whole days (start_at is 00:00 of the first day, end_at is 23:59:59 of the last day)
--     repeat_rule    none, weekly, monthly or yearly (the row is kept once; the page works out each repeat for the days on screen)
--     owner_user_id  the person who added it (users.id). The page shows the name only, never the role.
--     notify_all     1 = tutors and the super admin were told about it
--     is_private     1 = only the owner can see it
--     cover_image    optional picture, a path inside public/ (uploads/staff_calendar/...)
CREATE TABLE IF NOT EXISTS staff_calendar_event_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_key VARCHAR(30) NOT NULL,
    label VARCHAR(60) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#048C44',
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_sce_type_key (type_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff_calendar_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    location VARCHAR(255) NULL,
    cover_image VARCHAR(255) NULL,
    type_key VARCHAR(30) NOT NULL DEFAULT 'meeting',
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    all_day TINYINT(1) NOT NULL DEFAULT 0,
    repeat_rule VARCHAR(10) NOT NULL DEFAULT 'none',
    owner_user_id INT NOT NULL,
    notify_all TINYINT(1) NOT NULL DEFAULT 0,
    is_private TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sce_start (start_at),
    KEY idx_sce_owner (owner_user_id),
    KEY idx_sce_type (type_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO staff_calendar_event_types (type_key, label, color, sort_order)
SELECT x.type_key, x.label, x.color, x.sort_order FROM (
    SELECT 'meeting' AS type_key, 'Meeting' AS label, '#048C44' AS color, 1 AS sort_order
    UNION ALL SELECT 'client_meeting', 'Client meeting', '#2F6FC4', 2
    UNION ALL SELECT 'training', 'Training', '#7B4FC9', 3
    UNION ALL SELECT 'deadline', 'Deadline', '#C93A2E', 4
    UNION ALL SELECT 'reminder', 'Reminder', '#E08A00', 5
    UNION ALL SELECT 'birthday', 'Birthday', '#D6457F', 6
    UNION ALL SELECT 'holiday', 'Holiday', '#1A9A9A', 7
    UNION ALL SELECT 'project', 'Project', '#373435', 8
    UNION ALL SELECT 'release', 'Release', '#3F4FC4', 9
    UNION ALL SELECT 'payday', 'Payday', '#A88218', 10
    UNION ALL SELECT 'out_of_office', 'Out of office', '#7A8088', 11
) x
WHERE NOT EXISTS (SELECT 1 FROM staff_calendar_event_types t WHERE t.type_key = x.type_key);
