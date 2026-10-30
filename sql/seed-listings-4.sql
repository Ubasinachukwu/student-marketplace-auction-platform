-- marketplace/sql/seed-listings-4.sql
-- 100 listings (ids 1301-1400)

USE `marketplace_db`;

INSERT IGNORE INTO listings
  (id, seller_id, category_id, title, slug, description, `condition`, sale_type, price,
   starting_bid, reserve_price, current_bid, bid_increment, auction_start, auction_end,
   auto_extend, buy_it_now_price, quantity, views_count, watch_count, is_active, is_featured, status)
VALUES
-- HOME & GARDEN (60, 61)
(1301, 1008, 60, 'Rice Cooker — 1.8L', 'rice-cooker-1-8l', 'Rice cooker. 1.8L capacity. Non-stick pot. With steamer tray.', 'like_new', 'fixed', 32000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1302, 1008, 60, 'Electric Kettle — Stainless', 'electric-kettle-stainless', 'Electric kettle. Stainless steel. 1.7L. Fast boil.', 'new', 'fixed', 18000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1303, 1008, 60, 'Blender — NutriBullet', 'blender-nutribullet', 'NutriBullet blender. 900W. With multiple cups.', 'good', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1304, 1008, 60, 'Microwave Oven — Samsung', 'microwave-oven-samsung', 'Samsung microwave oven. 23L. Digital display.', 'good', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1305, 1008, 60, 'Gas Cooker — 4 Burner', 'gas-cooker-4-burner', '4-burner gas cooker. Stainless steel. With oven.', 'like_new', 'fixed', 145000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 1, 'active'),
(1306, 1008, 60, 'Refrigerator — LG Double Door', 'refrigerator-lg-double-door', 'LG double-door refrigerator. 450L. Inverter compressor.', 'like_new', 'fixed', 385000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1307, 1008, 60, 'Washing Machine — Front Load', 'washing-machine-front-load', 'Front-load washing machine. 7kg. Multiple cycles.', 'good', 'fixed', 245000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 0, 'active'),
(1308, 1008, 61, 'Garden Hose — 30m', 'garden-hose-30m', '30-metre garden hose. With spray nozzle. Never used.', 'new', 'fixed', 25000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1309, 1008, 61, 'Lawn Mower — Electric', 'lawn-mower-electric', 'Electric lawn mower. 1200W. Adjustable height. Grass box.', 'good', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1310, 1008, 61, 'Garden Tool Set — 5 Pieces', 'garden-tool-set-5-pieces', 'Garden tool set. 5 pieces. Trowel, fork, rake, pruner, gloves.', 'new', 'fixed', 18000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 56, 3, 1, 0, 'active'),
(1311, 1008, 61, 'Water Pump — Submersible', 'water-pump-submersible', 'Submersible water pump. 1HP. For well or borehole.', 'good', 'fixed', 95000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1312, 1008, 60, 'Vacuum Cleaner — Cordless', 'vacuum-cleaner-cordless', 'Cordless vacuum cleaner. 45-min runtime. HEPA filter.', 'like_new', 'fixed', 125000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 1, 'active'),
(1313, 1008, 60, 'Complete Kitchen Set — New', 'complete-kitchen-set-new', 'Complete kitchen set. Plates, pots, cutlery, utensils. 80 pieces.', 'new', 'auction', NULL, 125000.00, 165000.00, 125000.00, 5000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1314, 1008, 61, 'Generator — 3.5KVA', 'generator-3-5kva', '3.5KVA petrol generator. Sumec Firman. Well maintained.', 'good', 'auction', NULL, 285000.00, 350000.00, 285000.00, 10000.00, NOW(), DATE_ADD(NOW(), INTERVAL 11 DAY), 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1315, 1008, 60, 'Dining Set — 8 Seater', 'dining-set-8-seater', 'Complete 8-seater dining set. Marble top. 8 chairs included.', 'like_new', 'hybrid', NULL, 385000.00, 465000.00, 385000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 525000.00, 1, 234, 21, 1, 1, 'active'),

-- OTHER (category 10) and HEALTH & BEAUTY (49) and PET SUPPLIES (50)
(1316, 1009, 72, 'Skincare Bundle — Premium', 'skincare-bundle-premium', 'Premium skincare bundle. Cleanser, toner, moisturizer, sunscreen.', 'new', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 1, 'active'),
(1317, 1009, 72, 'Vitamin C Serum — 30ml', 'vitamin-c-serum-30ml', 'Vitamin C serum. 30ml. Brightening. New in box.', 'new', 'fixed', 18000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1318, 1009, 72, 'Face Mask Set — Korean', 'face-mask-set-korean', 'Korean face mask set. 20 masks. Various types.', 'new', 'fixed', 12000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1319, 1009, 73, 'Makeup Kit — Professional', 'makeup-kit-professional', 'Professional makeup kit. Full set. Brushes, palettes, foundation.', 'like_new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1320, 1009, 73, 'Lipstick Set — MAC', 'lipstick-set-mac', 'MAC lipstick set. 5 shades. Authentic. New.', 'new', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 1, 'active'),
(1321, 1009, 73, 'Foundation — Fenty Beauty', 'foundation-fenty-beauty', 'Fenty Beauty foundation. Shade 320. New with box.', 'new', 'fixed', 28000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1322, 1009, 74, 'Massage Gun — Portable', 'massage-gun-portable', 'Portable massage gun. 6 speeds. 4 attachments. USB-C.', 'like_new', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1323, 1009, 74, 'Yoga Mat — Premium', 'yoga-mat-premium-2', 'Premium yoga mat. Extra thick. Non-slip. With bag.', 'new', 'fixed', 22000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1324, 1009, 74, 'Meditation Cushion', 'meditation-cushion', 'Meditation cushion. Buckwheat filling. Cotton cover.', 'new', 'fixed', 15000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1325, 1009, 75, 'Dog Food — Premium 15kg', 'dog-food-premium-15kg', 'Premium dog food. 15kg bag. Chicken and rice.', 'new', 'fixed', 35000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1326, 1009, 75, 'Dog Collar and Leash Set', 'dog-collar-leash-set', 'Dog collar and leash set. Leather. Size M.', 'like_new', 'fixed', 12000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1327, 1009, 75, 'Dog Bed — Large', 'dog-bed-large', 'Large dog bed. Washable cover. Orthopedic foam.', 'new', 'fixed', 22000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1328, 1009, 76, 'Cat Litter Box — Self-Cleaning', 'cat-litter-box-self-cleaning', 'Self-cleaning cat litter box. Automatic. Good condition.', 'good', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1329, 1009, 76, 'Cat Tree — 5 Level', 'cat-tree-5-level', '5-level cat tree. Sisal posts. Plush platforms.', 'like_new', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1330, 1009, 76, 'Cat Food — Premium 10kg', 'cat-food-premium-10kg', 'Premium cat food. 10kg bag. Salmon flavor.', 'new', 'fixed', 28000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1331, 1009, 77, 'Hamster Cage — Complete', 'hamster-cage-complete', 'Complete hamster cage. With wheel, bottle, bedding.', 'good', 'fixed', 18000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 45, 2, 1, 0, 'active'),
(1332, 1009, 77, 'Fish Tank — 40L', 'fish-tank-40l', '40-litre fish tank. With filter, light, decorations.', 'good', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1333, 1009, 77, 'Bird Cage — Large', 'bird-cage-large', 'Large bird cage. With perches and feeders. Excellent condition.', 'like_new', 'fixed', 35000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 56, 3, 1, 0, 'active'),
(1334, 1009, 72, 'Anti-Aging Cream Set', 'anti-aging-cream-set', 'Anti-aging cream set. Day and night cream. Retinol.', 'new', 'auction', NULL, 45000.00, 65000.00, 45000.00, 2000.00, NOW(), DATE_ADD(NOW(), INTERVAL 8 DAY), 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1335, 1009, 73, 'Complete Bridal Makeup Kit', 'complete-bridal-makeup-kit', 'Complete bridal makeup kit. Professional grade. 30+ products.', 'like_new', 'hybrid', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 285000.00, 1, 178, 14, 1, 1, 'active'),

-- OTHER (10) — Musical, Industrial
(1336, 1009, NULL, 'Yamaha Keyboard — 61 Keys', 'yamaha-keyboard-61-keys', 'Yamaha PSR keyboard. 61 keys. With stand and pedal.', 'good', 'fixed', 125000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1337, 1009, NULL, 'Acoustic Guitar — Yamaha', 'acoustic-guitar-yamaha', 'Yamaha acoustic guitar. With case. Excellent condition.', 'like_new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 156, 11, 1, 0, 'active'),
(1338, 1009, NULL, 'Electric Guitar — Fender', 'electric-guitar-fender', 'Fender Squier Stratocaster. Sunburst. With amp.', 'good', 'fixed', 185000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 1, 'active'),
(1339, 1009, NULL, 'Drum Set — Complete', 'drum-set-complete', 'Complete drum set. 5-piece. With cymbals and stool.', 'good', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1340, 1009, NULL, 'Studio Microphone — Condenser', 'studio-microphone-condenser', 'Condenser studio microphone. With pop filter and shock mount.', 'like_new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1341, 1009, NULL, 'Audio Mixer — 8 Channel', 'audio-mixer-8-channel', '8-channel audio mixer. USB. For home studio.', 'good', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1342, 1009, NULL, 'Inverter — 2.5KVA', 'inverter-2-5kva', '2.5KVA inverter. With battery. Excellent condition.', 'good', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 0, 'active'),
(1343, 1009, NULL, 'Solar Panel — 200W', 'solar-panel-200w', 'Solar panel 200W. Monocrystalline. High efficiency.', 'new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 1, 'active'),
(1344, 1009, NULL, 'Welding Machine — Inverter', 'welding-machine-inverter', 'Inverter welding machine. 200A. For light industrial use.', 'good', 'fixed', 125000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1345, 1009, NULL, 'Complete Studio Setup', 'complete-studio-setup', 'Complete home studio setup. Mic, mixer, monitors, headphones.', 'like_new', 'auction', NULL, 385000.00, 485000.00, 385000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 11 DAY), 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1346, 1009, NULL, 'Professional Keyboard', 'professional-keyboard', 'Yamaha Motif professional keyboard. 88 keys. Weighted.', 'like_new', 'hybrid', NULL, 785000.00, 985000.00, 785000.00, 25000.00, NOW(), DATE_ADD(NOW(), INTERVAL 12 DAY), 1, 1150000.00, 1, 145, 11, 1, 1, 'active'),

-- MORE ELECTRONICS
(1347, 1006, 11, 'Tecno Spark 10 Pro', 'tecno-spark-10-pro', 'Tecno Spark 10 Pro. 8GB RAM, 128GB. Excellent camera.', 'like_new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1348, 1006, 11, 'Itel A70 — Budget Phone', 'itel-a70-budget-phone', 'Itel A70 budget smartphone. 4GB RAM, 128GB. New in box.', 'new', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1349, 1006, 12, 'HP Stream 11 — Budget', 'hp-stream-11-budget', 'HP Stream 11. Intel Celeron, 4GB RAM, 64GB eMMC.', 'good', 'fixed', 95000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1350, 1006, 13, 'Instax Mini 11 Camera', 'instax-mini-11-camera', 'Fujifilm Instax Mini 11 instant camera. Lavender.', 'like_new', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1351, 1006, 14, 'JBL Charge 5 Speaker', 'jbl-charge-5-speaker', 'JBL Charge 5 Bluetooth speaker. 20-hour battery. Powerbank function.', 'like_new', 'fixed', 95000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 1, 'active'),
(1352, 1006, 11, 'Nokia 3310 — 2017', 'nokia-3310-2017', 'Nokia 3310 (2017). Brand new. Iconic phone. Collector\'s item.', 'new', 'auction', NULL, 25000.00, 35000.00, 25000.00, 1000.00, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 1, NULL, 1, 234, 21, 1, 0, 'active'),
(1353, 1006, 14, 'Sonos Move Portable Speaker', 'sonos-move-portable-speaker', 'Sonos Move portable speaker. WiFi + Bluetooth. Excellent.', 'like_new', 'hybrid', NULL, 285000.00, 355000.00, 285000.00, 10000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, 385000.00, 1, 145, 11, 1, 1, 'active'),

-- MORE FASHION
(1354, 1007, 15, 'Kaftan with Embroidery — Green', 'kaftan-embroidered-green', 'Green Kaftan with gold embroidery. Size XL. New.', 'new', 'fixed', 28000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1355, 1007, 16, 'Ankara Midi Skirt', 'ankara-midi-skirt', 'Ankara midi skirt. Multi-colored. Size 12. New.', 'new', 'fixed', 15000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1356, 1007, 16, 'Lace Wedding Dress — Ivory', 'lace-wedding-dress-ivory', 'Ivory lace wedding dress. Custom. Size 10.', 'like_new', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 1, 'active'),
(1357, 1007, 17, 'Kids Formal Set — Age 6', 'kids-formal-set-age-6', 'Formal outfit for kids. Age 6. Navy. Includes tie.', 'like_new', 'fixed', 15000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 45, 2, 1, 0, 'active'),
(1358, 1007, 18, 'Balenciaga Sneakers — 42', 'balenciaga-sneakers-42', 'Balenciaga Triple S sneakers. Size 42. Authentic.', 'like_new', 'fixed', 385000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 21, 1, 1, 'active'),
(1359, 1007, 16, 'Designer Wedding Gown', 'designer-wedding-gown', 'Designer wedding gown. Ivory. Custom-made. Never worn.', 'new', 'auction', NULL, 585000.00, 785000.00, 585000.00, 20000.00, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 1, NULL, 1, 345, 31, 1, 1, 'active'),
(1360, 1007, 18, 'Limited Edition Sneakers', 'limited-edition-sneakers', 'Limited edition sneakers. Deadstock. With box and receipt.', 'new', 'hybrid', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 285000.00, 1, 178, 14, 1, 1, 'active'),

-- MORE FURNITURE
(1361, 1008, 60, 'Rocking Chair — Wooden', 'rocking-chair-wooden', 'Wooden rocking chair. Handcrafted. Excellent condition.', 'good', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1362, 1008, 61, 'Cot Bed — Baby', 'cot-bed-baby', 'Baby cot bed. Wooden. Adjustable mattress height. With mattress.', 'like_new', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1363, 1008, 60, 'Outdoor Patio Set', 'outdoor-patio-set', 'Outdoor patio set. Table + 4 chairs. Weatherproof.', 'like_new', 'fixed', 185000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1364, 1008, 61, 'Study Desk — Student', 'study-desk-student', 'Student study desk. With shelf. Ideal for HND students.', 'good', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 1, 'active'),
(1365, 1008, 60, 'Bamboo Furniture Set', 'bamboo-furniture-set', 'Eco-friendly bamboo furniture set. Sofa + table + shelf.', 'new', 'auction', NULL, 285000.00, 365000.00, 285000.00, 10000.00, NOW(), DATE_ADD(NOW(), INTERVAL 11 DAY), 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1366, 1008, 61, 'King Platform Bed — Minimalist', 'king-platform-bed-minimalist', 'King-size platform bed. Minimalist design. Solid wood.', 'like_new', 'hybrid', NULL, 285000.00, 365000.00, 285000.00, 10000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 425000.00, 1, 178, 14, 1, 1, 'active'),

-- MORE BOOKS
(1367, 1009, 57, 'Complete Works of Shakespeare', 'complete-works-shakespeare', 'Complete works of William Shakespeare. Single volume.', 'good', 'fixed', 8500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1368, 1009, 57, 'The Godfather — Mario Puzo', 'godfather-mario-puzo', 'The Godfather by Mario Puzo. Excellent condition.', 'good', 'fixed', 3500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1369, 1009, 57, 'Business Management — Bida Poly', 'business-management-bida-poly', 'Business Management textbook. Bida Poly syllabus.', 'good', 'fixed', 5500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1370, 1009, 57, 'Dictionary of Computer Terms', 'dictionary-of-computer-terms', 'Dictionary of Computer Terms. Comprehensive.', 'like_new', 'fixed', 4500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 56, 3, 1, 0, 'active'),
(1371, 1009, 57, 'Sherlock Holmes Complete Collection', 'sherlock-holmes-complete-collection', 'Complete Sherlock Holmes collection. All stories in one volume.', 'good', 'fixed', 6500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1372, 1009, 57, 'African Literature Collection', 'african-literature-collection', 'African literature collection. 10 essential novels.', 'good', 'auction', NULL, 25000.00, 35000.00, 25000.00, 1000.00, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1373, 1009, 57, 'Rare First Edition Set', 'rare-first-edition-set', 'Set of rare first edition books. Nigerian authors.', 'like_new', 'hybrid', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 285000.00, 1, 145, 11, 1, 1, 'active'),

-- MORE COLLECTIBLES
(1374, 1006, 69, 'Vintage Beer Steins', 'vintage-beer-steins', 'Set of 5 vintage German beer steins. Porcelain.', 'good', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1375, 1006, 68, 'Handcrafted Ceramic Vase', 'handcrafted-ceramic-vase', 'Handcrafted ceramic vase. Nigerian artist. 30cm tall.', 'new', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1376, 1006, 69, 'Vintage Cigar Box Collection', 'vintage-cigar-box-collection', 'Collection of 10 vintage cigar boxes. Wood. Excellent.', 'good', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1377, 1006, 69, 'Old Typewriter — Working', 'old-typewriter-working', 'Vintage typewriter. Fully working. With case.', 'good', 'fixed', 145000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1378, 1006, 68, 'Contemporary Nigerian Sculpture', 'contemporary-nigerian-sculpture', 'Contemporary Nigerian sculpture. Metal. Modern design.', 'new', 'auction', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1379, 1006, 69, 'Rare Historical Document', 'rare-historical-document', 'Rare historical document. Colonial era. With COA.', 'good', 'hybrid', NULL, 285000.00, 385000.00, 285000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 12 DAY), 1, 485000.00, 1, 123, 9, 1, 1, 'active'),

-- MORE VEHICLES
(1380, 1008, 70, 'Toyota Sienna 2012', 'toyota-sienna-2012', 'Toyota Sienna 2012. Minivan. 7-seater. Family-ready.', 'good', 'fixed', 7500000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 0, 'active'),
(1381, 1008, 70, 'Kia Optima 2014', 'kia-optima-2014', 'Kia Optima 2014. Sedan. Full option. Excellent condition.', 'good', 'fixed', 4850000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1382, 1008, 70, 'Toyota Avalon 2013', 'toyota-avalon-2013', 'Toyota Avalon 2013. Full size sedan. Leather seats.', 'good', 'fixed', 6500000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 0, 'active'),
(1383, 1008, 70, 'Volvo XC90 2015', 'volvo-xc90-2015', 'Volvo XC90 2015. 7-seater SUV. Diesel. Fully loaded.', 'like_new', 'auction', NULL, 18500000.00, 22500000.00, 18500000.00, 250000.00, NOW(), DATE_ADD(NOW(), INTERVAL 13 DAY), 1, NULL, 1, 345, 31, 1, 1, 'active'),
(1384, 1008, 70, 'Audi Q5 2016', 'audi-q5-2016', 'Audi Q5 2016. SUV. Quattro AWD. Panoramic roof.', 'like_new', 'hybrid', NULL, 15500000.00, 18500000.00, 15500000.00, 200000.00, NOW(), DATE_ADD(NOW(), INTERVAL 12 DAY), 1, 21500000.00, 1, 234, 21, 1, 1, 'active'),

-- MORE SPORTS
(1385, 1008, 67, 'Table Tennis Robot', 'table-tennis-robot', 'Table tennis robot. Auto ball feeder. Adjustable spin.', 'like_new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1386, 1008, 65, 'Pilates Reformer', 'pilates-reformer', 'Pilates reformer. Home use. Foldable. With accessories.', 'like_new', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 1, 'active'),
(1387, 1008, 65, 'Weighted Vest — 20kg', 'weighted-vest-20kg', '20kg weighted vest. Adjustable. For training.', 'new', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1388, 1008, 65, 'GYM Floor Mats — Set', 'gym-floor-mats-set', 'Set of 12 gym floor mats. Interlocking foam. 1cm thick.', 'new', 'fixed', 35000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1389, 1008, 67, 'Complete Surfboard Kit', 'complete-surfboard-kit', 'Complete surfboard kit. Board + leash + wax. Excellent.', 'like_new', 'auction', NULL, 145000.00, 185000.00, 145000.00, 5000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1390, 1008, 65, 'Complete Home Gym Bundle', 'complete-home-gym-bundle', 'Complete home gym bundle. Treadmill, bike, weights, bench.', 'like_new', 'hybrid', NULL, 585000.00, 785000.00, 585000.00, 20000.00, NOW(), DATE_ADD(NOW(), INTERVAL 11 DAY), 1, 985000.00, 1, 234, 21, 1, 1, 'active'),

-- MORE TOYS
(1391, 1008, 62, 'Rubik\'s Cube Collection', 'rubiks-cube-collection', 'Set of 5 Rubik\'s cubes. Different sizes. Excellent.', 'like_new', 'fixed', 12000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1392, 1008, 64, 'Kids Kiddie Pool', 'kids-kiddie-pool', 'Inflatable kiddie pool. For ages 2-6. With pump.', 'like_new', 'fixed', 18000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1393, 1008, 62, 'Dungeons & Dragons Set', 'dungeons-dragons-set', 'Dungeons & Dragons starter set. Books, dice, maps.', 'like_new', 'fixed', 35000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1394, 1008, 63, 'VR Headset — Oculus Quest 2', 'vr-headset-oculus-quest-2', 'Oculus Quest 2 VR headset. 128GB. With controllers.', 'like_new', 'fixed', 385000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 345, 31, 1, 1, 'active'),
(1395, 1008, 63, 'Gaming PC — Custom Built', 'gaming-pc-custom-built', 'Custom-built gaming PC. Ryzen 7, RTX 3070, 32GB RAM, 1TB SSD.', 'like_new', 'auction', NULL, 1250000.00, 1450000.00, 1250000.00, 25000.00, NOW(), DATE_ADD(NOW(), INTERVAL 11 DAY), 1, NULL, 1, 456, 42, 1, 1, 'active'),
(1396, 1008, 62, 'Complete LEGO Technic Set', 'complete-lego-technic-set', 'Complete LEGO Technic set. 2000+ pieces. Rare model.', 'like_new', 'hybrid', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 285000.00, 1, 178, 14, 1, 1, 'active'),

-- FINAL ELECTRONICS
(1397, 1006, 11, 'iPhone 15 Pro — 256GB', 'iphone-15-pro-256gb', 'iPhone 15 Pro, 256GB. Natural Titanium. 3 months old.', 'like_new', 'fixed', 1250000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 678, 65, 1, 1, 'active'),
(1398, 1006, 12, 'Alienware M15 — RTX 3070', 'alienware-m15-rtx-3070', 'Alienware M15 gaming laptop. i7 11th gen, 16GB, 1TB SSD, RTX 3070.', 'like_new', 'fixed', 1450000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 21, 1, 1, 'active'),
(1399, 1006, 13, 'Leica Q2 — Full Frame', 'leica-q2-full-frame', 'Leica Q2 full frame compact camera. 47MP. Fixed 28mm lens.', 'like_new', 'auction', NULL, 2450000.00, 2850000.00, 2450000.00, 50000.00, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 1, NULL, 1, 189, 15, 1, 1, 'active'),
(1400, 1006, 12, 'MacBook Pro 16 M2 Max', 'macbook-pro-16-m2-max', 'MacBook Pro 16 M2 Max. 32GB RAM, 1TB SSD. Space Black.', 'like_new', 'hybrid', NULL, 2850000.00, 3250000.00, 2850000.00, 50000.00, NOW(), DATE_ADD(NOW(), INTERVAL 12 DAY), 1, 3650000.00, 1, 456, 42, 1, 1, 'active');