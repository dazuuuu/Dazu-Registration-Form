-- databases/migrations/020_registration_referrals.sql
-- Optional referral when a student shares their course link.
USE registration_system;

ALTER TABLE registrations
    ADD COLUMN referral_registration_no VARCHAR(30) NULL DEFAULT NULL
        COMMENT 'registration_no of the student who shared the link'
        AFTER payment_reference;

CREATE INDEX idx_referral_reg_no ON registrations (referral_registration_no);
