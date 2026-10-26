-- databases/migrations/018_store_orders_minimal.sql
-- Optional: creates store order tables for the admin orders badge / store module.
-- Safe to run on registration_system when store_orders is missing (uses IF NOT EXISTS).
-- Requires users table from migration 001.

USE registration_system;

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
