-- Migration: Seed initial video testimonials so they can be managed from the admin panel
INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, testimonial_type, video_url, video_provider, thumbnail_url, service_tag, role, status, is_featured, sort_order, created_at, approved_at)
SELECT 'Faith Wambui', 'FW', 5, 'Full-Stack software development graduate sharing her learning journey at Dazu Hub.', 'video', 'https://www.youtube.com/watch?v=EUY5XaeDsCI', 'youtube', 'https://img.youtube.com/vi/EUY5XaeDsCI/hqdefault.jpg', 'Web Development', 'Full-Stack Graduate', 'approved', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM testimonials WHERE video_url = 'https://www.youtube.com/watch?v=EUY5XaeDsCI');

INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, testimonial_type, video_url, video_provider, thumbnail_url, service_tag, role, status, is_featured, sort_order, created_at, approved_at)
SELECT 'Kevin Mwangi', 'KM', 5, 'Cybersecurity and network administration graduate on hands-on lab training.', 'video', 'https://www.youtube.com/watch?v=wsnMje68J9M', 'youtube', 'https://img.youtube.com/vi/wsnMje68J9M/hqdefault.jpg', 'Cybersecurity & Networks', 'Security Graduate', 'approved', 1, 2, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM testimonials WHERE video_url = 'https://www.youtube.com/watch?v=wsnMje68J9M');

INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, testimonial_type, video_url, video_provider, thumbnail_url, service_tag, role, status, is_featured, sort_order, created_at, approved_at)
SELECT 'Naomi Chege', 'NC', 5, 'Data Analytics alumna analyzing big data pipelines and practical analytics dashboards.', 'video', 'https://www.youtube.com/watch?v=y0vgFgUXYAI', 'youtube', 'https://img.youtube.com/vi/y0vgFgUXYAI/hqdefault.jpg', 'Data Analytics', 'Data Analyst Graduate', 'approved', 1, 3, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM testimonials WHERE video_url = 'https://www.youtube.com/watch?v=y0vgFgUXYAI');

INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, testimonial_type, video_url, video_provider, thumbnail_url, service_tag, role, status, is_featured, sort_order, created_at, approved_at)
SELECT 'David Ochieng', 'DO', 5, 'AI & Digital Marketing graduate leveraging modern AI workflow tools.', 'video', 'https://www.youtube.com/watch?v=BK0wlEJ3nR4', 'youtube', 'https://img.youtube.com/vi/BK0wlEJ3nR4/hqdefault.jpg', 'AI & Digital Marketing', 'Marketing Graduate', 'approved', 1, 4, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM testimonials WHERE video_url = 'https://www.youtube.com/watch?v=BK0wlEJ3nR4');
