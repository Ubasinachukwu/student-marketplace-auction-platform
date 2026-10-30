-- marketplace/sql/seed-listings-3.sql
-- 100 listings (ids 1201-1300)

USE `marketplace_db`;

INSERT IGNORE INTO listings
  (id, seller_id, category_id, title, slug, description, `condition`, sale_type, price,
   starting_bid, reserve_price, current_bid, bid_increment, auction_start, auction_end,
   auto_extend, buy_it_now_price, quantity, views_count, watch_count, is_active, is_featured, status)
VALUES
-- ELECTRONICS
(1201, 1006, 11, 'Vivo V23 5G — 256GB', 'vivo-v23-5g-256gb', 'Vivo V23 5G, 12GB RAM, 256GB. Color-changing back. Excellent camera.', 'like_new', 'fixed', 165000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1202, 1006, 11, 'Realme GT Master Edition', 'realme-gt-master-edition', 'Realme GT Master Edition. 8GB RAM, 256GB. Snapdragon 778G.', 'good', 'fixed', 145000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1203, 1006, 12, 'Razer Blade Stealth 13', 'razer-blade-stealth-13', 'Razer Blade Stealth 13, i7, 16GB, 512GB SSD, GTX 1650.', 'good', 'fixed', 585000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1204, 1006, 12, 'Surface Laptop 4 — 8GB', 'surface-laptop-4-8gb', 'Microsoft Surface Laptop 4, Ryzen 5, 8GB, 256GB SSD. Platinum.', 'like_new', 'fixed', 425000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 1, 'active'),
(1205, 1006, 13, 'Canon EOS R6 Mirrorless', 'canon-eos-r6-mirrorless', 'Canon EOS R6 mirrorless. 20MP. Full frame. 5,000 shutter count.', 'like_new', 'fixed', 1250000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 21, 1, 1, 'active'),
(1206, 1006, 13, 'Nikon Z6 II — Body', 'nikon-z6-ii-body', 'Nikon Z6 II mirrorless body. 24MP. Full frame.', 'like_new', 'fixed', 985000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1207, 1006, 14, 'Bose SoundLink Revolve+', 'bose-soundlink-revolve-plus', 'Bose SoundLink Revolve+. 360° sound. Waterproof. Original box.', 'like_new', 'fixed', 155000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1208, 1006, 14, 'Sonos One SL Speaker', 'sonos-one-sl-speaker', 'Sonos One SL smart speaker. Pairs with other Sonos.', 'good', 'fixed', 125000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1209, 1006, 53, 'iPad Pro 11 — 128GB', 'ipad-pro-11-128gb', 'iPad Pro 11-inch, 128GB, WiFi. Space Gray. With Apple Pencil 2.', 'like_new', 'fixed', 685000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 21, 1, 1, 'active'),
(1210, 1006, 53, 'Amazon Fire HD 10 Tablet', 'amazon-fire-hd-10-tablet', 'Amazon Fire HD 10 tablet, 32GB. Great for reading and media.', 'good', 'fixed', 95000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1211, 1006, 12, 'Lenovo Legion 5 — RTX 3060', 'lenovo-legion-5-rtx-3060', 'Lenovo Legion 5 gaming laptop. Ryzen 7, 16GB, 1TB, RTX 3060.', 'like_new', 'auction', NULL, 685000.00, 785000.00, 685000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 8 DAY), 1, NULL, 1, 345, 31, 1, 1, 'active'),
(1212, 1006, 11, 'Samsung Galaxy S22 Ultra', 'samsung-galaxy-s22-ultra', 'Samsung Galaxy S22 Ultra, 256GB. S-Pen. 108MP camera.', 'like_new', 'auction', NULL, 785000.00, 885000.00, 785000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, NULL, 1, 456, 42, 1, 1, 'active'),
(1213, 1006, 13, 'Blackmagic Pocket Cinema 6K', 'blackmagic-pocket-cinema-6k', 'Blackmagic Pocket Cinema Camera 6K. Professional videography.', 'like_new', 'hybrid', NULL, 1250000.00, 1450000.00, 1250000.00, 25000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 1650000.00, 1, 178, 14, 1, 1, 'active'),

-- FASHION
(1214, 1007, 15, 'Complete Kaftan Set — Cream', 'complete-kaftan-set-cream', 'Cream Kaftan 3-piece set. Custom tailored. Size L.', 'new', 'fixed', 42000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1215, 1007, 15, 'Cashmere Sweater — Navy', 'cashmere-sweater-navy', 'Navy blue cashmere sweater. Pure cashmere. Size M.', 'like_new', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1216, 1007, 16, 'Ankara Peplum Top', 'ankara-peplum-top', 'Ankara peplum top. Multi-colored. Size 10. New with tags.', 'new', 'fixed', 18000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1217, 1007, 16, 'Silk Evening Dress — Red', 'silk-evening-dress-red', 'Red silk evening dress. Elegant. Size 8. Worn once.', 'like_new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1218, 1007, 16, 'Aso-Oke Two-Piece — Green', 'aso-oke-two-piece-green', 'Green Aso-Oke 2-piece set. Handwoven. Premium quality.', 'new', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 1, 'active'),
(1219, 1007, 17, 'Kids Easter Outfit Set', 'kids-easter-outfit-set', 'Easter outfit set for kids. Age 5. Pastel colors.', 'new', 'fixed', 15000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 45, 2, 1, 0, 'active'),
(1220, 1007, 17, 'Baby Boy Romper Set', 'baby-boy-romper-set', 'Baby boy romper set. 6-9 months. Never worn.', 'new', 'fixed', 8500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 34, 1, 1, 0, 'active'),
(1221, 1007, 18, 'Converse Chuck Taylor — 41', 'converse-chuck-taylor-41', 'Converse Chuck Taylor All Star. High-top. Size 41.', 'good', 'fixed', 28000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1222, 1007, 18, 'Vans Old Skool — 40', 'vans-old-skool-40', 'Vans Old Skool sneakers. Black/White. Size 40.', 'like_new', 'fixed', 32000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1223, 1007, 18, 'Birkenstock Sandals — 42', 'birkenstock-sandals-42', 'Birkenstock Arizona sandals. Size 42. Brown leather.', 'good', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1224, 1007, 54, 'Lacoste Polo — Men L', 'lacoste-polo-men-l', 'Lacoste polo shirt. Green crocodile. Authentic. Size L.', 'like_new', 'fixed', 28000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1225, 1007, 54, 'Armani Jeans — Size 32', 'armani-jeans-size-32', 'Armani Jeans. Dark blue. Size 32. Excellent condition.', 'good', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1226, 1007, 55, 'Zara Blazer — Women M', 'zara-blazer-women-m', 'Zara blazer. Black. Size M. Great for office wear.', 'like_new', 'fixed', 25000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1227, 1007, 55, 'Coach Handbag — Vintage', 'coach-handbag-vintage', 'Coach leather handbag. Vintage style. Excellent condition.', 'like_new', 'fixed', 125000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 1, 'active'),
(1228, 1007, 56, 'Kids Christmas Outfit', 'kids-christmas-outfit', 'Christmas outfit for kids. Age 4. Red and green. New.', 'new', 'fixed', 12000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 34, 1, 1, 0, 'active'),
(1229, 1007, 16, 'Custom Wedding Gown', 'custom-wedding-gown', 'Custom-designed wedding gown. Ivory. Size 10. Never worn.', 'new', 'auction', NULL, 425000.00, 550000.00, 425000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 12 DAY), 1, NULL, 1, 345, 31, 1, 1, 'active'),
(1230, 1007, 18, 'Louis Vuitton Sneakers — 42', 'louis-vuitton-sneakers-42', 'Louis Vuitton sneakers. Size 42. Authentic with box.', 'like_new', 'auction', NULL, 285000.00, 350000.00, 285000.00, 10000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, NULL, 1, 234, 21, 1, 1, 'active'),
(1231, 1007, 15, 'Designer Agbada Set', 'designer-agbada-set', 'Designer Agbada 3-piece set. Royal blue with silver embroidery.', 'new', 'hybrid', NULL, 125000.00, 165000.00, 125000.00, 5000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, 185000.00, 1, 234, 21, 1, 1, 'active'),

-- FURNITURE
(1232, 1008, 60, 'Side Table — Glass', 'side-table-glass', 'Glass side table. Chrome frame. Modern design.', 'like_new', 'fixed', 35000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1233, 1008, 61, 'Bunk Bed with Desk', 'bunk-bed-with-desk', 'Bunk bed with built-in desk and shelves. Great for students.', 'good', 'fixed', 145000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 1, 'active'),
(1234, 1008, 60, 'Corner Sofa — L-shape', 'corner-sofa-l-shape', 'L-shaped corner sofa. Grey fabric. Seats 5. Very comfortable.', 'good', 'fixed', 265000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1235, 1008, 61, 'King Bed with Storage', 'king-bed-with-storage', 'King bed with storage drawers under. Wooden frame.', 'good', 'fixed', 185000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1236, 1008, 60, 'Console Table — Entry Way', 'console-table-entry-way', 'Console table for entryway. Wood and metal. 120cm.', 'like_new', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1237, 1008, 61, 'Bedside Table — Pair', 'bedside-table-pair', 'Pair of bedside tables. Wood. 2 drawers each. Excellent.', 'good', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1238, 1008, 60, 'Reception Desk — Modern', 'reception-desk-modern', 'Modern reception desk. Curved design. Front counter.', 'good', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1239, 1008, 61, 'Wall Mirror — Large', 'wall-mirror-large', 'Large wall mirror. Ornate frame. 120cm x 80cm.', 'good', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1240, 1008, 60, 'Dining Chair Set — 6', 'dining-chair-set-6', 'Set of 6 dining chairs. Wooden. Padded seats. Dark brown.', 'good', 'fixed', 145000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1241, 1008, 61, 'Bunk Bed — Wooden', 'bunk-bed-wooden', 'Wooden bunk bed. Solid wood. Fits 2 singles.', 'good', 'fixed', 95000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1242, 1008, 60, 'Executive Desk Suite', 'executive-desk-suite', 'Executive desk with hutch. 200cm x 100cm. Mahogany finish.', 'good', 'auction', NULL, 245000.00, 325000.00, 245000.00, 10000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, NULL, 1, 145, 11, 1, 1, 'active'),
(1243, 1008, 61, 'Vintage Writing Desk', 'vintage-writing-desk', 'Vintage writing desk. Victorian style. Secret compartments.', 'fair', 'auction', NULL, 145000.00, 185000.00, 145000.00, 5000.00, NOW(), DATE_ADD(NOW(), INTERVAL 11 DAY), 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1244, 1008, 60, 'Italian Leather Sofa — Cream', 'italian-leather-sofa-cream', 'Italian leather sofa. Cream. 3-seater. Made in Italy.', 'like_new', 'hybrid', NULL, 385000.00, 465000.00, 385000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 525000.00, 1, 234, 21, 1, 1, 'active'),

-- BOOKS
(1245, 1009, 57, 'No Longer at Ease — Achebe', 'no-longer-at-ease-achebe', 'No Longer at Ease by Chinua Achebe. Classic Nigerian novel.', 'good', 'fixed', 3000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1246, 1009, 57, 'Arrow of God — Achebe', 'arrow-of-god-achebe', 'Arrow of God by Chinua Achebe. Classic African literature.', 'good', 'fixed', 3200.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1247, 1009, 57, 'The Joys of Motherhood', 'joys-of-motherhood', 'The Joys of Motherhood by Buchi Emecheta. Essential reading.', 'good', 'fixed', 3500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1248, 1009, 57, 'Efuru — Nwapa', 'efuru-nwapa', 'Efuru by Flora Nwapa. First novel by a Nigerian woman.', 'good', 'fixed', 3800.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1249, 1009, 57, 'Akata Witch — Okorafor', 'akata-witch-okorafor', 'Akata Witch by Nnedi Okorafor. Fantasy young adult novel.', 'like_new', 'fixed', 4500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1250, 1009, 57, 'Introduction to CS — Bida Poly', 'introduction-to-cs-bida-poly', 'Introduction to Computer Science textbook. Bida Poly syllabus.', 'good', 'fixed', 5500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 1, 'active'),
(1251, 1009, 57, 'Calculus — Stewart 8th Ed', 'calculus-stewart-8th-ed', 'Calculus by James Stewart. 8th edition. Standard university text.', 'good', 'fixed', 15000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1252, 1009, 57, 'Physics — Halliday Resnick', 'physics-halliday-resnick', 'Physics by Halliday, Resnick and Walker. 10th edition.', 'good', 'fixed', 13500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1253, 1009, 57, 'Statistical Methods — Bida Poly', 'statistical-methods-bida-poly', 'Statistical Methods textbook. HND level. Bida Poly.', 'good', 'fixed', 6500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1254, 1009, 57, 'HBR Guide Collection', 'hbr-guide-collection', 'Harvard Business Review guides. Set of 5. Business essential.', 'like_new', 'fixed', 28000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1255, 1009, 59, 'Business Day Newspaper Bundle', 'business-day-newspaper-bundle', 'Bundle of 30 Business Day newspapers. 2023-2024.', 'good', 'fixed', 8500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 56, 3, 1, 0, 'active'),
(1256, 1009, 59, 'Reader\'s Digest Collection', 'readers-digest-collection', 'Collection of 15 Reader\'s Digest magazines. Vintage.', 'fair', 'fixed', 12000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 45, 2, 1, 0, 'active'),
(1257, 1009, 59, 'Ovation Magazine Bundle', 'ovation-magazine-bundle', 'Bundle of 12 Ovation magazines. Nigerian lifestyle.', 'good', 'fixed', 14000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 56, 3, 1, 0, 'active'),
(1258, 1009, 57, 'Signed Wole Soyinka Novel', 'signed-wole-soyinka-novel', 'Signed Wole Soyinka novel. First edition. With COA.', 'like_new', 'auction', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 1, NULL, 1, 234, 21, 1, 1, 'active'),
(1259, 1009, 57, 'Complete Encyclopaedia Set', 'complete-encyclopaedia-set', 'Complete Encyclopaedia Britannica set. 32 volumes. Mint condition.', 'like_new', 'hybrid', NULL, 285000.00, 385000.00, 285000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 450000.00, 1, 178, 14, 1, 1, 'active'),

-- COLLECTIBLES
(1260, 1006, 69, 'Vintage Pocket Watch', 'vintage-pocket-watch', 'Vintage gold-plated pocket watch. 1940s. Working condition.', 'good', 'fixed', 125000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1261, 1006, 69, 'Old Framed Photographs', 'old-framed-photographs', 'Set of 5 old framed photographs. 1950s-1970s. Nigerian scenes.', 'fair', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1262, 1006, 68, 'Handwoven Wall Hanging', 'handwoven-wall-hanging', 'Handwoven wall hanging. Traditional Nigerian pattern.', 'new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1263, 1006, 68, 'Abstract African Painting', 'abstract-african-painting', 'Abstract painting by Nigerian artist. Acrylic. 70cm x 50cm.', 'new', 'fixed', 185000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 1, 'active'),
(1264, 1006, 69, 'Vintage Nollywood Poster', 'vintage-nollywood-poster', 'Vintage 1980s Nollywood movie poster. Framed. Rare.', 'fair', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1265, 1006, 69, 'Antique Wooden Bowl', 'antique-wooden-bowl', 'Antique hand-carved wooden bowl. Benue region. 1930s.', 'good', 'fixed', 75000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1266, 1006, 68, 'Bronze Head Sculpture', 'bronze-head-sculpture', 'Bronze head sculpture. Modern Nigerian artist. 40cm tall.', 'new', 'fixed', 285000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 10, 1, 0, 'active'),
(1267, 1006, 68, 'Ceremonial Mask', 'ceremonial-mask', 'Authentic ceremonial mask. Cross River origin. Wood.', 'fair', 'fixed', 145000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1268, 1006, 69, 'Framed Vintage Currency', 'framed-vintage-currency', 'Framed set of vintage West African currency. Beautiful display.', 'good', 'auction', NULL, 125000.00, 165000.00, 125000.00, 5000.00, NOW(), DATE_ADD(NOW(), INTERVAL 8 DAY), 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1269, 1006, 68, 'Rare Nigerian Art Book', 'rare-nigerian-art-book', 'Rare out-of-print book on Nigerian art history. 1960s.', 'good', 'hybrid', NULL, 125000.00, 175000.00, 125000.00, 5000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, 195000.00, 1, 123, 9, 1, 1, 'active'),

-- VEHICLES
(1270, 1008, 70, 'Peugeot 406 2001', 'peugeot-406-2001', 'Peugeot 406 2001. Manual. Clean. New engine.', 'fair', 'fixed', 1250000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1271, 1008, 70, 'Toyota Matrix 2005', 'toyota-matrix-2005', 'Toyota Matrix 2005. Hatchback. Clean title. AC working.', 'good', 'fixed', 2850000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 0, 'active'),
(1272, 1008, 70, 'Mazda CX-5 2016', 'mazda-cx-5-2016', 'Mazda CX-5 2016. SUV. AWD. Leather interior. Sunroof.', 'like_new', 'fixed', 8500000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1273, 1008, 70, 'Hyundai Tucson 2015', 'hyundai-tucson-2015', 'Hyundai Tucson 2015. SUV. Petrol. Full AC. First body.', 'good', 'fixed', 6500000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 0, 'active'),
(1274, 1008, 70, 'Kia Rio 2014', 'kia-rio-2014', 'Kia Rio 2014. Compact sedan. Excellent fuel economy.', 'good', 'fixed', 3850000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1275, 1008, 70, 'Honda CR-V 2013', 'honda-cr-v-2013', 'Honda CR-V 2013. SUV. AWD. Clean. Full option.', 'good', 'fixed', 5500000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 189, 15, 1, 0, 'active'),
(1276, 1008, 70, 'Toyota Land Cruiser 2016', 'toyota-land-cruiser-2016', 'Toyota Land Cruiser 2016. Full option. Armored option available.', 'like_new', 'auction', NULL, 35000000.00, 45000000.00, 35000000.00, 500000.00, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY), 1, NULL, 1, 567, 52, 1, 1, 'active'),
(1277, 1008, 70, 'Range Rover Sport 2015', 'range-rover-sport-2015', 'Range Rover Sport 2015. Diesel. Fully loaded. New tires.', 'good', 'auction', NULL, 25000000.00, 32000000.00, 25000000.00, 300000.00, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 1, NULL, 1, 456, 42, 1, 1, 'active'),
(1278, 1008, 70, 'Mercedes-Benz GLE 2017', 'mercedes-benz-gle-2017', 'Mercedes-Benz GLE 2017. SUV. Diesel. First body. First-class condition.', 'like_new', 'hybrid', NULL, 28500000.00, 35000000.00, 28500000.00, 300000.00, NOW(), DATE_ADD(NOW(), INTERVAL 12 DAY), 1, 38000000.00, 1, 345, 31, 1, 1, 'active'),

-- SPORTS
(1279, 1008, 65, 'CrossFit Equipment Bundle', 'crossfit-equipment-bundle', 'CrossFit bundle. Rope, box, kettlebell, resistance bands.', 'good', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1280, 1008, 65, 'Weight Bench — Adjustable', 'weight-bench-adjustable', 'Adjustable weight bench. Incline/decline. Foldable.', 'like_new', 'fixed', 95000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1281, 1008, 67, 'Volleyball — Official', 'volleyball-official', 'Official size volleyball. Mikasa brand. Excellent.', 'good', 'fixed', 15000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1282, 1008, 67, 'Cricket Helmet', 'cricket-helmet', 'Cricket helmet with face guard. Size M. Professional grade.', 'good', 'fixed', 35000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 56, 3, 1, 0, 'active'),
(1283, 1008, 67, 'Boxing Punching Bag', 'boxing-punching-bag', 'Heavy punching bag. 4ft. With gloves. Excellent condition.', 'good', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1284, 1008, 65, 'Yoga Blocks Set', 'yoga-blocks-set', 'Set of 4 yoga blocks. Cork. Non-slip. Natural.', 'new', 'fixed', 12000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1285, 1008, 66, 'Camping Sleeping Pad', 'camping-sleeping-pad', 'Inflatable camping sleeping pad. Self-inflating. Compact.', 'like_new', 'fixed', 25000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1286, 1008, 66, 'Camping Lantern', 'camping-lantern', 'LED camping lantern. Rechargeable. Waterproof. Bright.', 'new', 'fixed', 18000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1287, 1008, 65, 'Professional Gym Set', 'professional-gym-set', 'Complete professional gym set. Barbell, plates, bench, rack.', 'good', 'auction', NULL, 385000.00, 465000.00, 385000.00, 15000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, NULL, 1, 234, 21, 1, 1, 'active'),
(1288, 1008, 67, 'Tournament Cricket Kit', 'tournament-cricket-kit', 'Complete tournament cricket kit. Batsman, wicketkeeper, bowler.', 'like_new', 'hybrid', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 9 DAY), 1, 285000.00, 1, 178, 14, 1, 1, 'active'),

-- TOYS
(1289, 1008, 62, 'Jenga — Giant Outdoor', 'jenga-giant-outdoor', 'Giant Jenga game. Outdoor size. Perfect for parties.', 'like_new', 'fixed', 25000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 78, 5, 1, 0, 'active'),
(1290, 1008, 62, 'Uno Card Game', 'uno-card-game', 'Uno card game. Original. Complete deck.', 'good', 'fixed', 5500.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1291, 1008, 62, 'Catan Board Game', 'catan-board-game', 'Settlers of Catan. Excellent strategy game. Complete.', 'like_new', 'fixed', 22000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1292, 1008, 63, 'PlayStation 5 DualSense Controller', 'playstation-5-dualsense-controller', 'PS5 DualSense wireless controller. White. Like new.', 'like_new', 'fixed', 45000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active'),
(1293, 1008, 63, 'Nintendo Switch Games Bundle', 'nintendo-switch-games-bundle', 'Bundle of 5 Nintendo Switch games. Mixed titles.', 'good', 'fixed', 65000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 123, 9, 1, 0, 'active'),
(1294, 1008, 63, 'Xbox Series X Console', 'xbox-series-x-console', 'Xbox Series X console. 1TB. With 2 controllers. Excellent.', 'like_new', 'fixed', 585000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1295, 1008, 64, 'Kids Bicycle — Ages 5-8', 'kids-bicycle-ages-5-8', 'Kids bicycle. Ages 5-8. 16-inch wheels. Training wheels included.', 'good', 'fixed', 35000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 67, 4, 1, 0, 'active'),
(1296, 1008, 64, 'Water Slide — Inflatable', 'water-slide-inflatable', 'Inflatable water slide for kids. Ages 4-10. Used once.', 'like_new', 'fixed', 85000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 89, 6, 1, 0, 'active'),
(1297, 1008, 63, 'Xbox One X Bundle', 'xbox-one-x-bundle', 'Xbox One X console with 5 games. Excellent condition.', 'good', 'auction', NULL, 285000.00, 350000.00, 285000.00, 10000.00, NOW(), DATE_ADD(NOW(), INTERVAL 8 DAY), 1, NULL, 1, 178, 14, 1, 0, 'active'),
(1298, 1008, 63, 'Rare Vintage Atari', 'rare-vintage-atari', 'Vintage Atari 2600 console. Working. With 10 games.', 'good', 'hybrid', NULL, 185000.00, 245000.00, 185000.00, 8000.00, NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 1, 285000.00, 1, 145, 11, 1, 0, 'active'),

-- HOME & GARDEN
(1299, 1008, 60, 'Stand Mixer — KitchenAid', 'stand-mixer-kitchenaid', 'KitchenAid stand mixer. 5-quart. Red. With attachments.', 'like_new', 'fixed', 185000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 234, 19, 1, 1, 'active'),
(1300, 1008, 60, 'Air Fryer — 5L', 'air-fryer-5l', '5-litre air fryer. Digital display. 6 preset modes.', 'like_new', 'fixed', 55000.00, NULL, NULL, NULL, 1.00, NULL, NULL, 1, NULL, 1, 145, 11, 1, 0, 'active');