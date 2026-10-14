-- Blog content blocks: "split" (text one side, image or YouTube video the other) and "divider" (section line).
-- Split blocks keep the media side in cta_type ('left'/'right') and the media kind in media_type ('image'/'youtube').
ALTER TABLE blog_sections
    MODIFY COLUMN section_type ENUM('heading','paragraph','image','image_gallery','youtube','link','quote','cta','faq','split','divider') DEFAULT 'paragraph';
