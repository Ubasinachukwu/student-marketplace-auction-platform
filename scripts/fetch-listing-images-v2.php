<?php
// marketplace/scripts/fetch-listing-images-v2.php
// Second pass — only processes listings still missing real images.
// Uses Pixabay first, falls back to Pexels, then falls back to generic category image.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

// ---- CONFIG ----
$pixabayKey = getenv('PIXABAY_API_KEY') ?: '';
$pexelsKey  = getenv('PEXELS_API_KEY')  ?: '';

if (empty($pixabayKey) && empty($pexelsKey)) {
    die("ERROR: Both PIXABAY_API_KEY and PEXELS_API_KEY are missing from .env\n");
}

$targetDir = __DIR__ . '/../assets/uploads/listings/';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$logFile = __DIR__ . '/../logs/pixabay-fetch-v2.log';
$sleepBetweenRequests = 2; // 30 requests/min — safe for both APIs

// ---- KEYWORD MAP ----
$keywordMap = [
    // Electronics
    'iphone'         => 'iphone',
    'samsung'        => 'samsung phone',
    'tecno'          => 'smartphone',
    'infinix'        => 'smartphone',
    'pixel'          => 'smartphone',
    'oppo'           => 'smartphone',
    'vivo'           => 'smartphone',
    'realme'         => 'smartphone',
    'poco'           => 'smartphone',
    'nokia'          => 'mobile phone',
    'nothing phone'  => 'smartphone',
    'huawei'         => 'smartphone',
    'oneplus'        => 'smartphone',
    'xiaomi'         => 'smartphone',
    'redmi'          => 'smartphone',
    'macbook'        => 'macbook',
    'laptop'         => 'laptop',
    'pavilion'       => 'laptop',
    'latitude'       => 'laptop',
    'thinkpad'       => 'laptop',
    'elitebook'      => 'laptop',
    'vivobook'       => 'laptop',
    'aspire'         => 'laptop',
    'predator'       => 'gaming laptop',
    'razer'          => 'gaming laptop',
    'alienware'      => 'gaming laptop',
    'legion'         => 'gaming laptop',
    'surface'        => 'tablet',
    'ipad'           => 'ipad',
    'tablet'         => 'tablet',
    'camera'         => 'camera',
    'dslr'           => 'dslr',
    'canon'          => 'canon camera',
    'nikon'          => 'nikon camera',
    'sony'           => 'sony',
    'fujifilm'       => 'camera',
    'panasonic'      => 'camera',
    'gopro'          => 'action camera',
    'dji'            => 'drone',
    'instax'         => 'camera',
    'speaker'        => 'speaker',
    'soundbar'       => 'soundbar',
    'headphone'      => 'headphones',
    'airpods'        => 'earbuds',
    'beats'          => 'headphones',
    'sennheiser'     => 'headphones',
    'bose'           => 'speaker',
    'marshall'       => 'speaker',
    'jbl'            => 'speaker',
    'sonos'          => 'speaker',
    'anker'          => 'speaker',
    'keyboard'       => 'piano',
    'guitar'         => 'guitar',
    'drum'           => 'drums',
    'microphone'     => 'microphone',
    'mixer'          => 'audio mixer',
    'playstation'    => 'playstation',
    'ps4'            => 'playstation',
    'ps5'            => 'playstation',
    'xbox'           => 'xbox',
    'nintendo'       => 'nintendo',
    'switch oled'    => 'nintendo',
    'steam deck'     => 'handheld console',
    'gameboy'        => 'gameboy',
    'atari'          => 'retro console',
    'generator'      => 'generator',
    'inverter'       => 'inverter',
    'solar'          => 'solar panel',
    'welding'        => 'welding',
    'projector'      => 'projector',
    'printer'        => '3d printer',
    'drone'          => 'drone',

    // Fashion
    'ankara'         => 'african fashion',
    'agbada'         => 'african man',
    'kaftan'         => 'kaftan',
    'aso-oke'        => 'african fabric',
    'saree'          => 'saree',
    'gown'           => 'gown',
    'wedding'        => 'wedding dress',
    'dress'          => 'dress',
    'blouse'         => 'blouse',
    'shirt'          => 'shirt',
    'polo'           => 'polo shirt',
    'jeans'          => 'jeans',
    'leather jacket' => 'leather jacket',
    'denim jacket'   => 'denim jacket',
    'suit'           => 'suit',
    'blazer'         => 'blazer',
    'skirt'          => 'skirt',
    'sneakers'       => 'sneakers',
    'nike'           => 'nike',
    'adidas'         => 'adidas',
    'puma'           => 'puma',
    'converse'       => 'converse',
    'vans'           => 'vans',
    'boots'          => 'boots',
    'loafers'        => 'loafers',
    'heels'          => 'high heels',
    'sandals'        => 'sandals',
    'handbag'        => 'handbag',
    'clutch'         => 'clutch bag',
    'belt'           => 'belt',
    'sunglasses'     => 'sunglasses',
    'watch'          => 'wristwatch',

    // Furniture
    'sofa'           => 'sofa',
    'couch'          => 'couch',
    'armchair'       => 'armchair',
    'recliner'       => 'recliner',
    'office chair'   => 'office chair',
    'chair'          => 'chair',
    'dining'         => 'dining table',
    'coffee table'   => 'coffee table',
    'side table'     => 'side table',
    'study desk'     => 'desk',
    'office desk'    => 'office desk',
    'desk'           => 'desk',
    'bookshelf'      => 'bookshelf',
    'wardrobe'       => 'wardrobe',
    'nightstand'     => 'nightstand',
    'drawers'        => 'dresser',
    'bunk'           => 'bunk bed',
    'bed'            => 'bed',
    'king bed'       => 'bed',
    'mattress'       => 'mattress',
    'mirror'         => 'mirror',

    // Books
    'harry potter'   => 'harry potter',
    'sherlock'       => 'book',
    'shakespeare'    => 'book',
    'bible'          => 'bible',
    'dictionary'     => 'dictionary',
    'encyclopedia'   => 'encyclopedia',
    'textbook'       => 'textbook',
    'novel'          => 'novel',
    'magazine'       => 'magazine',
    'comic'          => 'comic',
    'poetry'         => 'poetry',
    'things fall'    => 'book',
    'great gatsby'   => 'book',
    'economist'      => 'magazine',
    'gatsby'         => 'book',
    'book'           => 'book',

    // Collectibles
    'antique'        => 'antique',
    'vintage'        => 'vintage',
    'painting'       => 'painting',
    'sculpture'      => 'sculpture',
    'stamp'          => 'stamps',
    'coin'           => 'coins',
    'currency'       => 'currency',
    'record'         => 'vinyl',
    'vinyl'          => 'vinyl',
    'mask'           => 'african mask',
    'carving'        => 'wood carving',

    // Vehicles
    'toyota'         => 'toyota',
    'corolla'        => 'toyota corolla',
    'camry'          => 'toyota camry',
    'hiace'          => 'toyota hiace',
    'hilux'          => 'pickup truck',
    'rav4'           => 'toyota rav4',
    'honda'          => 'honda',
    'accord'         => 'honda accord',
    'civic'          => 'honda civic',
    'cr-v'           => 'honda crv',
    'pilot'          => 'honda pilot',
    'kia'            => 'kia',
    'hyundai'        => 'hyundai',
    'mazda'          => 'mazda',
    'nissan'         => 'nissan',
    'volvo'          => 'volvo',
    'bmw'            => 'bmw',
    'mercedes'       => 'mercedes',
    'audi'           => 'audi',
    'volkswagen'     => 'volkswagen',
    'ford'           => 'ford',
    'range rover'    => 'range rover',
    'lexus'          => 'lexus',
    'peugeot'        => 'peugeot',
    'motorcycle'     => 'motorcycle',
    'bicycle'        => 'bicycle',

    // Sports
    'football'       => 'soccer ball',
    'soccer'         => 'soccer',
    'basketball'     => 'basketball',
    'volleyball'     => 'volleyball',
    'tennis'         => 'tennis',
    'badminton'      => 'badminton',
    'cricket'        => 'cricket',
    'boxing'         => 'boxing',
    'yoga'           => 'yoga',
    'barbell'        => 'barbell',
    'dumbbell'       => 'dumbbell',
    'kettlebell'     => 'kettlebell',
    'treadmill'      => 'treadmill',
    'spin bike'      => 'spin bike',
    'gym'            => 'gym',
    'camping'        => 'camping',
    'tent'           => 'tent',
    'backpack'       => 'backpack',
    'fishing'        => 'fishing',
    'swimming'       => 'swimming',
    'rowing'         => 'rowing machine',
    'pilates'        => 'pilates',

    // Toys
    'lego'           => 'lego',
    'chess'          => 'chess',
    'monopoly'       => 'monopoly',
    'scrabble'       => 'scrabble',
    'uno'            => 'playing cards',
    'jenga'          => 'jenga',
    'puzzle'         => 'puzzle',
    'board game'     => 'board game',
    'doll'           => 'doll',
    'trampoline'     => 'trampoline',
    'swing set'      => 'swing',
    'kiddie pool'    => 'kids pool',
    'ride-on'        => 'kids car',

    // Home & Garden
    'refrigerator'   => 'refrigerator',
    'washing'        => 'washing machine',
    'microwave'      => 'microwave',
    'blender'        => 'blender',
    'toaster'        => 'toaster',
    'rice cooker'    => 'rice cooker',
    'air fryer'      => 'air fryer',
    'pressure cooker'=> 'pressure cooker',
    'gas cooker'     => 'gas stove',
    'cooker'         => 'cooker',
    'vacuum'         => 'vacuum cleaner',
    'dishwasher'     => 'dishwasher',
    'lawn mower'     => 'lawn mower',
    'garden'         => 'garden',
    'water pump'     => 'water pump',
    'water tank'     => 'water tank',
    'patio'          => 'patio',
    'bbq'            => 'barbecue',
    'mixer'          => 'kitchen mixer',
    'kettle'         => 'kettle',

    // Health & Beauty
    'skincare'       => 'skincare',
    'serum'          => 'serum',
    'makeup'         => 'makeup',
    'lipstick'       => 'lipstick',
    'foundation'     => 'makeup',
    'nail'           => 'nail polish',
    'massage'        => 'massage',
    'wellness'       => 'wellness',
    'beauty'         => 'beauty',

    // Pet
    'dog'            => 'dog',
    'cat'            => 'cat',
    'hamster'        => 'hamster',
    'fish tank'      => 'aquarium',
    'aquarium'       => 'aquarium',
    'bird'           => 'bird',
    'pet'            => 'pet',
];

// ---- GENERIC FALLBACK BY CATEGORY ----
// Used when keyword fails AND title has no match
$categoryFallback = [
    1  => 'electronics',           // Electronics
    2  => 'fashion clothing',      // Fashion
    3  => 'furniture',             // Furniture
    4  => 'books',                 // Books
    5  => 'antique',               // Collectibles
    6  => 'car',                   // Vehicles
    7  => 'sports equipment',      // Sports
    8  => 'toys',                  // Toys
    9  => 'home appliance',        // Home & Garden
    10 => 'product',               // Other
    49 => 'cosmetics',             // Health & Beauty
    50 => 'pet supplies',          // Pet Supplies
];

// ---- HELPERS ----
function logLine($file, $msg) {
    file_put_contents($file, date('Y-m-d H:i:s') . ' | ' . $msg . "\n", FILE_APPEND);
}

function pickKeyword($title, $keywordMap) {
    $lower = mb_strtolower($title);
    foreach ($keywordMap as $needle => $keyword) {
        if (mb_strpos($lower, $needle) !== false) {
            return $keyword;
        }
    }
    return null;
}

function fetchPixabay($apiKey, $keyword, $perPage = 10) {
    $url = 'https://pixabay.com/api/?key=' . urlencode($apiKey)
         . '&q=' . urlencode($keyword)
         . '&image_type=photo'
         . '&orientation=horizontal'
         . '&safesearch=true'
         . '&per_page=' . (int)$perPage;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'MarketplaceSeeder/2.0',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;
    $data = json_decode($response, true);
    if (empty($data['hits'])) return null;
    return $data['hits'];
}

function fetchPexels($apiKey, $keyword, $perPage = 10) {
    $url = 'https://api.pexels.com/v1/search?query=' . urlencode($keyword)
         . '&per_page=' . (int)$perPage
         . '&orientation=landscape';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $apiKey],
        CURLOPT_USERAGENT      => 'MarketplaceSeeder/2.0',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;
    $data = json_decode($response, true);
    if (empty($data['photos'])) return null;
    return $data['photos'];
}

function downloadImage($url, $savePath) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'MarketplaceSeeder/2.0',
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$data) return false;
    return file_put_contents($savePath, $data) !== false;
}

function storeImage($db, $listingId, $filename) {
    $existing = $db->fetchOne(
        "SELECT id FROM listing_images WHERE listing_id = ? ORDER BY id ASC LIMIT 1",
        [$listingId]
    );
    if ($existing) {
        $db->update(
            "UPDATE listing_images SET image_url = ?, is_primary = 1 WHERE id = ?",
            [$filename, $existing['id']]
        );
    } else {
        $db->insert(
            "INSERT INTO listing_images (listing_id, image_url, is_primary, sort_order) 
             VALUES (?, ?, 1, 0)",
            [$listingId, $filename]
        );
    }
}

// ---- MAIN ----
$db = db();

echo "=== Image Fetcher v2 (Pixabay + Pexels) ===\n\n";

// Find listings WITHOUT a pixabay-* or pexels-* image
$listings = $db->fetchAll(
    "SELECT l.id, l.title, l.category_id 
     FROM listings l
     WHERE l.id NOT IN (
         SELECT DISTINCT li.listing_id 
         FROM listing_images li 
         WHERE li.image_url LIKE 'pixabay-%' 
            OR li.image_url LIKE 'pexels-%'
     )
     ORDER BY l.id ASC"
);

$total = count($listings);
echo "Listings needing images: {$total}\n\n";

if ($total === 0) {
    echo "All listings already have images. Nothing to do.\n";
    exit(0);
}

$successPixabay = 0;
$successPexels  = 0;
$genericCount   = 0;
$fallbackCount  = 0;

foreach ($listings as $i => $row) {
    $idx   = $i + 1;
    $id    = (int)$row['id'];
    $title = $row['title'];
    $catId = (int)($row['category_id'] ?? 0);

    echo "[{$idx}/{$total}] #{$id} \"" . trim($title) . "\"";

    // 1) Try to pick a keyword from title
    $keyword = pickKeyword($title, $keywordMap);
    if (!$keyword) {
        echo " — no title match";
    } else {
        echo " — keyword: {$keyword}";
    }

    $downloaded = false;
    $source = null;

    // 2) Try Pixabay if we have a keyword
    if ($keyword && $pixabayKey) {
        $hits = fetchPixabay($pixabayKey, $keyword);
        if ($hits) {
            $pick = $hits[$id % count($hits)];
            $url  = $pick['webformatURL'] ?? $pick['largeImageURL'] ?? null;
            if ($url) {
                $filename = 'pixabay-' . $id . '.jpg';
                $savePath = $targetDir . $filename;
                if (downloadImage($url, $savePath)) {
                    storeImage($db, $id, $filename);
                    $downloaded = true;
                    $source = 'pixabay';
                    $successPixabay++;
                }
            }
        }
        if (!$downloaded) {
            // Retry once after 5 seconds
            sleep(5);
            $hits = fetchPixabay($pixabayKey, $keyword);
            if ($hits) {
                $pick = $hits[$id % count($hits)];
                $url  = $pick['webformatURL'] ?? $pick['largeImageURL'] ?? null;
                if ($url) {
                    $filename = 'pixabay-' . $id . '.jpg';
                    $savePath = $targetDir . $filename;
                    if (downloadImage($url, $savePath)) {
                        storeImage($db, $id, $filename);
                        $downloaded = true;
                        $source = 'pixabay';
                        $successPixabay++;
                    }
                }
            }
        }
    }

    // 3) Try Pexels if Pixabay failed AND we have a keyword
    if (!$downloaded && $keyword && $pexelsKey) {
        $photos = fetchPexels($pexelsKey, $keyword);
        if ($photos) {
            $pick = $photos[$id % count($photos)];
            $url  = $pick['src']['large'] ?? $pick['src']['medium'] ?? null;
            if ($url) {
                $filename = 'pexels-' . $id . '.jpg';
                $savePath = $targetDir . $filename;
                if (downloadImage($url, $savePath)) {
                    storeImage($db, $id, $filename);
                    $downloaded = true;
                    $source = 'pexels';
                    $successPexels++;
                }
            }
        }
    }

    // 4) Generic category fallback (only if we have a category)
    if (!$downloaded && $catId && isset($categoryFallback[$catId])) {
        $genericKeyword = $categoryFallback[$catId];
        echo " → generic: {$genericKeyword}";

        // Try Pixabay with category keyword
        if ($pixabayKey) {
            $hits = fetchPixabay($pixabayKey, $genericKeyword);
            if ($hits) {
                $pick = $hits[$id % count($hits)];
                $url  = $pick['webformatURL'] ?? null;
                if ($url) {
                    $filename = 'pixabay-' . $id . '.jpg';
                    $savePath = $targetDir . $filename;
                    if (downloadImage($url, $savePath)) {
                        storeImage($db, $id, $filename);
                        $downloaded = true;
                        $source = 'pixabay-generic';
                        $genericCount++;
                    }
                }
            }
        }

        // Try Pexels as second fallback
        if (!$downloaded && $pexelsKey) {
            $photos = fetchPexels($pexelsKey, $genericKeyword);
            if ($photos) {
                $pick = $photos[$id % count($photos)];
                $url  = $pick['src']['large'] ?? null;
                if ($url) {
                    $filename = 'pexels-' . $id . '.jpg';
                    $savePath = $targetDir . $filename;
                    if (downloadImage($url, $savePath)) {
                        storeImage($db, $id, $filename);
                        $downloaded = true;
                        $source = 'pexels-generic';
                        $genericCount++;
                    }
                }
            }
        }
    }

    // 5) Final fallback — point to default image
    if (!$downloaded) {
        storeImage($db, $id, '');
        $fallbackCount++;
        echo " → fallback (default-listing.jpg)\n";
        logLine($logFile, "#{$id} — no image, using default. Title: \"{$title}\"");
    } else {
        echo " → {$source} ✓\n";
        logLine($logFile, "#{$id} — {$source} ({$title})");
    }

    sleep($sleepBetweenRequests);
}

echo "\n=== DONE ===\n";
echo "Pixabay (keyword):  {$successPixabay}\n";
echo "Pexels (keyword):   {$successPexels}\n";
echo "Generic category:   {$genericCount}\n";
echo "Fallback to default: {$fallbackCount}\n";
echo "Log: logs/pixabay-fetch-v2.log\n";