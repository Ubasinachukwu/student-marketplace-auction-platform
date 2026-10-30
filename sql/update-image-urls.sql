-- marketplace/sql/update-image-urls.sql
-- Replace picsum.photos URLs with keyword-matched LoremFlickr URLs
-- Only updates listing_id >= 1001 (our seed data — leaves existing rows alone)

USE `marketplace_db`;

-- ============================================
-- BATCH 1: Listings 1001-1100
-- ============================================

-- Electronics — Smartphones
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,phone' WHERE listing_id = 1001;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/iphone,smartphone' WHERE listing_id = 1002;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,phone' WHERE listing_id = 1003;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,phone' WHERE listing_id = 1004;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,phone' WHERE listing_id = 1005;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,phone' WHERE listing_id = 1006;

-- Electronics — Laptops
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/laptop,computer' WHERE listing_id = 1007;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/laptop,computer' WHERE listing_id = 1008;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/macbook,laptop' WHERE listing_id = 1009;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/laptop,computer' WHERE listing_id = 1010;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/laptop,computer' WHERE listing_id = 1011;

-- Electronics — Cameras
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/camera,dslr' WHERE listing_id = 1012;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/camera,dslr' WHERE listing_id = 1013;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gopro,actioncamera' WHERE listing_id = 1014;

-- Electronics — Audio
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/speaker,audio' WHERE listing_id = 1015;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/headphones,audio' WHERE listing_id = 1016;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/speaker,audio' WHERE listing_id = 1017;

-- Electronics — Tablets
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/ipad,tablet' WHERE listing_id = 1018;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/tablet,samsung' WHERE listing_id = 1019;

-- Electronics — Auctions & Hybrids
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gaming,laptop' WHERE listing_id = 1020;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/iphone,smartphone' WHERE listing_id = 1021;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/camera,mirrorless' WHERE listing_id = 1022;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/macbook,laptop' WHERE listing_id = 1023;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/headphones,audio' WHERE listing_id = 1024;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/samsung,smartphone' WHERE listing_id = 1025;

-- Fashion — Men's
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/ankara,africanfashion' WHERE listing_id = 1026;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/agbada,menswear' WHERE listing_id = 1027;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kaftan,menswear' WHERE listing_id = 1028;

-- Fashion — Women's
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/ankara,dress' WHERE listing_id = 1029;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/lace,blouse' WHERE listing_id = 1030;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/aso-oke,african' WHERE listing_id = 1031;

-- Fashion — Kids
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kidsclothing,children' WHERE listing_id = 1032;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kidsdress,children' WHERE listing_id = 1033;

-- Fashion — Shoes
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sneakers,nike' WHERE listing_id = 1034;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sneakers,adidas' WHERE listing_id = 1035;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/boots,timberland' WHERE listing_id = 1036;

-- Fashion — Branded
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/polo,shirt' WHERE listing_id = 1037;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/handbag,leather' WHERE listing_id = 1038;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kidsshoes,children' WHERE listing_id = 1039;

-- Fashion — Auctions & Hybrids
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/fabric,textile' WHERE listing_id = 1040;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/loafers,leather' WHERE listing_id = 1041;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kaftan,menswear' WHERE listing_id = 1042;

-- Furniture — Sofas
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sofa,couch' WHERE listing_id = 1043;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/chair,recliner' WHERE listing_id = 1044;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/officechair,office' WHERE listing_id = 1045;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/diningtable,furniture' WHERE listing_id = 1046;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bookshelf,furniture' WHERE listing_id = 1047;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/coffeetable,furniture' WHERE listing_id = 1048;

-- Furniture — Beds
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bed,bedroom' WHERE listing_id = 1049;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/wardrobe,furniture' WHERE listing_id = 1050;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/mattress,bed' WHERE listing_id = 1051;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/armchair,furniture' WHERE listing_id = 1052;

-- Furniture — Auctions & Hybrids
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sofa,lshape' WHERE listing_id = 1053;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/desk,office' WHERE listing_id = 1054;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/desk,furniture' WHERE listing_id = 1055;

-- Books
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,books' WHERE listing_id = 1056;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/harrypotter,books' WHERE listing_id = 1057;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,study' WHERE listing_id = 1058;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,math' WHERE listing_id = 1059;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,classic' WHERE listing_id = 1060;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,chemistry' WHERE listing_id = 1061;

-- Books — Magazines
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/magazine,reading' WHERE listing_id = 1062;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/magazine,newspaper' WHERE listing_id = 1063;

-- Books — Reference
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/dictionary,book' WHERE listing_id = 1064;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,computer' WHERE listing_id = 1065;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,book' WHERE listing_id = 1066;

-- Books — Auctions & Hybrids
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/comics,book' WHERE listing_id = 1067;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,law' WHERE listing_id = 1068;

-- Collectibles
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/currency,oldmoney' WHERE listing_id = 1069;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/africancarving,woodcarving' WHERE listing_id = 1070;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/oillamp,antique' WHERE listing_id = 1071;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/vinylrecord,records' WHERE listing_id = 1072;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/painting,art' WHERE listing_id = 1073;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sculpture,african' WHERE listing_id = 1074;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/stamp,collection' WHERE listing_id = 1075;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/clock,antique' WHERE listing_id = 1076;

-- Collectibles — Auctions & Hybrids
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/photograph,art' WHERE listing_id = 1077;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bronze,sculpture' WHERE listing_id = 1078;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/book,signed' WHERE listing_id = 1079;

-- Vehicles
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/toyota,car' WHERE listing_id = 1080;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/honda,car' WHERE listing_id = 1081;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kia,car' WHERE listing_id = 1082;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/motorcycle,bike' WHERE listing_id = 1083;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/van,toyota' WHERE listing_id = 1084;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/nissan,car' WHERE listing_id = 1085;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/honda,car' WHERE listing_id = 1086;

-- Vehicles — Auctions & Hybrids
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/toyota,camry' WHERE listing_id = 1087;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/lexus,suv' WHERE listing_id = 1088;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/mercedes,car' WHERE listing_id = 1089;

-- Sports
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/football,soccer' WHERE listing_id = 1090;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/soccer,boots' WHERE listing_id = 1091;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/barbell,gym' WHERE listing_id = 1092;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/dumbbell,fitness' WHERE listing_id = 1093;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/treadmill,gym' WHERE listing_id = 1094;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/tennis,racket' WHERE listing_id = 1095;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/tabletennis,sport' WHERE listing_id = 1096;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/camping,tent' WHERE listing_id = 1097;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sleepingbag,camping' WHERE listing_id = 1098;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/yoga,mat' WHERE listing_id = 1099;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/boxing,gloves' WHERE listing_id = 1100;

-- ============================================
-- BATCH 2: Listings 1101-1200
-- ============================================

UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,xiaomi' WHERE listing_id = 1101;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,oneplus' WHERE listing_id = 1102;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,huawei' WHERE listing_id = 1103;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gaming,laptop' WHERE listing_id = 1104;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/laptop,acer' WHERE listing_id = 1105;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/laptop,hp' WHERE listing_id = 1106;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/camera,mirrorless' WHERE listing_id = 1107;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/drone,dji' WHERE listing_id = 1108;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/speaker,marshall' WHERE listing_id = 1109;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/headphones,beats' WHERE listing_id = 1110;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/surface,tablet' WHERE listing_id = 1111;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,samsung' WHERE listing_id = 1112;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gaming,laptop' WHERE listing_id = 1113;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/camera,panasonic' WHERE listing_id = 1114;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/iphone,smartphone' WHERE listing_id = 1115;

-- Fashion 1116-1133
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gown,dress' WHERE listing_id = 1116;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/blouse,fashion' WHERE listing_id = 1117;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/leatherskirt,fashion' WHERE listing_id = 1118;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/suit,menswear' WHERE listing_id = 1119;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/buba,menswear' WHERE listing_id = 1120;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/leatherbelt,fashion' WHERE listing_id = 1121;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kidssuit,boys' WHERE listing_id = 1122;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/schooluniform,kids' WHERE listing_id = 1123;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sneakers,puma' WHERE listing_id = 1124;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/heels,womensshoes' WHERE listing_id = 1125;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/oxfordshoes,menswear' WHERE listing_id = 1126;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/polo,shirt' WHERE listing_id = 1127;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/jumpsuit,womenswear' WHERE listing_id = 1128;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/blazer,womenswear' WHERE listing_id = 1129;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/winterjacket,kids' WHERE listing_id = 1130;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/weddingdress,bridal' WHERE listing_id = 1131;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/heels,designer' WHERE listing_id = 1132;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/lace,fabric' WHERE listing_id = 1133;

-- Furniture 1134-1146
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bunkbed,furniture' WHERE listing_id = 1134;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/tvstand,furniture' WHERE listing_id = 1135;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/mirror,furniture' WHERE listing_id = 1136;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sofa,recliner' WHERE listing_id = 1137;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/barstool,furniture' WHERE listing_id = 1138;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/nightstand,furniture' WHERE listing_id = 1139;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/filingcabinet,office' WHERE listing_id = 1140;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/dresser,furniture' WHERE listing_id = 1141;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/diningtable,furniture' WHERE listing_id = 1142;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/wardrobe,furniture' WHERE listing_id = 1143;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/officesuite,furniture' WHERE listing_id = 1144;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bed,antique' WHERE listing_id = 1145;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/accentchair,furniture' WHERE listing_id = 1146;

-- Books 1147-1160
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,african' WHERE listing_id = 1147;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,african' WHERE listing_id = 1148;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,african' WHERE listing_id = 1149;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,math' WHERE listing_id = 1150;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,chemistry' WHERE listing_id = 1151;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,economics' WHERE listing_id = 1152;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,book' WHERE listing_id = 1153;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/selfhelp,book' WHERE listing_id = 1154;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,computer' WHERE listing_id = 1155;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/magazine,vogue' WHERE listing_id = 1156;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/magazine,newspaper' WHERE listing_id = 1157;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/magazine,business' WHERE listing_id = 1158;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/poetry,book' WHERE listing_id = 1159;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bible,book' WHERE listing_id = 1160;

-- Collectibles 1161-1171
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/coins,collection' WHERE listing_id = 1161;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/radio,vintage' WHERE listing_id = 1162;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/painting,african' WHERE listing_id = 1163;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/africanmask,wood' WHERE listing_id = 1164;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/postcards,vintage' WHERE listing_id = 1165;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/silver,cutlery' WHERE listing_id = 1166;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/football,jersey' WHERE listing_id = 1167;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/map,vintage' WHERE listing_id = 1168;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/comic,book' WHERE listing_id = 1169;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/photograph,art' WHERE listing_id = 1170;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/stamp,collection' WHERE listing_id = 1171;

-- Vehicles 1172-1179
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/toyota,suv' WHERE listing_id = 1172;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/ford,explorer' WHERE listing_id = 1173;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/hyundai,car' WHERE listing_id = 1174;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/volkswagen,car' WHERE listing_id = 1175;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/minivan,van' WHERE listing_id = 1176;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/pickup,truck' WHERE listing_id = 1177;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/honda,suv' WHERE listing_id = 1178;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bmw,suv' WHERE listing_id = 1179;

-- Sports 1180-1190
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/spinbike,gym' WHERE listing_id = 1180;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kettlebell,gym' WHERE listing_id = 1181;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/resistanceband,fitness' WHERE listing_id = 1182;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/rowing,gym' WHERE listing_id = 1183;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/cricket,bat' WHERE listing_id = 1184;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/badminton,racket' WHERE listing_id = 1185;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/basketball,sports' WHERE listing_id = 1186;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/backpack,hiking' WHERE listing_id = 1187;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/campingstove,camping' WHERE listing_id = 1188;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gym,equipment' WHERE listing_id = 1189;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/cricket,kit' WHERE listing_id = 1190;

-- Toys 1191-1200
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/chess,board' WHERE listing_id = 1191;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/scrabble,boardgame' WHERE listing_id = 1192;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/monopoly,boardgame' WHERE listing_id = 1193;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/playstation,gaming' WHERE listing_id = 1194;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/xbox,gaming' WHERE listing_id = 1195;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/nintendo,gaming' WHERE listing_id = 1196;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/trampoline,kids' WHERE listing_id = 1197;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/swingset,kids' WHERE listing_id = 1198;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/ps5,gaming' WHERE listing_id = 1199;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gameboy,retro' WHERE listing_id = 1200;