-- marketplace/sql/update-image-urls-2.sql
-- Keyword-matched LoremFlickr URLs for listings 1201-1400

USE `marketplace_db`;

-- ============================================
-- BATCH: Listings 1201-1300
-- ============================================

UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,vivo' WHERE listing_id = 1201;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,realme' WHERE listing_id = 1202;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gaming,laptop' WHERE listing_id = 1203;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/surface,laptop' WHERE listing_id = 1204;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/camera,mirrorless' WHERE listing_id = 1205;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/camera,nikon' WHERE listing_id = 1206;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/speaker,bose' WHERE listing_id = 1207;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/speaker,sonos' WHERE listing_id = 1208;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/ipad,tablet' WHERE listing_id = 1209;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/tablet,amazon' WHERE listing_id = 1210;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gaming,laptop' WHERE listing_id = 1211;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/samsung,smartphone' WHERE listing_id = 1212;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/camera,cinema' WHERE listing_id = 1213;

-- Fashion 1214-1231
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kaftan,menswear' WHERE listing_id = 1214;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sweater,cashmere' WHERE listing_id = 1215;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/ankara,top' WHERE listing_id = 1216;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/eveningdress,silk' WHERE listing_id = 1217;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/aso-oke,african' WHERE listing_id = 1218;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kidsoutfit,children' WHERE listing_id = 1219;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/babyclothes,infant' WHERE listing_id = 1220;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/converse,sneakers' WHERE listing_id = 1221;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/vans,sneakers' WHERE listing_id = 1222;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sandals,birkenstock' WHERE listing_id = 1223;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/polo,lacoste' WHERE listing_id = 1224;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/jeans,armani' WHERE listing_id = 1225;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/blazer,zara' WHERE listing_id = 1226;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/handbag,coach' WHERE listing_id = 1227;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kidsoutfit,christmas' WHERE listing_id = 1228;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/weddingdress,bridal' WHERE listing_id = 1229;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/lvsneakers,designer' WHERE listing_id = 1230;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/agbada,menswear' WHERE listing_id = 1231;

-- Furniture 1232-1244
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sidetable,furniture' WHERE listing_id = 1232;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bunkbed,student' WHERE listing_id = 1233;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/cornersofa,furniture' WHERE listing_id = 1234;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kingbed,furniture' WHERE listing_id = 1235;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/consoletable,furniture' WHERE listing_id = 1236;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/nightstand,furniture' WHERE listing_id = 1237;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/receptiondesk,office' WHERE listing_id = 1238;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/mirror,furniture' WHERE listing_id = 1239;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/diningchairs,furniture' WHERE listing_id = 1240;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bunkbed,wooden' WHERE listing_id = 1241;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/executivedesk,office' WHERE listing_id = 1242;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/vintagedesk,antique' WHERE listing_id = 1243;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/leathersofa,italian' WHERE listing_id = 1244;

-- Books 1245-1259
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,african' WHERE listing_id = 1245;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,african' WHERE listing_id = 1246;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,african' WHERE listing_id = 1247;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,african' WHERE listing_id = 1248;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,fantasy' WHERE listing_id = 1249;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,computer' WHERE listing_id = 1250;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,calculus' WHERE listing_id = 1251;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,physics' WHERE listing_id = 1252;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/textbook,statistics' WHERE listing_id = 1253;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/business,book' WHERE listing_id = 1254;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/newspaper,business' WHERE listing_id = 1255;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/magazine,readers' WHERE listing_id = 1256;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/magazine,lifestyle' WHERE listing_id = 1257;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/book,signed' WHERE listing_id = 1258;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/encyclopedia,book' WHERE listing_id = 1259;

-- Collectibles 1260-1269
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/pocketwatch,vintage' WHERE listing_id = 1260;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/photograph,vintage' WHERE listing_id = 1261;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/wallhanging,textile' WHERE listing_id = 1262;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/painting,abstract' WHERE listing_id = 1263;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/poster,vintage' WHERE listing_id = 1264;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/woodenbowl,carving' WHERE listing_id = 1265;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bronze,sculpture' WHERE listing_id = 1266;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/africanmask,ceremonial' WHERE listing_id = 1267;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/currency,collection' WHERE listing_id = 1268;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/artbook,book' WHERE listing_id = 1269;

-- Vehicles 1270-1278
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/peugeot,car' WHERE listing_id = 1270;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/toyota,matrix' WHERE listing_id = 1271;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/mazda,suv' WHERE listing_id = 1272;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/hyundai,suv' WHERE listing_id = 1273;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kia,car' WHERE listing_id = 1274;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/honda,crv' WHERE listing_id = 1275;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/landcruiser,toyota' WHERE listing_id = 1276;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/rangerover,suv' WHERE listing_id = 1277;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/mercedes,gle' WHERE listing_id = 1278;

-- Sports 1279-1288
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/crossfit,gym' WHERE listing_id = 1279;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/weightbench,gym' WHERE listing_id = 1280;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/volleyball,sports' WHERE listing_id = 1281;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/crickethelem,sports' WHERE listing_id = 1282;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/punchingbag,boxing' WHERE listing_id = 1283;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/yogablocks,yoga' WHERE listing_id = 1284;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sleepingpad,camping' WHERE listing_id = 1285;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/lantern,camping' WHERE listing_id = 1286;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/homegym,equipment' WHERE listing_id = 1287;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/cricket,kit' WHERE listing_id = 1288;

-- Toys 1289-1298
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/jenga,boardgame' WHERE listing_id = 1289;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/uno,cardgame' WHERE listing_id = 1290;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/catan,boardgame' WHERE listing_id = 1291;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/ps5,controller' WHERE listing_id = 1292;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/nintendo,games' WHERE listing_id = 1293;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/xbox,gaming' WHERE listing_id = 1294;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bicycle,kids' WHERE listing_id = 1295;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/waterslide,kids' WHERE listing_id = 1296;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/xbox,gaming' WHERE listing_id = 1297;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/atari,retrogame' WHERE listing_id = 1298;

-- Home & Garden 1299-1300
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/standmixer,kitchen' WHERE listing_id = 1299;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/airfryer,kitchen' WHERE listing_id = 1300;

-- ============================================
-- BATCH: Listings 1301-1400
-- ============================================

UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/ricecooker,kitchen' WHERE listing_id = 1301;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kettle,kitchen' WHERE listing_id = 1302;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/blender,kitchen' WHERE listing_id = 1303;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/microwave,kitchen' WHERE listing_id = 1304;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gascooker,kitchen' WHERE listing_id = 1305;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/refrigerator,kitchen' WHERE listing_id = 1306;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/washingmachine,appliance' WHERE listing_id = 1307;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gardenhose,garden' WHERE listing_id = 1308;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/lawnmower,garden' WHERE listing_id = 1309;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gardentools,garden' WHERE listing_id = 1310;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/waterpump,equipment' WHERE listing_id = 1311;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/vacuumcleaner,appliance' WHERE listing_id = 1312;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kitchen,set' WHERE listing_id = 1313;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/generator,power' WHERE listing_id = 1314;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/diningset,furniture' WHERE listing_id = 1315;

-- Health & Beauty 1316-1335
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/skincare,beauty' WHERE listing_id = 1316;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/serum,skincare' WHERE listing_id = 1317;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/facemask,skincare' WHERE listing_id = 1318;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/makeup,cosmetics' WHERE listing_id = 1319;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/lipstick,makeup' WHERE listing_id = 1320;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/foundation,makeup' WHERE listing_id = 1321;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/massagegun,wellness' WHERE listing_id = 1322;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/yoga,mat' WHERE listing_id = 1323;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/meditation,cushion' WHERE listing_id = 1324;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/dogfood,pet' WHERE listing_id = 1325;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/dogcollar,pet' WHERE listing_id = 1326;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/dogbed,pet' WHERE listing_id = 1327;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/catlitterbox,pet' WHERE listing_id = 1328;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/cattree,pet' WHERE listing_id = 1329;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/catfood,pet' WHERE listing_id = 1330;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/hamstercage,pet' WHERE listing_id = 1331;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/fishtank,aquarium' WHERE listing_id = 1332;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/birdcage,pet' WHERE listing_id = 1333;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/skincare,cream' WHERE listing_id = 1334;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/makeup,bridal' WHERE listing_id = 1335;

-- Music & Other 1336-1346
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/keyboard,piano' WHERE listing_id = 1336;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/acousticguitar,guitar' WHERE listing_id = 1337;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/electricguitar,guitar' WHERE listing_id = 1338;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/drumset,drums' WHERE listing_id = 1339;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/microphone,studio' WHERE listing_id = 1340;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/audiomixer,studio' WHERE listing_id = 1341;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/inverter,power' WHERE listing_id = 1342;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/solarpanel,solar' WHERE listing_id = 1343;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/weldingmachine,welding' WHERE listing_id = 1344;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/studio,setup' WHERE listing_id = 1345;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/keyboard,piano' WHERE listing_id = 1346;

-- Electronics 1347-1353
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,tecno' WHERE listing_id = 1347;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/smartphone,itel' WHERE listing_id = 1348;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/laptop,hp' WHERE listing_id = 1349;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/instaxcamera,camera' WHERE listing_id = 1350;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/speaker,jbl' WHERE listing_id = 1351;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/nokia,phone' WHERE listing_id = 1352;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/speaker,sonos' WHERE listing_id = 1353;

-- Fashion 1354-1360
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kaftan,menswear' WHERE listing_id = 1354;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/ankara,skirt' WHERE listing_id = 1355;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/weddingdress,lace' WHERE listing_id = 1356;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kidssuit,formal' WHERE listing_id = 1357;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/balenciaga,sneakers' WHERE listing_id = 1358;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/weddingdress,designer' WHERE listing_id = 1359;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sneakers,limited' WHERE listing_id = 1360;

-- Furniture 1361-1366
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/rockingchair,furniture' WHERE listing_id = 1361;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/babycot,bedroom' WHERE listing_id = 1362;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/patioset,outdoor' WHERE listing_id = 1363;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/studydesk,furniture' WHERE listing_id = 1364;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/bamboo,furniture' WHERE listing_id = 1365;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/platformbed,furniture' WHERE listing_id = 1366;

-- Books 1367-1373
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/shakespeare,book' WHERE listing_id = 1367;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,book' WHERE listing_id = 1368;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/business,book' WHERE listing_id = 1369;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/dictionary,book' WHERE listing_id = 1370;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/novel,book' WHERE listing_id = 1371;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/african,book' WHERE listing_id = 1372;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/rarebook,book' WHERE listing_id = 1373;

-- Collectibles 1374-1379
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/beerstein,vintage' WHERE listing_id = 1374;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/ceramicvase,vase' WHERE listing_id = 1375;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/cigarbox,vintage' WHERE listing_id = 1376;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/typewriter,vintage' WHERE listing_id = 1377;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/sculpture,modern' WHERE listing_id = 1378;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/document,historical' WHERE listing_id = 1379;

-- Vehicles 1380-1384
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/toyota,minivan' WHERE listing_id = 1380;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kia,optima' WHERE listing_id = 1381;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/toyota,avalon' WHERE listing_id = 1382;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/volvo,suv' WHERE listing_id = 1383;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/audi,suv' WHERE listing_id = 1384;

-- Sports 1385-1390
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/tabletennis,robot' WHERE listing_id = 1385;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/pilates,reformer' WHERE listing_id = 1386;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/weightedvest,fitness' WHERE listing_id = 1387;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gymmats,fitness' WHERE listing_id = 1388;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/surfboard,surfing' WHERE listing_id = 1389;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/homegym,equipment' WHERE listing_id = 1390;

-- Toys 1391-1396
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/rubikscube,puzzle' WHERE listing_id = 1391;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/kiddiepool,kids' WHERE listing_id = 1392;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/dnd,boardgame' WHERE listing_id = 1393;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/vrheadset,gaming' WHERE listing_id = 1394;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gamingpc,computer' WHERE listing_id = 1395;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/lego,toys' WHERE listing_id = 1396;

-- Final Electronics 1397-1400
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/iphone,smartphone' WHERE listing_id = 1397;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/gaming,laptop' WHERE listing_id = 1398;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/camera,leica' WHERE listing_id = 1399;
UPDATE listing_images SET image_url = 'https://loremflickr.com/800/600/macbook,laptop' WHERE listing_id = 1400;