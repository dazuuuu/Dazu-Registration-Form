-- Fix courses not showing on share page (run in dazutech_registration)

-- Add is_active if missing (ignore error if column already exists)
ALTER TABLE courses
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER last_sequence;

-- Activate all existing courses
UPDATE courses SET is_active = 1 WHERE is_active IS NULL OR is_active = 0;
