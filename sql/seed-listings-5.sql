-- marketplace/sql/seed-listings-5.sql
-- 100 listings (ids 1401-1500)

USE `marketplace_db`;

INSERT IGNORE INTO listings
  (id, seller_id, category_id, title, slug, description, `condition`, sale_type, price,
   starting_bid, reserve_price, current_bid, bid_increment, auction_start, auction_end,
   auto_extend, buy_it_now_price, quantity, views_count, watch_count, is_active, is_featured, status)
VALUES
-- ELECTRONICS
(1401, 1006, 12, 'Acer Predator Helios 300', 'acer-predator-helios-300', 'Acer Predator Helios 300 gaming laptop. i7, 16GB, 512GB SSD, RTX 3060.', 'good', 'fixed', 785000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1402, 1006, 12, 'Chuwi HeroBook Pro', 'chuwi-herobok-pro', 'Chuwi HeroBook Pro. Budget laptop for students. Windows 11.', 'like_new', 'fixed', 125000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1403, 1006, 11, 'Xiaomi Poco F5', 'xiaomi-poco-f5', 'Xiaomi Poco F5. 8GB RAM, 256GB. Snapdragon 7+ Gen 2.', 'like_new', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 1, 'active'),
(1404, 1006, 11, 'Samsung Galaxy A54 5G', 'samsung-galaxy-a54-5g', 'Samsung Galaxy A54 5G. 8GB RAM, 128GB. Brand new sealed.', 'new', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 156, 11, 1, 0, 'active'),
(1405, 1006, 12, 'Dell XPS 13 — i7 16GB', 'dell-xps-13-i7-16gb', 'Dell XPS 13. i7, 16GB RAM, 512GB SSD. Premium ultrabook.', 'like_new', 'fixed', 685000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1406, 1006, 12, 'Lenovo Yoga 7i — Touchscreen', 'lenovo-yoga-7i-touchscreen', 'Lenovo Yoga 7i. Touchscreen. i5 11th gen, 8GB, 512GB SSD.', 'like_new', 'fixed', 485000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1407, 1006, 13, 'Sony Cyber-shot DSC-W800', 'sony-cyber-shot-dsc-w800', 'Sony Cyber-shot DSC-W800 point-and-shoot. 20MP.', 'good', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1408, 1006, 13, 'Canon PowerShot G7X Mark II', 'canon-powershot-g7x-mark-ii', 'Canon PowerShot G7X Mark II. Vlogger camera. Excellent.', 'like_new', 'fixed', 385000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 21, 1, 1, 'active'),
(1409, 1006, 14, 'Sennheiser HD 660S', 'sennheiser-hd-660s', 'Sennheiser HD 660S audiophile headphones. Open back.', 'like_new', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1410, 1006, 14, 'AirPods Pro 2', 'airpods-pro-2', 'Apple AirPods Pro 2. Active noise cancellation. With case.', 'like_new', 'fixed', 185000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 345, 31, 1, 1, 'active'),
(1411, 1006, 14, 'Marshall Emberton Speaker', 'marshall-emberton-speaker', 'Marshall Emberton portable speaker. 20-hour battery.', 'new', 'fixed', 95000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1412, 1006, 53, 'iPad Mini 6 — 64GB', 'ipad-mini-6-64gb', 'iPad Mini 6, 64GB. Purple. With Apple Pencil 2.', 'like_new', 'fixed', 385000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1413, 1006, 53, 'Samsung Galaxy Tab A8', 'samsung-galaxy-tab-a8', 'Samsung Galaxy Tab A8. 10.5 inch. 64GB. WiFi.', 'like_new', 'fixed', 125000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1414, 1006, 11, 'Nothing Phone 2 — 256GB', 'nothing-phone-2-256gb', 'Nothing Phone 2. 12GB RAM, 256GB. Glyph interface.', 'like_new', 'auction', NULL, 485000.00, 585000.00, 485000.00, 10000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, NULL, 1, 234, 21, 1, 1, 'active'),
(1415, 1006, 12, 'LG Gram 17 — Ultra Light', 'lg-gram-17-ultra-light', 'LG Gram 17. i7, 16GB, 1TB SSD. Weighs only 1.35kg.', 'like_new', 'auction', NULL, 985000.00, 1150000.00, 985000.00, 20000.00, NOW(), DATE_ADD(NOW(), INTERVAL 11 DAY), 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1416, 1006, 14, 'Bose Soundbar 700', 'bose-soundbar-700', 'Bose Soundbar 700. Premium home theater. With subwoofer.', 'like_new', 'hybrid', NULL, 585000.00, 685000.00, 585000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 785000.00, 1, 234, 21, 1, 1, 'active'),

-- FASHION
(1417, 1007, 16, 'Long Flowing Ankara Gown', 'long-flowing-ankara-gown', 'Long flowing Ankara gown. Beautiful pattern. Size 12.', 'new', 'fixed', 35000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1418, 1007, 15, 'Vintage Leather Jacket', 'vintage-leather-jacket', 'Vintage 1970s leather jacket. Brown. Genuine leather.', 'good', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1419, 1007, 16, 'Bohemian Maxi Dress', 'bohemian-maxi-dress', 'Bohemian maxi dress. Floral print. Size 10. Excellent.', 'like_new', 'fixed', 25000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1420, 1007, 17, 'Kids Cartoon Pajama Set', 'kids-cartoon-pajama-set', 'Cartoon pajama set for kids. Age 4. Cotton. Soft.', 'new', 'fixed', 8000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 56, 3, 1, 0, 'active'),
(1421, 1007, 17, 'Toddler Sneakers — Size 22', 'toddler-sneakers-size-22', 'Toddler sneakers. Size 22. Blue. Brand new.', 'new', 'fixed', 12000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 45, 2, 1, 0, 'active'),
(1422, 1007, 18, 'Leather Sandals — Women 38', 'leather-sandals-women-38', 'Genuine leather sandals. Size 38. Tan. Comfortable.', 'like_new', 'fixed', 22000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1423, 1007, 18, 'Running Shoes — Asics 44', 'running-shoes-asics-44', 'Asics Gel-Kayano 29. Size 44. Excellent cushioning.', 'like_new', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1424, 1007, 54, 'Vintage Denim Jacket — M', 'vintage-denim-jacket-m', 'Vintage Levi\'s denim jacket. Size M. Excellent condition.', 'good', 'fixed', 35000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1425, 1007, 54, 'Polo Ralph Lauren — L', 'polo-ralph-lauren-l', 'Polo Ralph Lauren shirt. Blue. Size L. Authentic.', 'like_new', 'fixed', 25000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1426, 1007, 55, 'Mango Midi Dress — M', 'mango-midi-dress-m', 'Mango midi dress. Floral. Size M. Barely worn.', 'like_new', 'fixed', 22000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1427, 1007, 55, 'Designer Clutch Bag', 'designer-clutch-bag', 'Designer clutch bag. Silver. New with tags.', 'new', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 1, 'active'),
(1428, 1007, 56, 'Kids Party Dress — Age 8', 'kids-party-dress-age-8', 'Party dress for kids. Age 8. Pink with sparkles.', 'like_new', 'fixed', 18000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 45, 2, 1, 0, 'active'),
(1429, 1007, 16, 'Vintage Silk Saree', 'vintage-silk-saree', 'Vintage silk saree. Hand-embroidered. Beautiful.', 'good', 'auction', NULL, 45000.00, 65000.00, 45000.00, 2000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1430, 1007, 18, 'Bespoke Leather Boots — 43', 'bespoke-leather-boots-43', 'Bespoke leather boots. Custom-made. Size 43. Cognac.', 'like_new', 'hybrid', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 285000.00, 1, 145, 11, 1, 1, 'active'),

-- FURNITURE
(1431, 1008, 60, 'Corner TV Unit — Modern', 'corner-tv-unit-modern', 'Modern corner TV unit. Wood. Holds up to 55-inch TV.', 'like_new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1432, 1008, 61, 'Walk-in Closet System', 'walk-in-closet-system', 'Complete walk-in closet system. Modular. Customizable.', 'like_new', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1433, 1008, 60, 'Vintage Writing Bureau', 'vintage-writing-bureau', 'Vintage writing bureau. Solid oak. With drawers.', 'good', 'fixed', 125000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1434, 1008, 60, 'Rattan Chair Set', 'rattan-chair-set', 'Set of 2 rattan chairs. Bohemian style. Excellent.', 'like_new', 'fixed', 95000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1435, 1008, 61, 'Platform Bed — Storage', 'platform-bed-storage', 'Platform bed with under-bed storage. Queen size.', 'good', 'fixed', 145000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1436, 1008, 60, 'Vintage Kitchen Cabinet', 'vintage-kitchen-cabinet', 'Vintage kitchen cabinet. Solid wood. Restored.', 'good', 'auction', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1437, 1008, 61, 'Designer Bedroom Set', 'designer-bedroom-set', 'Designer bedroom set. Bed, 2 nightstands, dresser.', 'like_new', 'hybrid', NULL, 585000.00, 685000.00, 585000.00, 20000.00, NOW(), DATE_ADD(NOW(), INTERVAL 12 DAY), 1, 785000.00, 1, 234, 21, 1, 1, 'active'),

-- BOOKS
(1438, 1009, 57, 'Wuthering Heights', 'wuthering-heights', 'Wuthering Heights by Emily Bronte. Classic literature.', 'good', 'fixed', 2500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1439, 1009, 57, 'Pride and Prejudice', 'pride-and-prejudice', 'Pride and Prejudice by Jane Austen. Timeless classic.', 'good', 'fixed', 2500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1440, 1009, 57, 'The Old Man and the Sea', 'the-old-man-and-the-sea', 'The Old Man and the Sea by Hemingway. Pulitzer Prize.', 'good', 'fixed', 3000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1441, 1009, 57, 'Complete Sherlock Holmes', 'complete-sherlock-holmes', 'Complete Sherlock Holmes collection. All 4 novels + 56 stories.', 'good', 'fixed', 8500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1442, 1009, 57, 'Sapiens — Yuval Harari', 'sapiens-yuval-harari', 'Sapiens by Yuval Noah Harari. A brief history of humankind.', 'like_new', 'fixed', 5500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 0, 'active'),
(1443, 1009, 57, 'Zero to One — Peter Thiel', 'zero-to-one-peter-thiel', 'Zero to One by Peter Thiel. Startup wisdom.', 'like_new', 'fixed', 4500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1444, 1009, 57, 'The Lean Startup', 'the-lean-startup', 'The Lean Startup by Eric Ries. Business classic.', 'like_new', 'fixed', 4500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1445, 1009, 59, 'Vintage Life Magazines', 'vintage-life-magazines', 'Set of 10 vintage Life magazines. 1960s-1970s.', 'fair', 'fixed', 22000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1446, 1009, 57, 'Rare Africana Book Set', 'rare-africana-book-set', 'Set of rare Africana books. 8 volumes. Collector\'s item.', 'good', 'auction', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, NULL, 1, 145, 11, 1, 1, 'active'),
(1447, 1009, 57, 'Antique Bible — 1880', 'antique-bible-1880', 'Antique Bible from 1880. Leather bound. Excellent condition.', 'good', 'hybrid', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 11 DAY), 1, 285000.00, 1, 123, 9, 1, 1, 'active'),

-- COLLECTIBLES
(1448, 1006, 69, 'Vintage Fountain Pen Set', 'vintage-fountain-pen-set', 'Set of 5 vintage fountain pens. Gold nibs.', 'good', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1449, 1006, 69, 'Old Camera Collection', 'old-camera-collection', 'Collection of 8 vintage cameras. 1950s-1970s.', 'fair', 'fixed', 145000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1450, 1006, 68, 'Hand-Painted Ceramic Plates', 'hand-painted-ceramic-plates', 'Set of 6 hand-painted ceramic plates. Nigerian artist.', 'new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1451, 1006, 69, 'Vintage Phonograph Records', 'vintage-phonograph-records', 'Collection of 20 vintage phonograph records. 78 RPM.', 'fair', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1452, 1006, 68, 'Original Watercolor Painting', 'original-watercolor-painting', 'Original watercolor painting. Lagos skyline. 40cm x 30cm.', 'new', 'fixed', 125000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 1, 'active'),
(1453, 1006, 69, 'Vintage Wooden Chess Set', 'vintage-wooden-chess-set', 'Vintage wooden chess set. 1960s. Hand-carved pieces.', 'good', 'auction', NULL, 45000.00, 65000.00, 45000.00, 2000.00, NOW(), DATE_ADD(NOW(), INTERVAL 8 DAY), 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1454, 1006, 68, 'Complete Art Collection', 'complete-art-collection', 'Complete art collection. 5 original paintings by Nigerian artists.', 'new', 'hybrid', NULL, 585000.00, 785000.00, 585000.00, 20000.00, NOW(), DATE_ADD(NOW(), INTERVAL 13 DAY), 1, 985000.00, 1, 178, 14, 1, 1, 'active'),

-- VEHICLES
(1455, 1008, 70, 'Toyota Camry 2010', 'toyota-camry-2010', 'Toyota Camry 2010. Sedan. Clean. First body.', 'good', 'fixed', 3850000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 0, 'active'),
(1456, 1008, 70, 'Honda Accord 2010 — Muscle', 'honda-accord-2010-muscle', 'Honda Accord 2010 muscle. Clean title. New tires.', 'good', 'fixed', 4200000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1457, 1008, 70, 'Hyundai Sonata 2011', 'hyundai-sonata-2011', 'Hyundai Sonata 2011. Sedan. Clean. Well maintained.', 'good', 'fixed', 3500000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 156, 11, 1, 0, 'active'),
(1458, 1008, 70, 'Nissan Xterra 2010', 'nissan-xterra-2010', 'Nissan Xterra 2010. SUV. 4x4. Great for rough terrain.', 'good', 'fixed', 4500000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1459, 1008, 70, 'Mercedes-Benz E350 2012', 'mercedes-benz-e350-2012', 'Mercedes-Benz E350 2012. Luxury sedan. Full option.', 'good', 'fixed', 8500000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1460, 1008, 70, 'Complete Fleet — 3 Cars', 'complete-fleet-3-cars', 'Complete fleet of 3 cars. All first body. Business-ready.', 'good', 'auction', NULL, 15000000.00, 20000000.00, 15000000.00, 250000.00, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 1, NULL, 1, 345, 31, 1, 1, 'active'),
(1461, 1008, 70, 'Range Rover Velar 2018', 'range-rover-velar-2018', 'Range Rover Velar 2018. First body. Fully loaded. Panoramic roof.', 'like_new', 'hybrid', NULL, 28500000.00, 35000000.00, 28500000.00, 400000.00, NOW(), DATE_ADD(NOW(), INTERVAL 13 DAY), 1, 42000000.00, 1, 289, 25, 1, 1, 'active'),

-- SPORTS
(1462, 1008, 65, 'Punching Bag Set', 'punching-bag-set', 'Complete punching bag set. Bag, gloves, wraps.', 'good', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1463, 1008, 65, 'Boxing Ring — Professional', 'boxing-ring-professional', 'Professional boxing ring. 6m x 6m. Competition grade.', 'good', 'fixed', 1250000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 1, 'active'),
(1464, 1008, 67, 'Complete Cricket Kit — Junior', 'complete-cricket-kit-junior', 'Complete junior cricket kit. Ages 8-12. Bat, pads, gloves.', 'like_new', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1465, 1008, 67, 'Football Boots — Nike 43', 'football-boots-nike-43', 'Nike football boots. Size 43. Blue. Excellent grip.', 'like_new', 'fixed', 35000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1466, 1008, 67, 'Swimming Goggles — Speedo', 'swimming-goggles-speedo', 'Speedo swimming goggles. Anti-fog. UV protection.', 'new', 'fixed', 12000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1467, 1008, 66, 'Camping Tent — 6 Person', 'camping-tent-6-person', 'Waterproof 6-person camping tent. Family size.', 'good', 'fixed', 95000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1468, 1008, 66, 'Fishing Rod — Professional', 'fishing-rod-professional', 'Professional fishing rod. Carbon fiber. With reel.', 'new', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1469, 1008, 65, 'Weightlifting Belt', 'weightlifting-belt', 'Weightlifting belt. Leather. Adjustable. 4-inch.', 'new', 'fixed', 22000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1470, 1008, 65, 'Squat Rack — Commercial', 'squat-rack-commercial', 'Commercial squat rack. Heavy duty. With pull-up bar.', 'good', 'auction', NULL, 385000.00, 485000.00, 385000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1471, 1008, 66, 'Professional Camping Set', 'professional-camping-set', 'Complete professional camping set. Tent, stove, sleeping bags.', 'like_new', 'hybrid', NULL, 285000.00, 355000.00, 285000.00, 10000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 385000.00, 1, 178, 14, 1, 1, 'active'),

-- TOYS
(1472, 1008, 62, 'Chess Computer — Electronic', 'chess-computer-electronic', 'Electronic chess computer. Multiple levels. Great practice.', 'like_new', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1473, 1008, 62, 'Monopoly — Nigerian Edition', 'monopoly-nigerian-edition', 'Monopoly Nigerian Edition. Lagos theme. Complete.', 'like_new', 'fixed', 18000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1474, 1008, 62, 'Puzzle Collection — 10 Sets', 'puzzle-collection-10-sets', 'Collection of 10 jigsaw puzzles. Various sizes. Complete.', 'good', 'fixed', 22000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1475, 1008, 64, 'Kids Trampoline — Indoor', 'kids-trampoline-indoor', 'Indoor mini trampoline for kids. With safety handle.', 'like_new', 'fixed', 35000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1476, 1008, 64, 'Ride-On Car — Kids Electric', 'ride-on-car-kids-electric', 'Kids electric ride-on car. Working battery. Ages 3-6.', 'good', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1477, 1008, 64, 'Kiddie Playhouse', 'kiddie-playhouse', 'Kiddie playhouse. Plastic. Indoor/outdoor. Ages 2-5.', 'like_new', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1478, 1008, 63, 'Steam Deck — 512GB', 'steam-deck-512gb', 'Steam Deck 512GB. Handheld gaming PC. With case.', 'like_new', 'auction', NULL, 685000.00, 785000.00, 685000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, NULL, 1, 234, 21, 1, 1, 'active'),
(1479, 1008, 63, 'Complete Retro Console Collection', 'complete-retro-console-collection', 'Complete retro console collection. NES, SNES, Genesis.', 'good', 'hybrid', NULL, 485000.00, 585000.00, 485000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 11 DAY), 1, 685000.00, 1, 178, 14, 1, 1, 'active'),

-- HOME & GARDEN
(1480, 1008, 60, 'Pressure Cooker — 6L', 'pressure-cooker-6l', '6-litre pressure cooker. Stainless steel. Safety valve.', 'like_new', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1481, 1008, 60, 'Toaster Oven — 30L', 'toaster-oven-30l', '30-litre toaster oven. Convection. Multiple settings.', 'like_new', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1482, 1008, 60, 'Dishwasher — Bosch', 'dishwasher-bosch', 'Bosch dishwasher. 12 place settings. A+ rated.', 'like_new', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 1, 'active'),
(1483, 1008, 61, 'Garden Shed — Wooden', 'garden-shed-wooden', 'Wooden garden shed. 8x6 ft. Brand new. Not assembled.', 'new', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1484, 1008, 61, 'BBQ Grill — Charcoal', 'bbq-grill-charcoal', 'Charcoal BBQ grill. With lid. 4-person capacity.', 'like_new', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1485, 1008, 61, 'Water Tank — 1000L', 'water-tank-1000l', '1000-litre water tank. Plastic. UV resistant.', 'new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1486, 1008, 60, 'Home Theater System', 'home-theater-system', 'Complete home theater system. 5.1 channel. With receiver.', 'like_new', 'auction', NULL, 285000.00, 355000.00, 285000.00, 10000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1487, 1008, 61, 'Complete Garden Kit', 'complete-garden-kit', 'Complete garden kit. All tools, seeds, pots, fertilizer.', 'new', 'hybrid', NULL, 125000.00, 165000.00, 125000.00, 5000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, 185000.00, 1, 145, 11, 1, 1, 'active'),

-- OTHER (10) - Music, Electronics accessories
(1488, 1009, NULL, 'Home Recording Bundle', 'home-recording-bundle', 'Complete home recording bundle. Mic, interface, headphones.', 'like_new', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 1, 'active'),
(1489, 1009, NULL, 'PA System — Complete', 'pa-system-complete', 'Complete PA system. 1000W. 2 speakers + mixer + mics.', 'good', 'fixed', 585000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 1, 'active'),
(1490, 1009, NULL, 'DJ Controller — Pioneer', 'dj-controller-pioneer', 'Pioneer DDJ DJ controller. 2-channel. With software.', 'like_new', 'fixed', 385000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1491, 1009, NULL, 'Stage Lights Set', 'stage-lights-set', 'Set of 4 LED stage lights. DMX-controlled. Remote included.', 'like_new', 'fixed', 145000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1492, 1009, NULL, 'Projector — Epson', 'projector-epson', 'Epson projector. Full HD. 3000 lumens. With screen.', 'like_new', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 1, 'active'),
(1493, 1009, NULL, 'Commercial Coffee Machine', 'commercial-coffee-machine', 'Commercial espresso coffee machine. 2-group.', 'good', 'fixed', 685000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1494, 1009, NULL, '3D Printer — Creality', '3d-printer-creality', 'Creality Ender 3 3D printer. With filament. Excellent.', 'like_new', 'fixed', 245000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 1, 'active'),
(1495, 1009, NULL, 'Drone — DJI Mini 3', 'drone-dji-mini-3', 'DJI Mini 3 drone. 4K video. Under 249g. With extra battery.', 'like_new', 'auction', NULL, 485000.00, 585000.00, 485000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, NULL, 1, 234, 21, 1, 1, 'active'),
(1496, 1009, NULL, 'Complete Photography Bundle', 'complete-photography-bundle', 'Complete photography bundle. Camera, lens, tripod, bag.', 'like_new', 'auction', NULL, 685000.00, 785000.00, 685000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 11 DAY), 1, NULL, 1, 189, 15, 1, 1, 'active'),
(1497, 1009, 72, 'Complete Beauty Salon Kit', 'complete-beauty-salon-kit', 'Complete beauty salon kit. Everything needed to start.', 'new', 'fixed', 485000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 1, 'active'),
(1498, 1009, 73, 'Professional Nail Kit', 'professional-nail-kit', 'Professional nail kit. Full set of gel polishes and tools.', 'new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1499, 1009, 74, 'Complete Yoga Studio Set', 'complete-yoga-studio-set', 'Complete yoga studio set. 10 mats, blocks, straps, bolsters.', 'new', 'hybrid', NULL, 285000.00, 355000.00, 285000.00, 10000.00, NOW(), DATE_ADD(NOW(), INTERVAL 12 DAY), 1, 385000.00, 1, 145, 11, 1, 1, 'active'),
(1500, 1009, NULL, 'Vintage Record Player', 'vintage-record-player', 'Vintage record player. Working. Wooden case. With speakers.', 'good', 'auction', NULL, 125000.00, 185000.00, 125000.00, 5000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, NULL, 1, 234, 21, 1, 1, 'active');