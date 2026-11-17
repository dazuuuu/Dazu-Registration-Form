-- 125_secretary_blog_submissions.sql
-- Secretary blogs: posts and changes that wait for the admin's approval. NEW TABLE ONLY: the existing blog tables (blogs, blog_sections, blog_faqs, blog_tags, ...) are not changed.
-- Safe to run twice (CREATE TABLE IF NOT EXISTS).
--  The existing blogs.status (draft, published, archived) is enough for "Published" and "Unpublished". It has no "waiting" value, and nothing is added to it: a post or a change that waits
--  for approval lives only here, so the public site never sees it. When the admin approves, the post is published through the existing blog code (BlogController) and the row here is closed.
--  secretary_blog_submissions: one row per waiting item.
--    kind          'new' (a new post, blog_id empty until approved) or 'change' (a change to the blog blog_id; the public keeps the last approved version until it is approved)
--    status        waiting, approving (a moment, while the admin's approval is being applied), sent_back (with reason), approved (kept as history; result_blog_id says which blog it became or changed)
--    payload       the editor fields as JSON: title, excerpt, content, meta_description, meta_title, meta_keywords, category_id, tags, is_featured, status, featured_image
--    rev           goes up on every save, so an approval of a version the admin did not see is refused
--    base_updated_at  blogs.updated_at when the change was started, so the admin can be warned that the live post changed meanwhile
--    secretary_id  the secretary who saved it last (she is the one told about the decision)
CREATE TABLE IF NOT EXISTS secretary_blog_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(8) NOT NULL DEFAULT 'new',
    blog_id INT DEFAULT NULL,
    secretary_id INT NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    payload LONGTEXT NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'waiting',
    reason VARCHAR(400) DEFAULT NULL,
    rev INT NOT NULL DEFAULT 1,
    base_updated_at VARCHAR(30) DEFAULT NULL,
    result_blog_id INT DEFAULT NULL,
    submitted_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    decided_by INT DEFAULT NULL,
    decided_by_name VARCHAR(160) DEFAULT NULL,
    decided_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_sbs_status (status, id),
    KEY idx_sbs_blog (blog_id),
    KEY idx_sbs_secretary (secretary_id),
    CONSTRAINT fk_sbs_secretary FOREIGN KEY (secretary_id) REFERENCES secretaries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
