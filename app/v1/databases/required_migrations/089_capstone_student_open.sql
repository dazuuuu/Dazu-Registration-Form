-- 089_capstone_student_open.sql
-- A tutor can open the capstone for one student early (course finished early). Only adds a column.
ALTER TABLE capstone_students ADD COLUMN opened_early_at DATETIME NULL;
