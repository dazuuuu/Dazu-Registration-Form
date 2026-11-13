-- 114_staff_calendar_last_edited.sql
-- Staff Calendar: the details panel shows who last edited an event and when. NEW COLUMNS on the table made by migration 111; no table of the live site is changed.
-- Safe to run twice (a column that already exists is skipped by the updater).
--  last_edited_by   users.id of the person who last saved a change (empty until the event is edited; the panel then says "Not edited yet")
--  last_edited_at   when, as Nairobi time (EAT), like the other times of this calendar
ALTER TABLE staff_calendar_events ADD COLUMN last_edited_by INT NULL;
ALTER TABLE staff_calendar_events ADD COLUMN last_edited_at DATETIME NULL;
