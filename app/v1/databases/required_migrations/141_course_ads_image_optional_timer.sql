-- Course ads: the image is now optional (falls back to the course's own cover image at display
-- time), and an ad can be given a countdown — how long the promotion runs for.
ALTER TABLE course_ads MODIFY COLUMN image_path VARCHAR(255) NULL;
ALTER TABLE course_ads ADD COLUMN ends_at DATETIME NULL;
