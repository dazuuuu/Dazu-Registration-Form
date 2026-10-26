-- 010_fix_store_orders_schema.sql
--
-- WHY: some installations have an older store_orders table shape, while others
-- have no store_orders table yet. OrderModel expects the parcel-ready schema
-- below.
--
-- WHAT THIS DOES: if store_orders is missing, creates the correct tables. If an
-- old incompatible store_orders table exists, renames the existing order tables
-- to timestamped backups first, then creates the correct tables. If the schema
-- is already correct, it does nothing.

SET FOREIGN_KEY_CHECKS = 0;

SET @orders_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'store_orders'
);

SET @orders_required_columns := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'store_orders'
      AND column_name IN (
          'order_number',
          'user_id',
          'customer_name',
          'contact_phone',
          'fulfillment_method',
          'pickup_location',
          'delivery_notes',
          'currency',
          'subtotal',
          'total',
          'payment_status',
          'payment_method',
          'mpesa_merchant_request_id',
          'mpesa_checkout_request_id',
          'mpesa_receipt',
          'mpesa_phone',
          'mpesa_payer_name',
          'paid_at'
      )
);

SET @orders_backup := CONCAT('store_orders_backup_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'));
SET @items_backup := CONCAT('store_order_items_backup_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'));

SET @sql := IF(
    @orders_exists = 1 AND @orders_required_columns < 18,
    CONCAT('RENAME TABLE store_orders TO ', @orders_backup),
    'SET @migration_noop = 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @items_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'store_order_items'
);

SET @items_required_columns := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'store_order_items'
      AND column_name IN (
          'order_id',
          'parcel_id',
          'product_id',
          'product_name',
          'unit_price',
          'quantity',
          'line_total',
          'fulfillment_status',
          'fulfilled_at'
      )
);

SET @sql := IF(
    @items_exists = 1 AND (@orders_required_columns < 18 OR @items_required_columns < 9),
    CONCAT('RENAME TABLE store_order_items TO ', @items_backup),
    'SET @migration_noop = 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS store_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(32) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    fulfillment_method ENUM('walkin','delivery') NOT NULL DEFAULT 'walkin',
    pickup_location VARCHAR(255) NULL,
    delivery_notes VARCHAR(500) NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'KES',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(20) NOT NULL DEFAULT 'mpesa',
    mpesa_merchant_request_id VARCHAR(64) NULL,
    mpesa_checkout_request_id VARCHAR(64) NULL,
    mpesa_receipt VARCHAR(32) NULL,
    mpesa_phone VARCHAR(20) NULL,
    mpesa_payer_name VARCHAR(150) NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_user (user_id),
    INDEX idx_orders_pay (payment_status),
    INDEX idx_orders_checkout (mpesa_checkout_request_id),
    CONSTRAINT fk_orders_user_v2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS store_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    parcel_id VARCHAR(32) UNIQUE NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(255) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    fulfillment_status ENUM('processing','ready_for_pickup','out_for_delivery','picked_up','delivered','arrived','cancelled')
        NOT NULL DEFAULT 'processing',
    fulfilled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_items_order (order_id),
    INDEX idx_items_parcel (parcel_id),
    INDEX idx_items_status (fulfillment_status),
    CONSTRAINT fk_items_order_v2 FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;
