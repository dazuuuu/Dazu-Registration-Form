-- 133_course_import.sql
-- Course importer: modules built from an uploaded spreadsheet remember their import (so they can be replaced by a new upload),
-- and a module can stay locked until the student has finished every other module (the final assignments).
ALTER TABLE course_modules ADD COLUMN unlock_after_all TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE course_modules ADD COLUMN import_batch VARCHAR(40) NULL;
