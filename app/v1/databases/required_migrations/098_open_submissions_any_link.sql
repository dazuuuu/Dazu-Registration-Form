-- 098_open_submissions_any_link.sql
-- Open submissions ("Submit work"): accept any https link, not only GitHub and Google Drive (kind 'link'). ADD ONLY.
ALTER TABLE open_submissions MODIFY kind ENUM('github','drive','file','link') NOT NULL;
