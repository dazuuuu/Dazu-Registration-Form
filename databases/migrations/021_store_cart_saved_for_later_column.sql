-- databases/migrations/021_store_cart_saved_for_later_column.sql
-- Moved out of 008_create_cart_tables.sql: it targeted store_cart, which
-- isn't created until 009_create_store_tables.sql.
-- Optional: enables the "Save for later" / "Move to cart" actions.
-- The consolidated cart API works without this; these two actions simply return
-- a friendly "not enabled" message until the column exists. Run once.
-- (MySQL has no ADD COLUMN IF NOT EXISTS before MariaDB 10.0 / MySQL 8.0.x — if
--  the column already exists you'll get a harmless "Duplicate column" error.)
USE registration_system;

ALTER TABLE store_cart
    ADD COLUMN saved_for_later TINYINT(1) NOT NULL DEFAULT 0;
