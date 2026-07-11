-- Video testimonials support (run once; ignore errors if columns already exist)
ALTER TABLE testimonials ADD COLUMN testimonial_type ENUM('text', 'video') NOT NULL DEFAULT 'text' AFTER testimonial_text;
ALTER TABLE testimonials ADD COLUMN video_url VARCHAR(500) NULL AFTER testimonial_type;
ALTER TABLE testimonials ADD COLUMN video_provider ENUM('youtube', 'vimeo', 'upload', 'none') NOT NULL DEFAULT 'none' AFTER video_url;
ALTER TABLE testimonials ADD COLUMN avatar_url VARCHAR(500) NULL AFTER customer_initial;
ALTER TABLE testimonials ADD COLUMN thumbnail_url VARCHAR(500) NULL AFTER avatar_url;