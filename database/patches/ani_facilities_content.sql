-- Archon Nell Incorporated (ANI) — facilities & visitor-area content refresh
-- Safe to re-run. Aligns seeded facilities/equipment/visit areas with Marikina HQ,
-- blending plant, and warehouse operations.

-- Rename legacy office-style facilities when still present
UPDATE facilities SET
    name = 'HQ Engineering Conference Room',
    type = 'room',
    location = 'Marikina City Headquarters — 2nd Floor',
    capacity = 12,
    status = 'available'
WHERE name = 'Conference Room A';

UPDATE facilities SET
    name = 'Sales & Technical Briefing Room',
    type = 'room',
    location = 'Marikina City Headquarters — 2nd Floor',
    capacity = 8,
    status = 'available'
WHERE name = 'Conference Room B';

UPDATE facilities SET
    name = 'Field Service Van',
    type = 'vehicle',
    location = 'Site Operations / Motor Pool',
    capacity = 8,
    status = 'available'
WHERE name = 'Company Van 1';

UPDATE facilities SET
    name = 'Training & Safety Hall',
    type = 'venue',
    location = 'Marikina City Headquarters — Ground Floor',
    capacity = 80,
    status = 'available'
WHERE name = 'Auditorium';

-- Add ANI plant/warehouse facilities if missing
INSERT INTO facilities (name, type, location, capacity, status)
SELECT 'Blending Plant Coordination Bay', 'room', 'Production & Blending Facility', 10, 'available'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE name = 'Blending Plant Coordination Bay');

INSERT INTO facilities (name, type, location, capacity, status)
SELECT 'Warehouse Staging Area', 'venue', 'Warehouse & Loading Dock', 25, 'available'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE name = 'Warehouse Staging Area');

INSERT INTO facilities (name, type, location, capacity, status)
SELECT 'HQ Engineering Conference Room', 'room', 'Marikina City Headquarters — 2nd Floor', 12, 'available'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE name = 'HQ Engineering Conference Room');

INSERT INTO facilities (name, type, location, capacity, status)
SELECT 'Sales & Technical Briefing Room', 'room', 'Marikina City Headquarters — 2nd Floor', 8, 'available'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE name = 'Sales & Technical Briefing Room');

INSERT INTO facilities (name, type, location, capacity, status)
SELECT 'Training & Safety Hall', 'venue', 'Marikina City Headquarters — Ground Floor', 80, 'available'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE name = 'Training & Safety Hall');

INSERT INTO facilities (name, type, location, capacity, status)
SELECT 'Field Service Van', 'vehicle', 'Site Operations / Motor Pool', 8, 'available'
WHERE NOT EXISTS (SELECT 1 FROM facilities WHERE name = 'Field Service Van');

-- Equipment refresh
UPDATE equipment_items SET name = 'Safety Briefing Whiteboard', category = 'Safety'
WHERE name = 'Whiteboard Set';

INSERT INTO equipment_items (name, category, quantity_available, status)
SELECT 'Refractory Sample Display Kit', 'Technical', 2, 'available'
WHERE NOT EXISTS (SELECT 1 FROM equipment_items WHERE name = 'Refractory Sample Display Kit');

INSERT INTO equipment_items (name, category, quantity_available, status)
SELECT 'Admixture Sampling Kit', 'Lab', 3, 'available'
WHERE NOT EXISTS (SELECT 1 FROM equipment_items WHERE name = 'Admixture Sampling Kit');

INSERT INTO equipment_items (name, category, quantity_available, status)
SELECT 'Safety Briefing Whiteboard', 'Safety', 6, 'available'
WHERE NOT EXISTS (SELECT 1 FROM equipment_items WHERE name = 'Safety Briefing Whiteboard');

-- Visit areas: rename legacy labels, then ensure ANI set exists
UPDATE visit_areas SET name = 'HQ Lobby / Reception' WHERE name = 'Lobby';
UPDATE visit_areas SET name = 'Head Office Support Area' WHERE name = '1st Floor Offices';
UPDATE visit_areas SET name = 'Sales & Technical Offices' WHERE name = '2nd Floor Conference Rooms';
UPDATE visit_areas SET name = 'Warehouse & Loading Dock' WHERE name = 'Warehouse';

INSERT INTO visit_areas (name)
SELECT 'HQ Lobby / Reception'
WHERE NOT EXISTS (SELECT 1 FROM visit_areas WHERE name = 'HQ Lobby / Reception');

INSERT INTO visit_areas (name)
SELECT 'Head Office Support Area'
WHERE NOT EXISTS (SELECT 1 FROM visit_areas WHERE name = 'Head Office Support Area');

INSERT INTO visit_areas (name)
SELECT 'Sales & Technical Offices'
WHERE NOT EXISTS (SELECT 1 FROM visit_areas WHERE name = 'Sales & Technical Offices');

INSERT INTO visit_areas (name)
SELECT 'Blending Plant Floor'
WHERE NOT EXISTS (SELECT 1 FROM visit_areas WHERE name = 'Blending Plant Floor');

INSERT INTO visit_areas (name)
SELECT 'Warehouse & Loading Dock'
WHERE NOT EXISTS (SELECT 1 FROM visit_areas WHERE name = 'Warehouse & Loading Dock');

INSERT INTO visit_areas (name)
SELECT 'Executive Suite'
WHERE NOT EXISTS (SELECT 1 FROM visit_areas WHERE name = 'Executive Suite');

-- Department label (keep FK integrity by renaming in place)
UPDATE departments SET name = 'Head Office Administration'
WHERE name = 'General Administration';

UPDATE employees SET position = 'Plant & Facilities Manager'
WHERE position = 'Facilities Manager';

UPDATE contract_templates
SET body_text = 'This Non-Disclosure Agreement ("Agreement") is entered into between Archon Nell Incorporated ("the Company") and the Counterparty.\n\n1. Confidential Information\n2. Obligations of Receiving Party\n3. Term and Termination\n4. Governing Law'
WHERE name = 'Standard NDA Template';
