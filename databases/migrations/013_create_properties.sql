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