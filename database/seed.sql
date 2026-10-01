-- ============================================================
-- AgriConnect - Seed Data for Testing
-- ============================================================

USE agriconnect_db;

-- ============================================================
-- South Sudan States
-- ============================================================
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

-- ============================================================
-- Counties (sample for Central Equatoria - id=1)
-- ============================================================
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

-- ============================================================
-- Payams (sample for Juba County - id=11)
-- ============================================================
INSERT INTO locations (name, type, parent_id) VALUES
('Juba Town Payam', 'payam', 11),
('Munuki Payam', 'payam', 11),
('Kator Payam', 'payam', 11),
('Rejaf Payam', 'payam', 11);

-- ============================================================
-- Specializations
-- ============================================================
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
-- Admin User (password: admin123)
-- ============================================================
INSERT INTO users (name, email, phone, password_hash, role, status) VALUES
('System Admin', 'admin@agriconnect.ss', '+211912345678',
 '$2b$10$XE1MlTe1Jkk0vwy0PTHstu0HbtAPUXAf9jpYb6yBh3gzLhTFhJeMq', 'admin', 'active');

-- ============================================================
-- Extension Officers
-- ============================================================
-- Officer 1: Juba, Crop Production (password: officer123)
INSERT INTO users (name, email, phone, password_hash, role, location_id, status) VALUES
('James Okello', 'james.okello@agriconnect.ss', '+211923456789',
 '$2b$10$/.bb.4VvQ03IgObgztrykuZsY9gGsay8XYbzYsEDQz9TZCCihHTdK', 'extension_officer', 11, 'active');

-- Officer 2: Bor, Livestock (password: officer123)
INSERT INTO users (name, email, phone, password_hash, role, location_id, status) VALUES
('Mary Akuien', 'mary.akuien@agriconnect.ss', '+211934567890',
 '$2b$10$/.bb.4VvQ03IgObgztrykuZsY9gGsay8XYbzYsEDQz9TZCCihHTdK', 'extension_officer', 17, 'active');

-- Officer 3: Yei, Horticulture (password: officer123)
INSERT INTO users (name, email, phone, password_hash, role, location_id, status) VALUES
('Peter Lual', 'peter.lual@agriconnect.ss', '+211945678901',
 '$2b$10$/.bb.4VvQ03IgObgztrykuZsY9gGsay8XYbzYsEDQz9TZCCihHTdK', 'extension_officer', 16, 'active');

INSERT INTO extension_officers (user_id, location_id, specialization_id, assigned_by) VALUES
(2, 11, 1, 1),
(3, 17, 2, 1),
(4, 16, 4, 1);

-- ============================================================
-- Farmers (password: farmer123)
-- ============================================================
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

-- ============================================================
-- Cooperative Leader (password: coop123)
-- ============================================================
INSERT INTO users (name, email, phone, password_hash, role, location_id, status) VALUES
('Taban Deng', 'taban.deng@gmail.com', '+211990123456',
 '$2b$10$pyRjECDYUU3LimiH4Y30ge7Bt6gfW1IOHJqlQAht4/vuROcjaD6w2', 'cooperative_leader', 11, 'active');

-- ============================================================
-- Cooperative
-- ============================================================
INSERT INTO cooperatives (name, description, location_id, leader_id, extension_officer_id, members_count) VALUES
('Juba Farmers Cooperative', 'A cooperative of smallholder farmers in Juba County focused on maize and sorghum production', 11, 9, 1, 3);

INSERT INTO cooperative_members (cooperative_id, user_id, role) VALUES
(1, 9, 'leader'),
(1, 5, 'member'),
(1, 6, 'member');

-- ============================================================
-- Sample Resources
-- ============================================================
INSERT INTO resources (title, description, type, file_path, uploaded_by, category, target_location_id) VALUES
('Best Practices for Maize Cultivation', 'A comprehensive guide on maize planting, spacing, fertilization, and harvesting techniques suitable for South Sudan', 'document', 'documents/maize_guide.pdf', 1, 'Crop Production', 11),
('Livestock Vaccination Schedule', 'Recommended vaccination schedule for cattle, goats, and sheep in South Sudan', 'document', 'documents/vaccination_schedule.pdf', 2, 'Livestock', 17),
('Introduction to Drip Irrigation', 'Video tutorial on setting up and maintaining a drip irrigation system for small farms', 'video', 'resources/drip_irrigation.mp4', 1, 'Irrigation', 11),
('Post-Harvest Grain Storage', 'Techniques for proper grain storage to prevent losses from pests and moisture', 'document', 'documents/grain_storage.pdf', 1, 'Storage', NULL);

-- ============================================================
-- Sample Announcements
-- ============================================================
INSERT INTO announcements (title, content, target_audience, created_by, location_id, priority) VALUES
('Maize Planting Season Alert', 'The rainy season is approaching. Farmers in Juba County should begin land preparation for maize planting by mid-March. Contact your extension officer for seeds and guidance.', 'farmers', 1, 11, 'high'),
('Livestock Vaccination Campaign', 'A free vaccination campaign for cattle against East Coast Fever will be conducted in Bor County starting next week. All cattle owners should participate.', 'all', 2, 17, 'urgent'),
('New Market Prices Available', 'Updated market prices for sorghum, maize, and groundnuts are now available. Check the marketplace section for current prices in your area.', 'all', 1, 11, 'medium');

-- ============================================================
-- Market Listings
-- No demo/static products are seeded. Listings shown in the public
-- marketplace come only from products added by extension officers.
-- ============================================================

-- ============================================================
-- Sample Opportunities
-- ============================================================
INSERT INTO opportunities (title, description, source, type, deadline, contact_info, status) VALUES
('FAO Seed Support Program', 'The Food and Agriculture Organization is providing free seeds and farming inputs to smallholder farmers in South Sudan. Eligible farmers will receive maize, sorghum, and groundnut seeds for the planting season.', 'ngo', 'funding', '2026-12-31', 'FAO South Sudan: fao.ss@fao.org, +211-123-456-789', 'active'),
('Youth Agribusiness Training', 'A 3-month training program for young people interested in starting agricultural businesses. Covers business planning, financial management, and market access.', 'ngo', 'training', '2026-10-30', 'Ministry of Agriculture: info@moa.gov.ss', 'active'),
('Agricultural Development Grant', 'The World Bank is offering grants up to $50,000 for innovative agricultural projects in South Sudan. Applications open for cooperatives and farmer groups.', 'investor', 'grant', '2026-11-15', 'World Bank Juba Office: grants@worldbank.org', 'active'),
('Government Irrigation Support', 'The Ministry of Agriculture provides subsidized irrigation equipment and technical support for farmers interested in year-round crop production.', 'government', 'funding', '2027-03-01', 'Ministry of Agriculture: irrigation@moa.gov.ss', 'active');

-- ============================================================
-- Sample Success Stories
-- ============================================================
INSERT INTO success_stories (title, content, farmer_name, location_id, approved_by, status) VALUES
('From Subsistence to Commercial Farming', 'Deng Atem started with a small 2-acre plot growing maize for his family. Through the AgriConnect platform, he connected with extension officer James Okello who provided training on modern farming techniques. Today, Deng farms 5 acres and sells surplus grain to the local market, earning enough to send his children to school.', 'Deng Atem', 11, 1, 'approved'),
('Cooperative Strength: Juba Farmers United', 'Five smallholder farmers in Juba County formed a cooperative to pool their resources. With guidance from their extension officer, they collectively negotiated better prices for their sorghum harvest and accessed a government seed program. Their cooperative now has 25 members.', 'Taban Deng', 11, 1, 'approved');

-- ============================================================
-- Sample Agricultural Fields
-- ============================================================
INSERT INTO agricultural_fields (name, location_id, size, size_unit, crop_type, activity_description, owner_type, owner_id, status) VALUES
('Deng Atem Maize Farm', 11, 5.0, 'acres', 'Maize', 'Active maize cultivation using improved seed varieties. Land was prepared in March, planting completed in April.', 'farmer', 5, 'active'),
('Juba Community Vegetable Garden', 11, 1.5, 'acres', 'Mixed Vegetables', 'Community garden growing tomatoes, peppers, onions, and cabbage. Uses drip irrigation system.', 'cooperative', 1, 'active'),
('Bor County Cattle Grazing Land', 17, 50.0, 'acres', 'Pasture', 'Communal grazing land managed by the Bor livestock cooperative. Rotational grazing system in place.', 'cooperative', NULL, 'active');

-- ============================================================
-- Sample Market Trends
-- ============================================================
INSERT INTO market_trends (commodity, price, unit, location_id, trend, recorded_date, updated_by) VALUES
('Maize', 1500.00, 'kg', 11, 'up', '2026-09-01', 1),
('Sorghum', 1200.00, 'kg', 11, 'stable', '2026-09-01', 1),
('Groundnuts', 3000.00, 'kg', 11, 'down', '2026-09-01', 1),
('Cattle', 350000.00, 'piece', 17, 'up', '2026-09-01', 2),
('Tomatoes', 2500.00, 'crate', 16, 'stable', '2026-09-01', 3),
('Fresh Milk', 800.00, 'litre', 17, 'up', '2026-09-01', 2);

-- ============================================================
-- Sample Notifications
-- ============================================================
INSERT INTO notifications (user_id, title, message, type) VALUES
(5, 'New Announcement', 'Maize Planting Season Alert: The rainy season is approaching. Begin land preparation by mid-March.', 'announcement'),
(5, 'New Resource Available', 'Best Practices for Maize Cultivation guide has been uploaded by your extension officer.', 'resource'),
(7, 'Livestock Vaccination Campaign', 'Free vaccination campaign for cattle starting next week in Bor County.', 'announcement'),
(6, 'Market Update', 'New market prices for maize and sorghum are now available in your area.', 'market');
