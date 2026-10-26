-- Force password change on first login for SmS-provisioned student accounts
USE registration_system;

ALTER TABLE users
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER account_status;
