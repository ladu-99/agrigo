-- ============================================================
-- AgriConnect - South Sudan Agricultural Platform
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS agriconnect_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agriconnect_db;

-- ============================================================
-- Locations: South Sudan states -> counties -> payams hierarchy
-- ============================================================
CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('state', 'county', 'payam') NOT NULL DEFAULT 'state',
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Specializations: areas of agricultural expertise
-- ============================================================
CREATE TABLE specializations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Users: all system users (admin, extension_officer, farmer, cooperative_leader)
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'extension_officer', 'farmer', 'cooperative_leader') NOT NULL DEFAULT 'farmer',
    location_id INT DEFAULT NULL,
    status ENUM('active', 'inactive', 'suspended', 'pending') NOT NULL DEFAULT 'active',
    profile_photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Extension Officers: linked to user, location, and specialization
-- ============================================================
CREATE TABLE extension_officers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    location_id INT NOT NULL,
    specialization_id INT DEFAULT NULL,
    assigned_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Farmers: linked to user and assigned extension officer
-- ============================================================
CREATE TABLE farmers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    location_id INT NOT NULL,
    farm_size DECIMAL(10,2) DEFAULT NULL,
    farm_size_unit ENUM('acres', 'hectares', 'square_meters') DEFAULT 'acres',
    farming_type VARCHAR(255) DEFAULT NULL,
    assigned_extension_officer_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_extension_officer_id) REFERENCES extension_officers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Cooperatives: group accounts for illiterate farmers
-- ============================================================
CREATE TABLE cooperatives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    location_id INT NOT NULL,
    leader_id INT NOT NULL,
    extension_officer_id INT DEFAULT NULL,
    members_count INT DEFAULT 0,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (extension_officer_id) REFERENCES extension_officers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Cooperative Members: farmers belonging to a cooperative
-- ============================================================
CREATE TABLE cooperative_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cooperative_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('leader', 'deputy', 'treasurer', 'secretary', 'member') NOT NULL DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cooperative_id) REFERENCES cooperatives(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_coop_member (cooperative_id, user_id)
) ENGINE=InnoDB;

-- ============================================================
-- Resources: learning materials uploaded by extension officers
-- ============================================================
CREATE TABLE resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('video', 'document') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_by INT NOT NULL,
    category VARCHAR(255) DEFAULT NULL,
    target_location_id INT DEFAULT NULL,
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES extension_officers(id) ON DELETE CASCADE,
    FOREIGN KEY (target_location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Announcements: posted by extension officers for farmers
-- ============================================================
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    target_audience ENUM('all', 'farmers', 'cooperatives', 'specific_location') NOT NULL DEFAULT 'all',
    created_by INT NOT NULL,
    location_id INT DEFAULT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES extension_officers(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Market Listings: agricultural products added by extension officers
-- ============================================================
CREATE TABLE market_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    description TEXT,
    quantity DECIMAL(12,2) NOT NULL,
    quantity_unit ENUM('kg', 'bag', 'crate', 'ton', 'piece', 'litre', 'bunch', 'sack') NOT NULL DEFAULT 'kg',
    price_ssp DECIMAL(12,2) NOT NULL COMMENT 'Price in South Sudanese Pound',
    product_photo VARCHAR(500) DEFAULT NULL,
    product_type ENUM('crop', 'livestock', 'dairy', 'fish', 'vegetable', 'fruit', 'grain', 'other') NOT NULL DEFAULT 'crop',
    seller_type ENUM('farmer', 'cooperative') NOT NULL DEFAULT 'farmer',
    seller_id INT DEFAULT NULL,
    location_id INT NOT NULL,
    status ENUM('active', 'sold', 'removed') NOT NULL DEFAULT 'active',
    added_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES extension_officers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Product Inquiries: buyer requests submitted from the public
-- marketplace, received by the extension officer who listed the product.
-- ============================================================
CREATE TABLE product_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    buyer_name VARCHAR(255) NOT NULL,
    buyer_phone VARCHAR(50) NOT NULL,
    buyer_email VARCHAR(255) DEFAULT NULL,
    quantity_wanted DECIMAL(12,2) DEFAULT NULL,
    message TEXT,
    status ENUM('new', 'contacted', 'completed', 'closed') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES market_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_inquiries_listing ON product_inquiries(listing_id);
CREATE INDEX idx_inquiries_status ON product_inquiries(status);

-- ============================================================
-- Contact Messages, Officer Reports, and system-wide Trash
-- ============================================================
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'archived') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE officer_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    extension_officer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    report_type ENUM('field', 'market', 'activity', 'sales', 'other') NOT NULL DEFAULT 'field',
    period_start DATE DEFAULT NULL,
    period_end DATE DEFAULT NULL,
    description TEXT NOT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    status ENUM('new', 'reviewed', 'archived') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (extension_officer_id) REFERENCES extension_officers(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE INDEX idx_reports_officer ON officer_reports(extension_officer_id);
CREATE INDEX idx_reports_status ON officer_reports(status);

CREATE TABLE trash (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(120) NOT NULL,
    source_table VARCHAR(80) NOT NULL,
    entity_id INT NOT NULL,
    label VARCHAR(255) DEFAULT NULL,
    snapshot LONGTEXT NOT NULL,
    deleted_by INT DEFAULT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    restored_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB;
CREATE INDEX idx_trash_table ON trash(source_table);

-- ============================================================
-- Opportunities: from government, NGOs, investors
-- ============================================================
CREATE TABLE opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    source ENUM('government', 'ngo', 'investor', 'other') NOT NULL DEFAULT 'ngo',
    type ENUM('funding', 'training', 'grant', 'partnership', 'scholarship', 'other') NOT NULL DEFAULT 'funding',
    deadline DATE DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    contact_info TEXT,
    status ENUM('active', 'closed', 'expired') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Success Stories: farmer stories approved by admin
-- ============================================================
CREATE TABLE success_stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    farmer_name VARCHAR(255) NOT NULL,
    location_id INT DEFAULT NULL,
    image_path VARCHAR(500) DEFAULT NULL,
    submitted_by INT DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Agricultural Fields: farm fields and activities around the country
-- ============================================================
CREATE TABLE agricultural_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location_id INT NOT NULL,
    size DECIMAL(10,2) DEFAULT NULL,
    size_unit ENUM('acres', 'hectares', 'square_meters') DEFAULT 'acres',
    crop_type VARCHAR(255) DEFAULT NULL,
    activity_description TEXT,
    owner_type ENUM('farmer', 'cooperative') DEFAULT 'farmer',
    owner_id INT DEFAULT NULL,
    images TEXT COMMENT 'Comma-separated image paths',
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Market Trends: commodity prices updated by extension officers
-- ============================================================
CREATE TABLE market_trends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commodity VARCHAR(255) NOT NULL,
    price DECIMAL(12,2) NOT NULL COMMENT 'Price in SSP',
    unit ENUM('kg', 'bag', 'crate', 'ton', 'piece', 'litre') NOT NULL DEFAULT 'kg',
    location_id INT NOT NULL,
    trend ENUM('up', 'down', 'stable') NOT NULL DEFAULT 'stable',
    recorded_date DATE NOT NULL,
    updated_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES extension_officers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Notifications: system notifications for users
-- ============================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('announcement', 'resource', 'market', 'system', 'cooperative') NOT NULL DEFAULT 'system',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Indexes for performance
-- ============================================================
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_location ON users(location_id);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_farmers_extension ON farmers(assigned_extension_officer_id);
CREATE INDEX idx_coop_members ON cooperative_members(cooperative_id, user_id);
CREATE INDEX idx_resources_location ON resources(target_location_id);
CREATE INDEX idx_announcements_location ON announcements(location_id);
CREATE INDEX idx_market_listings_location ON market_listings(location_id);
CREATE INDEX idx_market_listings_status ON market_listings(status);
CREATE INDEX idx_market_trends_location ON market_trends(location_id);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX idx_locations_parent ON locations(parent_id);
CREATE INDEX idx_locations_type ON locations(type);
