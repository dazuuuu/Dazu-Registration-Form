-- 112_staff_calendar_seed_event.sql
-- Staff Calendar: the first event, "Staff meeting" on Saturday 21 November 2026, 10:00 am to 12:00 pm Nairobi time, owned by the super admin, everyone notified.
-- Safe to run twice and safe on the live site: the row is added only when no event with this title, this date and this owner exists, and only when a super admin exists.
-- (Adding it here does not send notifications; the flag only records that the meeting is for everyone.)
INSERT INTO staff_calendar_events (title, description, location, type_key, start_at, end_at, all_day, repeat_rule, owner_user_id, notify_all, is_private, created_at, updated_at)
SELECT 'Staff meeting', '', 'Nairobi House (Cianda House), 4th Floor, Room 406B', 'meeting', '2026-11-21 10:00:00', '2026-11-21 12:00:00', 0, 'none', o.id, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM users o
WHERE o.id = (SELECT MIN(u.id) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.role_name = 'superadmin')
  AND NOT EXISTS (
      SELECT 1 FROM staff_calendar_events e
      WHERE e.title = 'Staff meeting' AND e.owner_user_id = o.id
        AND e.start_at >= '2026-11-21 00:00:00' AND e.start_at < '2026-11-22 00:00:00'
  );
