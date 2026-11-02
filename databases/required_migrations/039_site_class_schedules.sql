-- Site-wide class schedules shown on every paid course details page.
-- Admin can edit these from Admin -> Settings -> Class Schedule.
USE registration_system;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('class_schedule_online', '8am, 12pm, 6pm'),
    ('class_schedule_physical', '5am'),
    ('class_schedule_note', 'Students confirm their preferred class slot during admission.')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
