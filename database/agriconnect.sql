
-- Locations: South Sudan states -> counties -> payams hierarchy
CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('state', 'county', 'payam') NOT NULL DEFAULT 'state',
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Specializations: areas of agricultural expertise
CREATE TABLE specializations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Users: all system users (admin, extension_officer, farmer, cooperative_leader)
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

-- Extension Officers: linked to user, location, and specialization
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

-- Farmers: linked to user and assigned extension officer
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

-- Cooperatives: group accounts for illiterate farmers
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

-- Cooperative Members: farmers belonging to a cooperative
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

-- Resources: learning materials uploaded by extension officers
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

-- Announcements: posted by extension officers for farmers
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

-- Market Listings: agricultural products added by extension officers
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

-- Marketplace Sales: sales records tracked by extension officers
CREATE TABLE marketplace_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    extension_officer_id INT NOT NULL,
    buyer_name VARCHAR(255) DEFAULT NULL,
    buyer_contact VARCHAR(100) DEFAULT NULL,
    quantity_sold DECIMAL(12,2) NOT NULL,
    sale_price DECIMAL(12,2) NOT NULL COMMENT 'Price per unit at time of sale in SSP',
    total_amount DECIMAL(14,2) NOT NULL COMMENT 'quantity_sold * sale_price',
    notes TEXT,
    sale_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES market_listings(id) ON DELETE CASCADE,
    FOREIGN KEY (extension_officer_id) REFERENCES extension_officers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_sales_listing ON marketplace_sales(listing_id);
CREATE INDEX idx_sales_officer ON marketplace_sales(extension_officer_id);
CREATE INDEX idx_sales_date ON marketplace_sales(sale_date);

-- Product Inquiries: buyer requests submitted from the public marketplace,
-- received by the extension officer who listed the product.
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

-- Public contact messages sent from the website to the admin.
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

-- Reports forwarded by extension officers to the admin.
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

-- System-wide Trash / Recycle Bin: JSON snapshot of every deleted record.
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

-- Opportunities: from government, NGOs, investors
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

-- Success Stories: farmer stories approved by admin
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

-- Agricultural Fields: farm fields and activities around the country
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

-- Market Trends: commodity prices updated by extension officers
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

-- Notifications: system notifications for users
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
-- PERFORMANCE INDEXES
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

-- ============================================================
-- SEED DATA
-- ============================================================

-- South Sudan States (10 states)
INSERT INTO locations (name, type) VALUES
('Central Equatoria', 'state'),
('Eastern Equatoria', 'state'),
('Western Equatoria', 'state'),
('Jonglei', 'state'),
('Unity', 'state'),
('Upper Nile', 'state'),
('Lakes', 'state'),
('Warrap', 'state'),
('Northern Bahr el Ghazal', 'state'),
('Western Bahr el Ghazal', 'state');

-- Counties (sample for Central Equatoria - id=1)
INSERT INTO locations (name, type, parent_id) VALUES
('Juba County', 'county', 1),
('Kajo-Keji County', 'county', 1),
('Terekeka County', 'county', 1),
('Lainya County', 'county', 1),
('Morobo County', 'county', 1),
('Yei River County', 'county', 1);

-- Counties for Jonglei (id=4)
INSERT INTO locations (name, type, parent_id) VALUES
('Bor County', 'county', 4),
('Twic East County', 'county', 4),
('Akobo County', 'county', 4);

-- Counties for Unity (id=5)
INSERT INTO locations (name, type, parent_id) VALUES
('Bentiu County', 'county', 5),
('Rubkona County', 'county', 5),
('Mayom County', 'county', 5);

-- Payams (sample for Juba County - id=11)
INSERT INTO locations (name, type, parent_id) VALUES
('Juba Town Payam', 'payam', 11),
('Munuki Payam', 'payam', 11),
('Kator Payam', 'payam', 11),
('Rejaf Payam', 'payam', 11);

-- Specializations
INSERT INTO specializations (name, description) VALUES
('Crop Production', 'Expertise in crop cultivation, soil management, and harvest techniques'),
('Livestock Management', 'Animal husbandry, veterinary basics, and livestock breeding'),
('Fisheries', 'Fish farming, aquaculture, and freshwater fisheries management'),
('Horticulture', 'Fruit and vegetable cultivation, greenhouse management'),
('Dairy Farming', 'Milk production, dairy cattle management, and processing'),
('Agribusiness', 'Farm business management, marketing, and value chain development'),
('Irrigation & Water Management', 'Irrigation systems, water harvesting, and drainage'),
('Post-Harvest & Storage', 'Grain storage, food preservation, and post-harvest handling'),
('Pest & Disease Management', 'Integrated pest management and crop disease control'),
('Soil & Land Management', 'Soil conservation, land preparation, and fertility management');

-- ============================================================
-- TEST USER ACCOUNTS
-- ============================================================
-- All test passwords are the same bcrypt hash for: password123
-- You can change them after first login.
-- ============================================================

-- Admin (password: admin123)
INSERT INTO users (name, email, phone, password_hash, role, status) VALUES
('System Admin', 'admin@agriconnect.ss', '+211912345678',
 '$2b$10$XE1MlTe1Jkk0vwy0PTHstu0HbtAPUXAf9jpYb6yBh3gzLhTFhJeMq', 'admin', 'active');

-- Extension Officers (password: officer123)
INSERT INTO users (name, email, phone, password_hash, role, location_id, status) VALUES
('James Okello', 'james.okello@agriconnect.ss', '+211923456789',
 '$2b$10$/.bb.4VvQ03IgObgztrykuZsY9gGsay8XYbzYsEDQz9TZCCihHTdK', 'extension_officer', 11, 'active'),
('Mary Akuien', 'mary.akuien@agriconnect.ss', '+211934567890',
 '$2b$10$/.bb.4VvQ03IgObgztrykuZsY9gGsay8XYbzYsEDQz9TZCCihHTdK', 'extension_officer', 17, 'active'),
('Peter Lual', 'peter.lual@agriconnect.ss', '+211945678901',
 '$2b$10$/.bb.4VvQ03IgObgztrykuZsY9gGsay8XYbzYsEDQz9TZCCihHTdK', 'extension_officer', 16, 'active');

INSERT INTO extension_officers (user_id, location_id, specialization_id, assigned_by) VALUES
(2, 11, 1, 1),
(3, 17, 2, 1),
(4, 16, 4, 1);

-- Farmers (password: farmer123)
INSERT INTO users (name, email, phone, password_hash, role, location_id, status) VALUES
('Deng Atem', 'deng.atem@gmail.com', '+211956789012',
 '$2b$10$3h2Y03SMXS4lWmlLC17n0eF.j0pEyf828iYMnLROU92bfdFP3LLV.', 'farmer', 11, 'active'),
('Sarah Nyawal', 'sarah.nyawal@gmail.com', '+211967890123',
 '$2b$10$3h2Y03SMXS4lWmlLC17n0eF.j0pEyf828iYMnLROU92bfdFP3LLV.', 'farmer', 11, 'active'),
('Joseph Ladu', 'joseph.ladu@gmail.com', '+211978901234',
 '$2b$10$3h2Y03SMXS4lWmlLC17n0eF.j0pEyf828iYMnLROU92bfdFP3LLV.', 'farmer', 17, 'active'),
('Grace Akot', 'grace.akot@gmail.com', '+211989012345',
 '$2b$10$3h2Y03SMXS4lWmlLC17n0eF.j0pEyf828iYMnLROU92bfdFP3LLV.', 'farmer', 16, 'active');

INSERT INTO farmers (user_id, location_id, farm_size, farm_size_unit, farming_type, assigned_extension_officer_id) VALUES
(5, 11, 5.0, 'acres', 'Mixed crops (maize, sorghum, groundnuts)', 1),
(6, 11, 3.5, 'acres', 'Vegetable farming', 1),
(7, 17, 10.0, 'acres', 'Cattle rearing', 2),
(8, 16, 2.0, 'acres', 'Horticulture (tomatoes, peppers)', 3);

-- Cooperative Leader (password: coop123)
INSERT INTO users (name, email, phone, password_hash, role, location_id, status) VALUES
('Taban Deng', 'taban.deng@gmail.com', '+211990123456',
 '$2b$10$pyRjECDYUU3LimiH4Y30ge7Bt6gfW1IOHJqlQAht4/vuROcjaD6w2', 'cooperative_leader', 11, 'active');

-- Cooperative
INSERT INTO cooperatives (name, description, location_id, leader_id, extension_officer_id, members_count) VALUES
('Juba Farmers Cooperative', 'A cooperative of smallholder farmers in Juba County focused on maize and sorghum production', 11, 9, 1, 3);

INSERT INTO cooperative_members (cooperative_id, user_id, role) VALUES
(1, 9, 'leader'),
(1, 5, 'member'),
(1, 6, 'member');

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Resources
INSERT INTO resources (title, description, type, file_path, uploaded_by, category, target_location_id) VALUES
('Best Practices for Maize Cultivation', 'A comprehensive guide on maize planting, spacing, fertilization, and harvesting techniques suitable for South Sudan', 'document', 'documents/maize_guide.pdf', 1, 'Crop Production', 11),
('Livestock Vaccination Schedule', 'Recommended vaccination schedule for cattle, goats, and sheep in South Sudan', 'document', 'documents/vaccination_schedule.pdf', 2, 'Livestock', 17),
('Introduction to Drip Irrigation', 'Video tutorial on setting up and maintaining a drip irrigation system for small farms', 'video', 'resources/drip_irrigation.mp4', 1, 'Irrigation', 11),
('Post-Harvest Grain Storage', 'Techniques for proper grain storage to prevent losses from pests and moisture', 'document', 'documents/grain_storage.pdf', 1, 'Storage', NULL);

-- Announcements
INSERT INTO announcements (title, content, target_audience, created_by, location_id, priority) VALUES
('Maize Planting Season Alert', 'The rainy season is approaching. Farmers in Juba County should begin land preparation for maize planting by mid-March. Contact your extension officer for seeds and guidance.', 'farmers', 1, 11, 'high'),
('Livestock Vaccination Campaign', 'A free vaccination campaign for cattle against East Coast Fever will be conducted in Bor County starting next week. All cattle owners should participate.', 'all', 2, 17, 'urgent'),
('New Market Prices Available', 'Updated market prices for sorghum, maize, and groundnuts are now available. Check the marketplace section for current prices in your area.', 'all', 1, 11, 'medium');

-- Market Listings: no demo/static products seeded. The public marketplace
-- shows only products added by extension officers through the app.
-- (intentionally empty)

-- Opportunities
INSERT INTO opportunities (title, description, source, type, deadline, contact_info, status) VALUES
('FAO Seed Support Program', 'The Food and Agriculture Organization is providing free seeds and farming inputs to smallholder farmers in South Sudan. Eligible farmers will receive maize, sorghum, and groundnut seeds for the planting season.', 'ngo', 'funding', '2026-12-31', 'FAO South Sudan: fao.ss@fao.org, +211-123-456-789', 'active'),
('Youth Agribusiness Training', 'A 3-month training program for young people interested in starting agricultural businesses. Covers business planning, financial management, and market access.', 'ngo', 'training', '2026-10-30', 'Ministry of Agriculture: info@moa.gov.ss', 'active'),
('Agricultural Development Grant', 'The World Bank is offering grants up to $50,000 for innovative agricultural projects in South Sudan. Applications open for cooperatives and farmer groups.', 'investor', 'grant', '2026-11-15', 'World Bank Juba Office: grants@worldbank.org', 'active'),
('Government Irrigation Support', 'The Ministry of Agriculture provides subsidized irrigation equipment and technical support for farmers interested in year-round crop production.', 'government', 'funding', '2027-03-01', 'Ministry of Agriculture: irrigation@moa.gov.ss', 'active');

-- Success Stories
INSERT INTO success_stories (title, content, farmer_name, location_id, approved_by, status) VALUES
('From Subsistence to Commercial Farming', 'Deng Atem started with a small 2-acre plot growing maize for his family. Through the AgriConnect platform, he connected with extension officer James Okello who provided training on modern farming techniques. Today, Deng farms 5 acres and sells surplus grain to the local market, earning enough to send his children to school.', 'Deng Atem', 11, 1, 'approved'),
('Cooperative Strength: Juba Farmers United', 'Five smallholder farmers in Juba County formed a cooperative to pool their resources. With guidance from their extension officer, they collectively negotiated better prices for their sorghum harvest and accessed a government seed program. Their cooperative now has 25 members.', 'Taban Deng', 11, 1, 'approved');

-- Agricultural Fields
INSERT INTO agricultural_fields (name, location_id, size, size_unit, crop_type, activity_description, owner_type, owner_id, status) VALUES
('Deng Atem Maize Farm', 11, 5.0, 'acres', 'Maize', 'Active maize cultivation using improved seed varieties. Land was prepared in March, planting completed in April.', 'farmer', 5, 'active'),
('Juba Community Vegetable Garden', 11, 1.5, 'acres', 'Mixed Vegetables', 'Community garden growing tomatoes, peppers, onions, and cabbage. Uses drip irrigation system.', 'cooperative', 1, 'active'),
('Bor County Cattle Grazing Land', 17, 50.0, 'acres', 'Pasture', 'Communal grazing land managed by the Bor livestock cooperative. Rotational grazing system in place.', 'cooperative', NULL, 'active');

-- Market Trends
INSERT INTO market_trends (commodity, price, unit, location_id, trend, recorded_date, updated_by) VALUES
('Maize', 1500.00, 'kg', 11, 'up', '2026-09-01', 1),
('Sorghum', 1200.00, 'kg', 11, 'stable', '2026-09-01', 1),
('Groundnuts', 3000.00, 'kg', 11, 'down', '2026-09-01', 1),
('Cattle', 350000.00, 'piece', 17, 'up', '2026-09-01', 2),
('Tomatoes', 2500.00, 'crate', 16, 'stable', '2026-09-01', 3),
('Fresh Milk', 800.00, 'litre', 17, 'up', '2026-09-01', 2);

-- Notifications
INSERT INTO notifications (user_id, title, message, type) VALUES
(5, 'New Announcement', 'Maize Planting Season Alert: The rainy season is approaching. Begin land preparation by mid-March.', 'announcement'),
(5, 'New Resource Available', 'Best Practices for Maize Cultivation guide has been uploaded by your extension officer.', 'resource'),
(7, 'Livestock Vaccination Campaign', 'Free vaccination campaign for cattle starting next week in Bor County.', 'announcement'),
(6, 'Market Update', 'New market prices for maize and sorghum are now available in your area.', 'market');

-- ============================================================
-- SETUP COMPLETE
-- ============================================================
-- Database 'agriconnect_db' is ready with all tables and sample data.
--
-- Test Accounts:
--   Admin:              admin@agriconnect.ss        / admin123
--   Extension Officer:  james.okello@agriconnect.ss / officer123
--   Farmer:             deng.atem@gmail.com         / farmer123
--   Cooperative Leader: taban.deng@gmail.com        / coop123
-- ============================================================
