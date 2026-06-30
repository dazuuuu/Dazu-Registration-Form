-- 012_add_property_sold.sql
-- Only needed if you ALREADY ran 011 before the sold feature was added.
-- (Fresh installs of 011 already include these columns — running this would error,
--  which is fine to ignore in that case.)

ALTER TABLE properties
    ADD COLUMN is_sold TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN sold_at TIMESTAMP NULL AFTER is_sold;

ALTER TABLE properties ADD INDEX idx_prop_sold (is_sold);