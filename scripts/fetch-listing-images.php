<?php
// marketplace/scripts/fetch-listing-images.php
// Downloads real product photos from Pixabay and stores them locally.
// Run from command line: php scripts/fetch-listing-images.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

// ---- CONFIG ----
$apiKey = getenv('PIXABAY_API_KEY') ?: '';
if (empty($apiKey)) {
    die("ERROR: PIXABAY_API_KEY is not set in .env\n");
}

$targetDir = __DIR__ . '/../assets/uploads/listings/';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$logFile = __DIR__ . '/../logs/pixabay-fetch.log';
$perPage = 5;            // grab top 5 results per keyword for variety
$sleepBetweenRequests = 1; // 1 sec = safe under 100 req/min

// ---- KEYWORD MAP ----
// Maps a substring in the listing title → search keyword for Pixabay
$keywordMap = [
    // Electronics
    'iphone'         => 'iphone smartphone',
    'samsung'        => 'samsung smartphone',
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
    'macbook'        => 'macbook laptop',
    'laptop'         => 'laptop computer',
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
    'surface'        => 'tablet surface',
    'ipad'           => 'ipad tablet',
    'tablet'         => 'tablet',
    'camera'         => 'camera photography',
    'dslr'           => 'dslr camera',
    'mirrorless'     => 'camera',
    'canon'          => 'canon camera',
    'nikon'          => 'nikon camera',
    'sony'           => 'sony camera',
    'fujifilm'       => 'fuji camera',
    'panasonic'      => 'panasonic camera',
    'gopro'          => 'action camera',
    'dji'            => 'drone',
    'instax'         => 'instant camera',
    'speaker'        => 'bluetooth speaker',
    'soundbar'       => 'soundbar speaker',
    'headphone'      => 'headphones',
    'airpods'        => 'earbuds',
    'beats'          => 'headphones',
    'sennheiser'     => 'headphones',
    'bose'           => 'speaker audio',
    'marshall'       => 'speaker',
    'jbl'            => 'speaker',
    'sonos'          => 'speaker',
    'anker'          => 'speaker',
    'keyboard'       => 'piano keyboard',
    'guitar'         => 'guitar',
    'drum'           => 'drum kit',
    'microphone'     => 'microphone studio',
    'mixer'          => 'audio mixer',
    'playstation'    => 'playstation console',
    'ps4'            => 'playstation',
    'ps5'            => 'playstation 5',
    'xbox'           => 'xbox console',
    'nintendo'       => 'nintendo switch',
    'switch oled'    => 'nintendo switch',
    'steam deck'     => 'handheld gaming',
    'gameboy'        => 'gameboy retro',
    'atari'          => 'retro game console',
    'generator'      => 'portable generator',
    'inverter'       => 'inverter power',
    'solar'          => 'solar panel',
    'welding'        => 'welding machine',
    'projector'      => 'projector',
    'printer'        => '3d printer',
    'drone'          => 'drone',

    // Fashion
    'ankara'         => 'african fabric ankara',
    'agbada'         => 'african men fashion',
    'kaftan'         => 'african men clothing',
    'aso-oke'        => 'african fabric',
    'saree'          => 'saree',
    'gown'           => 'gown dress',
    'wedding dress'  => 'wedding dress',
    'dress'          => 'dress fashion',
    'blouse'         => 'blouse',
    'shirt'          => 'shirt',
    't-shirt'        => 'tshirt',
    'polo'           => 'polo shirt',
    'jeans'          => 'jeans',
    'leather jacket' => 'leather jacket',
    'denim jacket'   => 'denim jacket',
    'suit'           => 'suit menswear',
    'blazer'         => 'blazer',
    'skirt'          => 'skirt',
    'sneakers'       => 'sneakers',
    'nike'           => 'nike shoes',
    'adidas'         => 'adidas shoes',
    'puma'           => 'puma shoes',
    'converse'       => 'converse shoes',
    'vans'           => 'vans shoes',
    'boots'          => 'boots shoes',
    'loafers'        => 'loafers shoes',
    'heels'          => 'high heels',
    'sandals'        => 'sandals',
    'handbag'        => 'handbag purse',
    'clutch'         => 'clutch bag',
    'belt'           => 'leather belt',
    'jewelry'        => 'jewelry',
    'sunglasses'     => 'sunglasses',
    'watch'          => 'wristwatch',

    // Furniture
    'sofa'           => 'sofa couch',
    'couch'          => 'couch sofa',
    'armchair'       => 'armchair',
    'recliner'       => 'recliner chair',
    'office chair'   => 'office chair',
    'chair'          => 'chair furniture',
    'dining table'   => 'dining table',
    'coffee table'   => 'coffee table',
    'side table'     => 'side table',
    'study desk'     => 'study desk',
    'office desk'    => 'office desk',
    'desk'           => 'desk furniture',
    'bookshelf'      => 'bookshelf',
    'wardrobe'       => 'wardrobe closet',
    'nightstand'     => 'nightstand',
    'chest of drawers' => 'dresser',
    'bunk bed'       => 'bunk bed',
    'bed frame'      => 'bed frame',
    'platform bed'   => 'bed',
    'king bed'       => 'bed bedroom',
    'mattress'       => 'mattress',
    'mirror'         => 'mirror',

    // Books
    'harry potter'   => 'harry potter book',
    'sherlock'       => 'sherlock holmes book',
    'shakespeare'    => 'shakespeare book',
    'bible'          => 'bible book',
    'dictionary'     => 'dictionary book',
    'encyclopedia'   => 'encyclopedia book',
    'textbook'       => 'textbook study',
    'novel'          => 'novel book',
    'magazine'       => 'magazine',
    'comic'          => 'comic book',
    'poetry'         => 'poetry book',
    'book'           => 'book reading',

    // Collectibles
    'antique'        => 'antique vintage',
    'vintage'        => 'vintage retro',
    'painting'       => 'painting art',
    'sculpture'      => 'sculpture',
    'stamp'          => 'postage stamps',
    'coin'           => 'old coins',
    'currency'       => 'old currency',
    'record'         => 'vinyl record',
    'vinyl'          => 'vinyl record',
    'mask'           => 'african mask',
    'carving'        => 'wood carving',

    // Vehicles
    'toyota'         => 'toyota car',
    'corolla'        => 'toyota corolla',
    'camry'          => 'toyota camry',
    'hiace'          => 'toyota van',
    'hilux'          => 'pickup truck',
    'rav4'           => 'toyota suv',
    'honda'          => 'honda car',
    'accord'         => 'honda accord',
    'civic'          => 'honda civic',
    'cr-v'           => 'honda suv',
    'pilot'          => 'honda suv',
    'kia'            => 'kia car',
    'hyundai'        => 'hyundai car',
    'mazda'          => 'mazda car',
    'nissan'         => 'nissan car',
    'volvo'          => 'volvo car',
    'bmw'            => 'bmw car',
    'mercedes'       => 'mercedes benz',
    'audi'           => 'audi car',
    'volkswagen'     => 'volkswagen car',
    'ford'           => 'ford suv',
    'range rover'    => 'range rover suv',
    'lexus'          => 'lexus suv',
    'peugeot'        => 'peugeot car',
    'motorcycle'     => 'motorcycle',
    'bicycle'        => 'bicycle bike',

    // Sports
    'football'       => 'soccer ball',
    'soccer'         => 'soccer',
    'basketball'     => 'basketball',
    'volleyball'     => 'volleyball',
    'tennis'         => 'tennis racket',
    'badminton'      => 'badminton racket',
    'cricket'        => 'cricket bat',
    'boxing'         => 'boxing gloves',
    'yoga'           => 'yoga mat',
    'barbell'        => 'barbell gym',
    'dumbbell'       => 'dumbbell',
    'kettlebell'     => 'kettlebell',
    'treadmill'      => 'treadmill',
    'spin bike'      => 'spin bike',
    'gym'            => 'gym equipment',
    'camping'        => 'camping tent',
    'tent'           => 'camping tent',
    'backpack'       => 'hiking backpack',
    'fishing'        => 'fishing rod',
    'swimming'       => 'swimming goggles',
    'rowing'         => 'rowing machine',
    'pilates'        => 'pilates',

    // Toys
    'lego'           => 'lego bricks',
    'chess'          => 'chess board',
    'monopoly'       => 'monopoly board game',
    'scrabble'       => 'scrabble board game',
    'uno'            => 'uno card game',
    'jenga'          => 'jenga game',
    'puzzle'         => 'jigsaw puzzle',
    'board game'     => 'board game',
    'action figure'  => 'action figures',
    'doll'           => 'doll toy',
    'trampoline'     => 'trampoline',
    'swing set'      => 'swing set kids',
    'kiddie pool'    => 'kiddie pool',
    'ride-on'        => 'kids ride on car',

    // Home & Garden
    'refrigerator'   => 'refrigerator',
    'washing machine'=> 'washing machine',
    'microwave'      => 'microwave',
    'blender'        => 'blender kitchen',
    'mixer'          => 'kitchen mixer',
    'kettle'         => 'electric kettle',
    'toaster'        => 'toaster oven',
    'rice cooker'    => 'rice cooker',
    'air fryer'      => 'air fryer',
    'pressure cooker'=> 'pressure cooker',
    'gas cooker'     => 'gas stove',
    'cooker'         => 'kitchen stove',
    'vacuum'         => 'vacuum cleaner',
    'dishwasher'     => 'dishwasher',
    'lawn mower'     => 'lawn mower',
    'garden'         => 'garden tools',
    'water pump'     => 'water pump',
    'water tank'     => 'water tank',
    'patio'          => 'patio furniture',
    'bbq'            => 'bbq grill',

    // Health & Beauty
    'skincare'       => 'skincare products',
    'serum'          => 'serum bottle',
    'makeup'         => 'makeup cosmetics',
    'lipstick'       => 'lipstick',
    'foundation'     => 'makeup foundation',
    'nail'           => 'nail polish',
    'massage'        => 'massage gun',
    'wellness'       => 'wellness spa',

    // Pet
    'dog'            => 'dog',
    'cat'            => 'cat',
    'hamster'        => 'hamster',
    'fish tank'      => 'aquarium',
    'bird'           => 'bird cage',
    'pet'            => 'pet animal',
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

function fetchFromPixabay($apiKey, $keyword, $perPage) {
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
        CURLOPT_USERAGENT      => 'MarketplaceSeeder/1.0',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }
    $data = json_decode($response, true);
    if (!isset($data['hits']) || empty($data['hits'])) {
        return null;
    }
    return $data['hits'];
}

function downloadImage($url, $savePath) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'MarketplaceSeeder/1.0',
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$data) {
        return false;
    }
    return file_put_contents($savePath, $data) !== false;
}

// ---- MAIN ----
$db = db();

echo "=== Pixabay Image Fetcher ===\n";
echo "Fetching listings...\n\n";

// Fetch all listings that are missing a real local image
$listings = $db->fetchAll(
    "SELECT id, title FROM listings ORDER BY id ASC"
);

$total = count($listings);
echo "Total listings to process: {$total}\n\n";

$success = 0;
$failed  = 0;
$skipped = 0;

foreach ($listings as $i => $row) {
    $idx   = $i + 1;
    $id    = (int)$row['id'];
    $title = $row['title'];

    // Pick keyword
    $keyword = pickKeyword($title, $keywordMap);
    if (!$keyword) {
        echo "[{$idx}/{$total}] #{$id} \"{$title}\" — no keyword match, skipping\n";
        logLine($logFile, "#{$id} — no keyword match: {$title}");
        $skipped++;
        continue;
    }

    // Call Pixabay
    $hits = fetchFromPixabay($apiKey, $keyword, $perPage);
    if (!$hits) {
        echo "[{$idx}/{$total}] #{$id} \"{$title}\" — Pixabay returned no results for \"{$keyword}\"\n";
        logLine($logFile, "#{$id} — no results for \"{$keyword}\"");
        $failed++;
        sleep($sleepBetweenRequests);
        continue;
    }

    // Pick the best result — rotate based on listing id for variety
    $pick = $hits[$id % count($hits)];
    $imageUrl = $pick['webformatURL'] ?? $pick['largeImageURL'] ?? null;
    if (!$imageUrl) {
        echo "[{$idx}/{$total}] #{$id} \"{$title}\" — no URL in result\n";
        logLine($logFile, "#{$id} — no URL in result");
        $failed++;
        sleep($sleepBetweenRequests);
        continue;
    }

    // Save filename
    $filename = 'pixabay-' . $id . '.jpg';
    $savePath = $targetDir . $filename;

    if (!downloadImage($imageUrl, $savePath)) {
        echo "[{$idx}/{$total}] #{$id} \"{$title}\" — download failed\n";
        logLine($logFile, "#{$id} — download failed for {$imageUrl}");
        $failed++;
        sleep($sleepBetweenRequests);
        continue;
    }

    // Update or insert listing_images row
    $existing = $db->fetchOne(
        "SELECT id FROM listing_images WHERE listing_id = ? ORDER BY id ASC LIMIT 1",
        [$id]
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
            [$id, $filename]
        );
    }

    echo "[{$idx}/{$total}] #{$id} \"{$title}\" — {$keyword} → {$filename} ✓\n";
    logLine($logFile, "#{$id} — saved as {$filename} ({$keyword})");
    $success++;

    sleep($sleepBetweenRequests);
}

echo "\n=== DONE ===\n";
echo "Success: {$success}\n";
echo "Failed:  {$failed}\n";
echo "Skipped: {$skipped}\n";
echo "Log:     logs/pixabay-fetch.log\n";