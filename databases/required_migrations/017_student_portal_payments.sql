-- databases/migrations/017_student_portal_payments.sql
-- Extends registrations and payments for student portal locking, receipts, and payment workflow.

USE realestate_db;

ALTER TABLE registrations
    ADD COLUMN installments_paid TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER installment_amount,
    ADD COLUMN next_payment_due DATE DEFAULT NULL AFTER installments_paid,
    ADD COLUMN portal_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER next_payment_due,
    ADD COLUMN portal_locked_at TIMESTAMP NULL DEFAULT NULL AFTER portal_locked,
    ADD COLUMN credentials_sent_at TIMESTAMP NULL DEFAULT NULL AFTER portal_locked_at;

ALTER TABLE payments
    ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER amount,
    ADD COLUMN mpesa_code VARCHAR(50) DEFAULT NULL AFTER payer_reference,
    ADD COLUMN receipt_no VARCHAR(40) DEFAULT NULL AFTER notes,
    ADD COLUMN receipt_html MEDIUMTEXT DEFAULT NULL AFTER receipt_no,
    ADD COLUMN submitted_by INT DEFAULT NULL AFTER recorded_by,
    ADD COLUMN reviewed_by INT DEFAULT NULL AFTER submitted_by,
    ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER reviewed_by,
    ADD COLUMN rejection_reason VARCHAR(255) DEFAULT NULL AFTER reviewed_at,
    ADD INDEX idx_status (status);
