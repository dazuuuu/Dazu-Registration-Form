-- Revert 037_course_brochure_and_school_fees.sql

ALTER TABLE courses
    DROP COLUMN brochure_path,
    DROP COLUMN school_fee_online,
    DROP COLUMN school_fee_physical;
