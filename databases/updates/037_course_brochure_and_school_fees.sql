-- Optional update: course brochure PDF + separate online/physical school fees.
-- Run from Admin → System Updates. Revert with the paired .revert.sql file.

ALTER TABLE courses
    ADD COLUMN brochure_path VARCHAR(255) DEFAULT NULL AFTER cover_image,
    ADD COLUMN school_fee_online DECIMAL(10,2) DEFAULT NULL AFTER course_fee,
    ADD COLUMN school_fee_physical DECIMAL(10,2) DEFAULT NULL AFTER school_fee_online;
