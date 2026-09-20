-- Extends blog_sections into a full block-based content editor:
-- heading, paragraph, image, image_gallery, youtube, link, quote, cta, faq.
-- No existing rows use the old section_type values, so the ENUM is safely redefined.
ALTER TABLE blog_sections
    MODIFY COLUMN section_type ENUM('heading','paragraph','image','image_gallery','youtube','link','quote','cta','faq') DEFAULT 'paragraph',
    ADD COLUMN gallery_images TEXT NULL AFTER media_url,
    ADD COLUMN link_label VARCHAR(150) NULL AFTER video_id,
    ADD COLUMN cta_type VARCHAR(20) NULL AFTER link_label,
    ADD COLUMN cta_value VARCHAR(255) NULL AFTER cta_type,
    ADD COLUMN cta_label VARCHAR(150) NULL AFTER cta_value,
    ADD COLUMN faq_items TEXT NULL AFTER cta_label;
