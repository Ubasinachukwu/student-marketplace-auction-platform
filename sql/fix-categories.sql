-- marketplace/sql/fix-categories.sql
-- Fixes subcategory parent_id mappings that were attached to wrong parents.

USE `marketplace_db`;

-- Books: Fiction (57), Magazines (59) → should be under Books (4)
UPDATE categories SET parent_id = 4 WHERE id = 57;
UPDATE categories SET parent_id = 4 WHERE id = 59;

-- Home & Garden: Decor (60), Garden (61) → under Home & Garden (9)
UPDATE categories SET parent_id = 9 WHERE id = 60;
UPDATE categories SET parent_id = 9 WHERE id = 61;

-- Toys: Board Games (62), Video Games (63), Outdoor Toys (64) → under Toys (8)
UPDATE categories SET parent_id = 8 WHERE id = 62;
UPDATE categories SET parent_id = 8 WHERE id = 63;
UPDATE categories SET parent_id = 8 WHERE id = 64;

-- Sports: Fitness (65), Camping (66), Team Sports (67) → under Sports (7)
UPDATE categories SET parent_id = 7 WHERE id = 65;
UPDATE categories SET parent_id = 7 WHERE id = 66;
UPDATE categories SET parent_id = 7 WHERE id = 67;

-- Collectibles: Art (68), Antiques (69) → under Collectibles (5)
UPDATE categories SET parent_id = 5 WHERE id = 68;
UPDATE categories SET parent_id = 5 WHERE id = 69;

-- Vehicles: Car Parts (70) → under Vehicles (6); Tools (71) → under Home & Garden (9)
UPDATE categories SET parent_id = 6 WHERE id = 70;
UPDATE categories SET parent_id = 9 WHERE id = 71;

-- Health & Beauty: Skincare (72), Makeup (73), Wellness (74) → under Health & Beauty (49)
UPDATE categories SET parent_id = 49 WHERE id = 72;
UPDATE categories SET parent_id = 49 WHERE id = 73;
UPDATE categories SET parent_id = 49 WHERE id = 74;

-- Pet Supplies: Dog (75), Cat (76), Small Pet (77) → under Pet Supplies (50)
UPDATE categories SET parent_id = 50 WHERE id = 75;
UPDATE categories SET parent_id = 50 WHERE id = 76;
UPDATE categories SET parent_id = 50 WHERE id = 77;