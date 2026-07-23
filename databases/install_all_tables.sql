-- Dazu Hub Institute — full schema for cPanel/phpMyAdmin
-- 1. In phpMyAdmin, click your database (e.g. dazutech_registration) in the left sidebar
-- 2. Open the Import tab and upload this file
-- 3. Do NOT create a new database — import INTO the one cPanel already gave you

-- ===== 001_create_users_table.sql =====
-- databases/migrations/001_create_users_table.sql

-- Create roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert roles
INSERT INTO roles (role_name) VALUES 
('superadmin'),
('admin'),
('user')
ON DUPLICATE KEY UPDATE role_name = role_name;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- Create user_profiles table
CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INT PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create login_attempts table
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100),
    ip_address VARCHAR(45),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempt_time)
);

-- Insert a default super admin (password: Admin123!)
INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified) 
VALUES ('Administrator', 'admin@gmail.com', '$2y$10$Gwm4NsOLyUch9BclS6eDfeod2AUT1vFDwhellc.tLmRbRi9e.QGdi', 1, 1, 1)
ON DUPLICATE KEY UPDATE username = username;

-- ===== 002_create_projects_tables.sql =====
-- databases/migrations/002_create_projects_tables.sql

-- Create project categories table
CREATE TABLE IF NOT EXISTS project_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    category_slug VARCHAR(100) NOT NULL UNIQUE,
    category_description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_slug (category_slug)
);

-- Create projects table
CREATE TABLE IF NOT EXISTS projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    small_title VARCHAR(100) NOT NULL,
    major_title VARCHAR(200) NOT NULL,
    project_slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT,
    cover_image VARCHAR(255),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    view_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES project_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_slug (project_slug)
);

-- Create project gallery table (for multiple images)
CREATE TABLE IF NOT EXISTS project_gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_title VARCHAR(100),
    image_description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id),
    INDEX idx_sort (sort_order)
);

-- Create project videos table
CREATE TABLE IF NOT EXISTS project_videos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    video_title VARCHAR(200),
    video_url VARCHAR(500) NOT NULL,
    video_embed_code TEXT,
    video_type ENUM('youtube', 'vimeo', 'local', 'other') DEFAULT 'youtube',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
);

-- Create project tags table
CREATE TABLE IF NOT EXISTS project_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tag_name VARCHAR(50) NOT NULL UNIQUE,
    tag_slug VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create project-tag relationship table
CREATE TABLE IF NOT EXISTS project_tag_relations (
    project_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (project_id, tag_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES project_tags(id) ON DELETE CASCADE
);

-- Insert sample categories
INSERT INTO project_categories (category_name, category_slug, category_description) VALUES 
('Web Development', 'web-development', 'Web development projects including websites and web applications'),
('Mobile Apps', 'mobile-apps', 'Mobile application development projects'),
('UI/UX Design', 'ui-ux-design', 'User interface and experience design projects'),
('E-commerce', 'ecommerce', 'E-commerce platform and online store projects'),
('Custom Software', 'custom-software', 'Custom software development projects')
ON DUPLICATE KEY UPDATE category_name = category_name;

-- ===== 003_create_services_tables.sql =====

-- Create services table
CREATE TABLE IF NOT EXISTS services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    short_description TEXT,
    cover_image VARCHAR(255),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    view_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_slug (slug),
    INDEX idx_created_by (created_by)
);

-- Create service_sections table (for flexible content sections)
CREATE TABLE IF NOT EXISTS service_sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    section_type ENUM('text_only', 'text_image_left', 'text_image_right', 'image_gallery', 'video') DEFAULT 'text_only',
    title VARCHAR(200),
    content TEXT,
    media_url VARCHAR(500),
    media_type ENUM('image', 'video', 'youtube', 'vimeo') DEFAULT 'image',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id),
    INDEX idx_sort (sort_order)
);

-- Create service_gallery table
CREATE TABLE IF NOT EXISTS service_gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_title VARCHAR(100),
    image_description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id),
    INDEX idx_sort (sort_order)
);

-- Create service_benefits table
CREATE TABLE IF NOT EXISTS service_benefits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    benefit_title VARCHAR(200) NOT NULL,
    benefit_description TEXT,
    icon_class VARCHAR(100),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id)
);

-- Create service_faqs table
CREATE TABLE IF NOT EXISTS service_faqs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_id INT NOT NULL,
    question VARCHAR(300) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service (service_id)
);

-- ===== 004_create_blogs_tables.sql =====

-- Create blog categories table (admin can create their own)
CREATE TABLE IF NOT EXISTS blog_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    color VARCHAR(20) DEFAULT '#667eea',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_slug (slug)
);

-- Create blogs table
CREATE TABLE IF NOT EXISTS blogs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT,
    content LONGTEXT,
    featured_image VARCHAR(255),
    category_id INT NULL,
    author_id INT NOT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    view_count INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(255),
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_slug (slug),
    INDEX idx_author (author_id),
    INDEX idx_category (category_id),
    INDEX idx_published (published_at),
    INDEX idx_featured (is_featured)
);

-- Create blog sections table (for flexible content)
CREATE TABLE IF NOT EXISTS blog_sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    blog_id INT NOT NULL,
    section_type ENUM('text_only', 'text_image_left', 'text_image_right', 'image_gallery', 'video', 'youtube', 'code_block', 'quote') DEFAULT 'text_only',
    title VARCHAR(255),
    content TEXT,
    media_url VARCHAR(500),
    media_type ENUM('image', 'video', 'youtube') DEFAULT 'image',
    video_id VARCHAR(100),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    INDEX idx_blog (blog_id),
    INDEX idx_sort (sort_order)
);

-- Create blog FAQs table
CREATE TABLE IF NOT EXISTS blog_faqs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    blog_id INT NOT NULL,
    question VARCHAR(300) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    INDEX idx_blog (blog_id)
);

-- Create blog tags table
CREATE TABLE IF NOT EXISTS blog_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create blog-tag relationship table
CREATE TABLE IF NOT EXISTS blog_tag_relations (
    blog_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (blog_id, tag_id),
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
);

-- ===== 005_create_settings_tables.sql =====
-- =====================================================================
-- 005_create_settings_tables.sql
-- Site settings (logo + site name), homepage hero slides, and the
-- per-page header banners used by the public layout.
-- Safe to run more than once (IF NOT EXISTS + idempotent seeds).
-- =====================================================================

-- ---------------------------------------------------------------------
-- Key/value singletons: logo, site name, etc.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('site_name', 'Ismano'),
    ('logo_path', NULL),
    ('logo_alt',  'Ismano')
ON DUPLICATE KEY UPDATE setting_key = setting_key;   -- no-op if row exists

-- ---------------------------------------------------------------------
-- Homepage hero slideshow images (managed by admin).
-- image_path is stored relative to /public  (e.g. uploads/hero/x.png)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hero_slides (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    image_path  VARCHAR(255) NOT NULL,
    caption     VARCHAR(255) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hero_active_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Per-page header banners for public pages (services, projects, ...).
-- One row per page_key; image managed by admin.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS page_headers (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    page_key    VARCHAR(60)  NOT NULL UNIQUE,
    title       VARCHAR(150) NULL,
    subtitle    VARCHAR(255) NULL,
    image_path  VARCHAR(255) NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO page_headers (page_key, title, subtitle) VALUES
    ('services', 'Our Services', 'Comprehensive digital solutions tailored to elevate your business.'),
    ('projects', 'Our Projects', 'A selection of the work we are proud of.'),
    ('blogs',    'Our Blog',     'Insights, ideas and updates from the team.'),
    ('contact',  'Get in Touch', 'We would love to hear about your project.')
ON DUPLICATE KEY UPDATE page_key = page_key;

-- ===== 006_create_gallery_tables.sql =====
-- Gallery table for images and videos
CREATE TABLE IF NOT EXISTS gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    media_type ENUM('image', 'video') DEFAULT 'image',
    file_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500),
    video_url VARCHAR(500), -- For external videos (YouTube/Vimeo)
    video_embed_code TEXT, -- For embedded videos
    category VARCHAR(100),
    tags VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    view_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_media_type (media_type),
    INDEX idx_sort (sort_order)
);

-- Gallery categories (optional)
CREATE TABLE IF NOT EXISTS gallery_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===== 007_create_products_tables.sql =====
-- Product categories
CREATE TABLE IF NOT EXISTS product_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image_path VARCHAR(500),
    parent_id INT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES product_categories(id) ON DELETE SET NULL,
    INDEX idx_active (is_active),
    INDEX idx_slug (slug)
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    compare_price DECIMAL(10, 2), -- Original price for sale display
    sku VARCHAR(100) UNIQUE,
    stock_quantity INT DEFAULT 0,
    category_id INT,
    featured_image VARCHAR(500),
    gallery_images TEXT, -- JSON array of additional images
    status ENUM('active', 'inactive', 'draft') DEFAULT 'draft',
    is_featured BOOLEAN DEFAULT FALSE,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(255),
    view_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_price (price),
    FULLTEXT INDEX idx_search (name, description)
);

-- ===== 008_create_cart_tables.sql =====
-- Shopping cart sessions (for non-logged in users)
CREATE TABLE IF NOT EXISTS cart_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id)
);

-- Cart items
CREATE TABLE IF NOT EXISTS cart_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cart_session_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL, -- Snapshot of price at add time
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_session_id) REFERENCES cart_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_product (cart_session_id, product_id),
    INDEX idx_cart (cart_session_id),
    INDEX idx_product (product_id)
);

-- Saved for later items
CREATE TABLE IF NOT EXISTS saved_for_later (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cart_session_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_session_id) REFERENCES cart_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_saved (cart_session_id, product_id)
);

-- 008_store_cart_saved_for_later.sql
-- Optional: enables the "Save for later" / "Move to cart" actions.
-- The consolidated cart API works without this; these two actions simply return
-- a friendly "not enabled" message until the column exists. Run once.
-- (MySQL has no ADD COLUMN IF NOT EXISTS before MariaDB 10.0 / MySQL 8.0.x — if
--  the column already exists you'll get a harmless "Duplicate column" error.)

ALTER TABLE store_cart
    ADD COLUMN saved_for_later TINYINT(1) NOT NULL DEFAULT 0;

-- ===== 009_create_store_tables.sql =====
-- Store categories table
CREATE TABLE IF NOT EXISTS store_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image_path VARCHAR(500),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_slug (slug)
);

-- Store products table
CREATE TABLE IF NOT EXISTS store_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    compare_price DECIMAL(10, 2),
    sku VARCHAR(100),
    stock_quantity INT DEFAULT 0,
    category_id INT,
    featured_image VARCHAR(500),
    gallery_images TEXT,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'draft',
    is_featured BOOLEAN DEFAULT FALSE,
    meta_title VARCHAR(255),
    meta_description TEXT,
    sort_order INT DEFAULT 0,
    view_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_price (price),
    FULLTEXT INDEX idx_search (name, description)
);

-- Shopping cart table (for logged-in users)
CREATE TABLE IF NOT EXISTS store_cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id),
    INDEX idx_user (user_id)
);

-- Saved for later items
CREATE TABLE IF NOT EXISTS store_saved_for_later (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product_saved (user_id, product_id),
    INDEX idx_user_saved (user_id)
);

-- Orders table (for future use)
CREATE TABLE IF NOT EXISTS store_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    shipping_address TEXT,
    billing_address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_order_number (order_number)
);

-- Order items table
CREATE TABLE IF NOT EXISTS store_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
    INDEX idx_order (order_id)
);

-- Insert default categories
INSERT INTO store_categories (name, slug, description, sort_order) VALUES
('Electronics', 'electronics', 'Electronic devices and gadgets', 1),
('Clothing', 'clothing', 'Fashion and apparel', 2),
('Books', 'books', 'Books and publications', 3),
('Home & Living', 'home-living', 'Home decor and living essentials', 4),
('Sports', 'sports', 'Sports equipment and gear', 5)
ON DUPLICATE KEY UPDATE name = name;

-- 009_create_store_orders.sql
-- Orders + order items. Each order item IS a "parcel": it gets its own unique
-- parcel_id and its own fulfillment_status that the admin advances. The order
-- holds the customer/payment info; items hold the per-product parcels.

CREATE TABLE IF NOT EXISTS store_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(32) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    fulfillment_method ENUM('walkin','delivery') NOT NULL DEFAULT 'walkin',
    pickup_location VARCHAR(255) NULL,         -- pickup point (walk-in) OR delivery address
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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS store_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    parcel_id VARCHAR(32) UNIQUE NOT NULL,
    product_id INT NULL,                        -- snapshot link (nullable if product later deleted)
    product_name VARCHAR(255) NOT NULL,         -- snapshot so receipts stay correct
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
    FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE
);

-- ===== 010_fix_store_orders_schema.sql =====
-- 010_fix_store_orders_schema.sql
--
-- WHY: an older store_orders table exists with a different structure, so inserts
-- fail with "Unknown column 'customer_name'". Migration 009 used
-- CREATE TABLE IF NOT EXISTS, so it skipped the existing table.
--
-- WHAT THIS DOES: renames the existing order tables to *_backup (your old rows
-- are preserved there), then creates store_orders / store_order_items with the
-- exact columns OrderModel expects. New foreign keys are explicitly named so
-- they can't clash with the backup tables' auto-named constraints.
--
-- Run this whole file once in phpMyAdmin / your DB tool. After confirming
-- checkout works, you can DROP the *_backup tables if you don't need the old data.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS store_orders_backup;
DROP TABLE IF EXISTS store_order_items_backup;

ALTER TABLE store_orders      RENAME TO store_orders_backup;
ALTER TABLE store_order_items RENAME TO store_order_items_backup;

CREATE TABLE store_orders (
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

CREATE TABLE store_order_items (
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

-- ===== 011_create_enquiries_table.sql =====
-- Enquiries table for managing contact form submissions
CREATE TABLE IF NOT EXISTS enquiries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    service VARCHAR(100),
    message TEXT,
    status ENUM('new', 'read', 'contacted', 'closed') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    notes TEXT,
    contacted_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_created (created_at),
    FULLTEXT INDEX idx_search (name, email, message)
);

-- Create replies table for admin responses
CREATE TABLE IF NOT EXISTS enquiry_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    enquiry_id INT NOT NULL,
    admin_id INT NOT NULL,
    reply TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_enquiry (enquiry_id)
);

-- Insert sample status labels for reference
INSERT INTO enquiries (name, email, phone, service, message, status) VALUES
('Test User', 'test@example.com', '0712345678', 'Commercial Kitchen', 'This is a test enquiry', 'closed')
ON DUPLICATE KEY UPDATE id = id;

-- ===== 012_create_testimonials_table.sql =====
-- Testimonials table
CREATE TABLE IF NOT EXISTS testimonials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    customer_initial VARCHAR(5),
    rating INT DEFAULT 5,
    testimonial_text TEXT NOT NULL,
    service_tag VARCHAR(100),
    role VARCHAR(100),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    is_featured BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_rating (rating),
    INDEX idx_featured (is_featured),
    INDEX idx_sort (sort_order)
);

-- Insert sample testimonials
INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, service_tag, role, status, is_featured) VALUES
('James Mwangi', 'J', 5, 'ISMAN designed and installed our 450 sqm hotel kitchen in under 8 weeks. The SS304 fabrication quality exceeded international standards, and their team worked around our operational hours without a single disruption to guests.', 'Commercial Kitchen', 'General Manager, Radisson Blu Nairobi', 'approved', 1),
('Aisha Noor', 'A', 5, 'The stainless balustrade work at Two Rivers was flawless. Precision welds, perfect alignment across three floors, and delivered ahead of schedule. We have used them on every project since.', 'Stainless Railing', 'Project Lead, Centum Investment', 'approved', 1),
('Dr. Peter Otieno', 'P', 5, 'Their hospital fit-out met every infection-control requirement we set. Documentation was thorough and the finish on the SS316 surfaces is exactly what a sterile environment needs.', 'Hospital Fit-out', 'Facilities Director, Kenyatta National Hospital', 'approved', 1),
('Grace Wambui', 'G', 5, 'We commissioned a full processing line and ISMAN handled design, fabrication and install end to end. HACCP-ready, on budget, and running at full throughput from day one.', 'Food Processing', 'Operations Manager, Brookside Dairy', 'approved', 1)
ON DUPLICATE KEY UPDATE id = id;

-- ===== 013_create_properties.sql =====
-- 011_create_properties.sql
-- Real-estate core: counties (admin manages country + region), properties (listings),
-- property_images (gallery), property_videos (YouTube/other URLs).

CREATE TABLE IF NOT EXISTS counties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Kenya',
    region VARCHAR(120) NULL,                 -- e.g. "Eastern"
    slug VARCHAR(140) UNIQUE NOT NULL,
    center_lat DECIMAL(10,7) NULL,            -- map centre for this county
    center_lng DECIMAL(10,7) NULL,
    zoom TINYINT NOT NULL DEFAULT 11,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_counties_country (country)
);

CREATE TABLE IF NOT EXISTS properties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) UNIQUE NOT NULL,
    type ENUM('residential','commercial') NOT NULL DEFAULT 'residential',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_hot_offer TINYINT(1) NOT NULL DEFAULT 0,
    cover_image VARCHAR(255) NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Kenya',
    county_id INT NULL,
    location_text VARCHAR(200) NULL,          -- free text, e.g. "Ruiru - Kimbo"
    bedrooms TINYINT NULL,                     -- 1..8, NULL for commercial / N/A
    price DECIMAL(14,2) NULL,                  -- optional asking price (KSh)
    description MEDIUMTEXT NULL,               -- rich HTML (paragraphs, lists)
    latitude DECIMAL(10,7) NULL,               -- exact pin for the map
    longitude DECIMAL(10,7) NULL,
    status ENUM('published','draft') NOT NULL DEFAULT 'published',
    is_sold TINYINT(1) NOT NULL DEFAULT 0,
    sold_at TIMESTAMP NULL,
    view_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prop_county (county_id),
    INDEX idx_prop_type (type),
    INDEX idx_prop_status (status),
    INDEX idx_prop_featured (is_featured),
    INDEX idx_prop_hot (is_hot_offer),
    INDEX idx_prop_sold (is_sold),
    CONSTRAINT fk_prop_county FOREIGN KEY (county_id) REFERENCES counties(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS property_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    property_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pimg_prop (property_id),
    CONSTRAINT fk_pimg_prop FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS property_videos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    property_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    title VARCHAR(150) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pvid_prop (property_id),
    CONSTRAINT fk_pvid_prop FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- ===== 014_add_property_sold.sql =====
-- 012_add_property_sold.sql
-- Only needed if you ALREADY ran 011 before the sold feature was added.
-- (Fresh installs of 011 already include these columns — running this would error,
--  which is fine to ignore in that case.)

ALTER TABLE properties
    ADD COLUMN is_sold TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN sold_at TIMESTAMP NULL AFTER is_sold;

ALTER TABLE properties ADD INDEX idx_prop_sold (is_sold);

-- ===== 015_create_courses_table.sql =====
-- databases/migrations/015_create_courses_table.sql

CREATE TABLE IF NOT EXISTS courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    abbreviation VARCHAR(10) NOT NULL UNIQUE,        -- e.g. "PY" for Python. Set manually by admin, never auto-derived.
    cover_image VARCHAR(255) DEFAULT NULL,
    description TEXT,
    overview TEXT,
    duration_months TINYINT UNSIGNED NOT NULL,
    weeks_of_study SMALLINT UNSIGNED DEFAULT NULL,
    hours_of_study SMALLINT UNSIGNED DEFAULT NULL,
    course_fee DECIMAL(10,2) NOT NULL,               -- enforced >= 16000 in application code (MySQL CHECK support is inconsistent pre-8.0.16)
    last_sequence INT UNSIGNED NOT NULL DEFAULT 0,    -- incremented atomically per registration; never recalculated from COUNT()
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_active (is_active)
);

-- Add the two roles this system needs beyond the existing superadmin/admin/user.
INSERT INTO roles (role_name) VALUES ('tutor'), ('student')
ON DUPLICATE KEY UPDATE role_name = role_name;

-- account_status drives the admin's suspend/unlock feature. Nothing else reads or writes it
-- except Middleware::requireAuth(), so behaviour stays in exactly one place.
-- NOTE: run this once. "IF NOT EXISTS" on ADD COLUMN needs MySQL 8.0.29+/recent MariaDB and
-- many local stacks (Ampps included) ship older — so if you re-run this migration by accident,
-- MySQL will throw "Duplicate column name", not silently skip it. That's expected, not a bug.
ALTER TABLE users
    ADD COLUMN account_status ENUM('active','suspended') NOT NULL DEFAULT 'active' AFTER is_active;

-- ===== 016_create_registrations_payments_tables.sql =====
-- databases/migrations/016_create_registrations_payments_tables.sql

CREATE TABLE IF NOT EXISTS registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_no VARCHAR(30) DEFAULT NULL,        -- e.g. DH-PY01/2026. Assigned only on approval, not at submission.

    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,

    course_id INT NOT NULL,
    study_mode ENUM('physical','online') NOT NULL,
    id_document_path VARCHAR(255) NOT NULL,           -- PDF: National ID or result slip

    -- Snapshots taken at submission time. Never re-read from courses table later, so a course
    -- price change next term doesn't retroactively alter what an already-submitted student owes.
    registration_fee DECIMAL(10,2) NOT NULL,
    course_fee DECIMAL(10,2) NOT NULL,
    payment_plan ENUM('monthly','3months','full') NOT NULL,
    installment_count TINYINT UNSIGNED NOT NULL,
    installment_amount DECIMAL(10,2) NOT NULL,        -- the regular installment; last one absorbs rounding remainder

    payment_reference VARCHAR(150) NOT NULL,           -- name/number/M-Pesa code the student typed in, for the admin to manually verify
    possible_returning_student BOOLEAN NOT NULL DEFAULT FALSE, -- flagged if email/phone matches a prior approved registration
    registration_fee_waived BOOLEAN NOT NULL DEFAULT FALSE,    -- admin sets this explicitly on approval; never automatic

    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    user_id INT DEFAULT NULL,                          -- linked once approved and the student account is created

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_phone (phone)
);

CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payer_reference VARCHAR(150) NOT NULL,   -- name/number the student says they paid with; admin cross-checks manually
    recorded_by INT NOT NULL,                -- admin who logged it
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_registration (registration_id)
);

-- ===== 017_student_portal_payments.sql =====
-- databases/migrations/017_student_portal_payments.sql
-- Extends registrations and payments for student portal locking, receipts, and payment workflow.


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

-- ===== 018_store_orders_minimal.sql =====
-- databases/migrations/018_store_orders_minimal.sql
-- Optional: creates store order tables for the admin orders badge / store module.
-- Safe to run on registration_system when store_orders is missing (uses IF NOT EXISTS).
-- Requires users table from migration 001.


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

-- ===== 019_lms_tutors_curriculum.sql =====
-- databases/migrations/019_lms_tutors_curriculum.sql
-- Tutors, course assignments, curriculum (units/sub-units/topics), tests, projects, submissions, progress.


CREATE TABLE IF NOT EXISTS tutors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    bio TEXT,
    photo_path VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    credentials_sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS course_tutors (
    course_id INT NOT NULL,
    tutor_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id, tutor_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES tutors(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS curriculum_units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_course_sort (course_id, sort_order)
);

CREATE TABLE IF NOT EXISTS curriculum_sub_units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unit_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES curriculum_units(id) ON DELETE CASCADE,
    INDEX idx_unit_sort (unit_id, sort_order)
);

CREATE TABLE IF NOT EXISTS curriculum_topics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sub_unit_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sub_unit_id) REFERENCES curriculum_sub_units(id) ON DELETE CASCADE,
    INDEX idx_subunit_sort (sub_unit_id, sort_order)
);

CREATE TABLE IF NOT EXISTS unit_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unit_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    instructions TEXT,
    max_marks DECIMAL(6,2) NOT NULL DEFAULT 100,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES curriculum_units(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS unit_projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unit_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    instructions TEXT,
    max_marks DECIMAL(6,2) NOT NULL DEFAULT 100,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES curriculum_units(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_id INT NOT NULL,
    submission_type ENUM('test','project') NOT NULL,
    test_id INT DEFAULT NULL,
    project_id INT DEFAULT NULL,
    answer_text TEXT,
    file_path VARCHAR(255) DEFAULT NULL,
    status ENUM('submitted','graded') NOT NULL DEFAULT 'submitted',
    marks DECIMAL(6,2) DEFAULT NULL,
    feedback TEXT,
    graded_by INT DEFAULT NULL,
    graded_at TIMESTAMP NULL DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES unit_tests(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES unit_projects(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reg (registration_id),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS student_unit_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    registration_id INT NOT NULL,
    unit_id INT NOT NULL,
    is_unlocked TINYINT(1) NOT NULL DEFAULT 0,
    test_completed TINYINT(1) NOT NULL DEFAULT 0,
    project_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_reg_unit (registration_id, unit_id),
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES curriculum_units(id) ON DELETE CASCADE
);

-- ===== 020_registration_referrals.sql =====
-- databases/migrations/020_registration_referrals.sql
-- Optional referral when a student shares their course link.

ALTER TABLE registrations
    ADD COLUMN referral_registration_no VARCHAR(30) NULL DEFAULT NULL
        COMMENT 'registration_no of the student who shared the link'
        AFTER payment_reference;

CREATE INDEX idx_referral_reg_no ON registrations (referral_registration_no);

