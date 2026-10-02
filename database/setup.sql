-- ============================================================
-- AgriConnect - Single-file setup for TiDB Cloud / MySQL
-- Paste this ENTIRE file into TiDB's SQL Editor and click Execute.
-- It creates the database, all tables, and all demo/seed data.
-- Explicit IDs are used everywhere so TiDB's AUTO_INCREMENT block
-- allocation cannot break foreign-key references.
-- ============================================================

-- Optional safety: allow the script to drop an existing DB.
DROP DATABASE IF EXISTS agriconnect_db;
CREATE DATABASE agriconnect_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agriconnect_db;

-- Disable FK checks while loading (re-enabled at the end).
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- SCHEMA
-- ============================================================

CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('state', 'county', 'payam') NOT NULL DEFAULT 'state',
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE specializations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

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
-- SEED DATA (explicit IDs to protect FK references)
-- ============================================================

-- South Sudan states (ids 1-10)
INSERT INTO locations (id, name, type) VALUES
(1,'Central Equatoria','state'),
(2,'Eastern Equatoria','state'),
(3,'Western Equatoria','state'),
(4,'Jonglei','state'),
(5,'Unity','state'),
(6,'Upper Nile','state'),
(7,'Lakes','state'),
(8,'Warrap','state'),
(9,'Northern Bahr el Ghazal','state'),
(10,'Western Bahr el Ghazal','state');

-- Counties (Central Equatoria ids 11-16, Jonglei 17-19, Unity 20-22)
INSERT INTO locations (id, name, type, parent_id) VALUES
(11,'Juba County','county',1),
(12,'Kajo-Keji County','county',1),
(13,'Terekeka County','county',1),
(14,'Lainya County','county',1),
(15,'Morobo County','county',1),
(16,'Yei River County','county',1),
(17,'Bor County','county',4),
(18,'Twic East County','county',4),
(19,'Akobo County','county',4),
(20,'Bentiu County','county',5),
(21,'Rubkona County','county',5),
(22,'Mayom County','county',5);

-- Payams (Juba County, ids 23-26)
INSERT INTO locations (id, name, type, parent_id) VALUES
(23,'Juba Town Payam','payam',11),
(24,'Munuki Payam','payam',11),
(25,'Kator Payam','payam',11),
(26,'Rejaf Payam','payam',11);

-- Specializations (ids 1-10)
INSERT INTO specializations (id, name, description) VALUES
(1,'Crop Production','Expertise in crop cultivation, soil management, and harvest techniques'),
(2,'Livestock Management','Animal husbandry, veterinary basics, and livestock breeding'),
(3,'Fisheries','Fish farming, aquaculture, and freshwater fisheries management'),
(4,'Horticulture','Fruit and vegetable cultivation, greenhouse management'),
(5,'Dairy Farming','Milk production, dairy cattle management, and processing'),
(6,'Agribusiness','Farm business management, marketing, and value chain development'),
(7,'Irrigation & Water Management','Irrigation systems, water harvesting, and drainage'),
(8,'Post-Harvest & Storage','Grain storage, food preservation, and post-harvest handling'),
(9,'Pest & Disease Management','Integrated pest management and crop disease control'),
(10,'Soil & Land Management','Soil conservation, land preparation, and fertility management');

-- Users (admin=1, officers=2-4, farmers=5-8, coop leader=9)
-- Passwords: admin123 / officer123 / farmer123 / coop123
INSERT INTO users (id, name, email, phone, password_hash, role, location_id, status) VALUES
(1,'System Admin','admin@agriconnect.ss','+211912345678','$2b$10$XE1MlTe1Jkk0vwy0PTHstu0HbtAPUXAf9jpYb6yBh3gzLhTFhJeMq','admin',NULL,'active'),
(2,'James Okello','james.okello@agriconnect.ss','+211923456789','$2b$10$/.bb.4VvQ03IgObgztrykuZsY9gGsay8XYbzYsEDQz9TZCCihHTdK','extension_officer',11,'active'),
(3,'Mary Akuien','mary.akuien@agriconnect.ss','+211934567890','$2b$10$/.bb.4VvQ03IgObgztrykuZsY9gGsay8XYbzYsEDQz9TZCCihHTdK','extension_officer',17,'active'),
(4,'Peter Lual','peter.lual@agriconnect.ss','+211945678901','$2b$10$/.bb.4VvQ03IgObgztrykuZsY9gGsay8XYbzYsEDQz9TZCCihHTdK','extension_officer',16,'active'),
(5,'Deng Atem','deng.atem@gmail.com','+211956789012','$2b$10$3h2Y03SMXS4lWmlLC17n0eF.j0pEyf828iYMnLROU92bfdFP3LLV.','farmer',11,'active'),
(6,'Sarah Nyawal','sarah.nyawal@gmail.com','+211967890123','$2b$10$3h2Y03SMXS4lWmlLC17n0eF.j0pEyf828iYMnLROU92bfdFP3LLV.','farmer',11,'active'),
(7,'Joseph Ladu','joseph.ladu@gmail.com','+211978901234','$2b$10$3h2Y03SMXS4lWmlLC17n0eF.j0pEyf828iYMnLROU92bfdFP3LLV.','farmer',17,'active'),
(8,'Grace Akot','grace.akot@gmail.com','+211989012345','$2b$10$3h2Y03SMXS4lWmlLC17n0eF.j0pEyf828iYMnLROU92bfdFP3LLV.','farmer',16,'active'),
(9,'Taban Deng','taban.deng@gmail.com','+211990123456','$2b$10$pyRjECDYUU3LimiH4Y30ge7Bt6gfW1IOHJqlQAht4/vuROcjaD6w2','cooperative_leader',11,'active');

-- Extension officers (ids 1-3)
INSERT INTO extension_officers (id, user_id, location_id, specialization_id, assigned_by) VALUES
(1,2,11,1,1),
(2,3,17,2,1),
(3,4,16,4,1);

-- Farmers (ids 1-4)
INSERT INTO farmers (id, user_id, location_id, farm_size, farm_size_unit, farming_type, assigned_extension_officer_id) VALUES
(1,5,11,5.0,'acres','Mixed crops (maize, sorghum, groundnuts)',1),
(2,6,11,3.5,'acres','Vegetable farming',1),
(3,7,17,10.0,'acres','Cattle rearing',2),
(4,8,16,2.0,'acres','Horticulture (tomatoes, peppers)',3);

-- Cooperative + members
INSERT INTO cooperatives (id, name, description, location_id, leader_id, extension_officer_id, members_count) VALUES
(1,'Juba Farmers Cooperative','A cooperative of smallholder farmers in Juba County focused on maize and sorghum production',11,9,1,3);

INSERT INTO cooperative_members (id, cooperative_id, user_id, role) VALUES
(1,1,9,'leader'),
(2,1,5,'member'),
(3,1,6,'member');

-- Resources
INSERT INTO resources (id, title, description, type, file_path, uploaded_by, category, target_location_id) VALUES
(1,'Best Practices for Maize Cultivation','A comprehensive guide on maize planting, spacing, fertilization, and harvesting techniques suitable for South Sudan','document','documents/maize_guide.pdf',1,'Crop Production',11),
(2,'Livestock Vaccination Schedule','Recommended vaccination schedule for cattle, goats, and sheep in South Sudan','document','documents/vaccination_schedule.pdf',2,'Livestock',17),
(3,'Introduction to Drip Irrigation','Video tutorial on setting up and maintaining a drip irrigation system for small farms','video','resources/drip_irrigation.mp4',1,'Irrigation',11),
(4,'Post-Harvest Grain Storage','Techniques for proper grain storage to prevent losses from pests and moisture','document','documents/grain_storage.pdf',1,'Storage',NULL);

-- Announcements
INSERT INTO announcements (id, title, content, target_audience, created_by, location_id, priority) VALUES
(1,'Maize Planting Season Alert','The rainy season is approaching. Farmers in Juba County should begin land preparation for maize planting by mid-March. Contact your extension officer for seeds and guidance.','farmers',1,11,'high'),
(2,'Livestock Vaccination Campaign','A free vaccination campaign for cattle against East Coast Fever will be conducted in Bor County starting next week. All cattle owners should participate.','all',2,17,'urgent'),
(3,'New Market Prices Available','Updated market prices for sorghum, maize, and groundnuts are now available. Check the marketplace section for current prices in your area.','all',1,11,'medium');

-- Opportunities
INSERT INTO opportunities (id, title, description, source, type, deadline, contact_info, status) VALUES
(1,'FAO Seed Support Program','The Food and Agriculture Organization is providing free seeds and farming inputs to smallholder farmers in South Sudan. Eligible farmers will receive maize, sorghum, and groundnut seeds for the planting season.','ngo','funding','2026-12-31','FAO South Sudan: fao.ss@fao.org, +211-123-456-789','active'),
(2,'Youth Agribusiness Training','A 3-month training program for young people interested in starting agricultural businesses. Covers business planning, financial management, and market access.','ngo','training','2026-10-30','Ministry of Agriculture: info@moa.gov.ss','active'),
(3,'Agricultural Development Grant','The World Bank is offering grants up to $50,000 for innovative agricultural projects in South Sudan. Applications open for cooperatives and farmer groups.','investor','grant','2026-11-15','World Bank Juba Office: grants@worldbank.org','active'),
(4,'Government Irrigation Support','The Ministry of Agriculture provides subsidized irrigation equipment and technical support for farmers interested in year-round crop production.','government','funding','2027-03-01','Ministry of Agriculture: irrigation@moa.gov.ss','active');

-- Success Stories
INSERT INTO success_stories (id, title, content, farmer_name, location_id, approved_by, status) VALUES
(1,'From Subsistence to Commercial Farming','Deng Atem started with a small 2-acre plot growing maize for his family. Through the AgriConnect platform, he connected with extension officer James Okello who provided training on modern farming techniques. Today, Deng farms 5 acres and sells surplus grain to the local market, earning enough to send his children to school.','Deng Atem',11,1,'approved'),
(2,'Cooperative Strength: Juba Farmers United','Five smallholder farmers in Juba County formed a cooperative to pool their resources. With guidance from their extension officer, they collectively negotiated better prices for their sorghum harvest and accessed a government seed program. Their cooperative now has 25 members.','Taban Deng',11,1,'approved');

-- Agricultural Fields
INSERT INTO agricultural_fields (id, name, location_id, size, size_unit, crop_type, activity_description, owner_type, owner_id, status) VALUES
(1,'Deng Atem Maize Farm',11,5.0,'acres','Maize','Active maize cultivation using improved seed varieties. Land was prepared in March, planting completed in April.','farmer',5,'active'),
(2,'Juba Community Vegetable Garden',11,1.5,'acres','Mixed Vegetables','Community garden growing tomatoes, peppers, onions, and cabbage. Uses drip irrigation system.','cooperative',1,'active'),
(3,'Bor County Cattle Grazing Land',17,50.0,'acres','Pasture','Communal grazing land managed by the Bor livestock cooperative. Rotational grazing system in place.','cooperative',NULL,'active');

-- Market Trends
INSERT INTO market_trends (id, commodity, price, unit, location_id, trend, recorded_date, updated_by) VALUES
(1,'Maize',1500.00,'kg',11,'up','2026-09-01',1),
(2,'Sorghum',1200.00,'kg',11,'stable','2026-09-01',1),
(3,'Groundnuts',3000.00,'kg',11,'down','2026-09-01',1),
(4,'Cattle',350000.00,'piece',17,'up','2026-09-01',2),
(5,'Tomatoes',2500.00,'crate',16,'stable','2026-09-01',3),
(6,'Fresh Milk',800.00,'litre',17,'up','2026-09-01',2);

-- Notifications
INSERT INTO notifications (id, user_id, title, message, type) VALUES
(1,5,'New Announcement','Maize Planting Season Alert: The rainy season is approaching. Begin land preparation by mid-March.','announcement'),
(2,5,'New Resource Available','Best Practices for Maize Cultivation guide has been uploaded by your extension officer.','resource'),
(3,7,'Livestock Vaccination Campaign','Free vaccination campaign for cattle starting next week in Bor County.','announcement'),
(4,6,'Market Update','New market prices for maize and sorghum are now available in your area.','market');

-- ============================================================
-- Post-load housekeeping
-- ============================================================

-- Re-enable FK checks.
SET FOREIGN_KEY_CHECKS = 1;

-- Push AUTO_INCREMENT past the last seeded id so new rows don't collide.
ALTER TABLE locations           AUTO_INCREMENT = 1000;
ALTER TABLE specializations     AUTO_INCREMENT = 1000;
ALTER TABLE users               AUTO_INCREMENT = 1000;
ALTER TABLE extension_officers  AUTO_INCREMENT = 1000;
ALTER TABLE farmers             AUTO_INCREMENT = 1000;
ALTER TABLE cooperatives        AUTO_INCREMENT = 1000;
ALTER TABLE cooperative_members AUTO_INCREMENT = 1000;
ALTER TABLE resources           AUTO_INCREMENT = 1000;
ALTER TABLE announcements       AUTO_INCREMENT = 1000;
ALTER TABLE opportunities       AUTO_INCREMENT = 1000;
ALTER TABLE success_stories     AUTO_INCREMENT = 1000;
ALTER TABLE agricultural_fields AUTO_INCREMENT = 1000;
ALTER TABLE market_trends       AUTO_INCREMENT = 1000;
ALTER TABLE notifications       AUTO_INCREMENT = 1000;
ALTER TABLE market_listings     AUTO_INCREMENT = 1000;
ALTER TABLE product_inquiries   AUTO_INCREMENT = 1000;
ALTER TABLE contact_messages    AUTO_INCREMENT = 1000;
ALTER TABLE officer_reports     AUTO_INCREMENT = 1000;
ALTER TABLE trash               AUTO_INCREMENT = 1000;

-- Quick verification
SELECT COUNT(*) AS users          FROM users;
SELECT COUNT(*) AS locations      FROM locations;
SELECT COUNT(*) AS farmers        FROM farmers;
SELECT COUNT(*) AS officers       FROM extension_officers;
