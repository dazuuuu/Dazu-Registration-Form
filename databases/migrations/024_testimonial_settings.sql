-- Default testimonial portal settings
INSERT INTO site_settings (setting_key, setting_value) VALUES
('testimonials_students_open', '0'),
('testimonials_auto_publish', '1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
