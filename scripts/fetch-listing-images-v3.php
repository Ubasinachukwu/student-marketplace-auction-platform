<?php
// marketplace/scripts/fetch-listing-images-v3.php
// Third pass — targets ONLY listings still on fallback.
// Pexels first (higher rate limit), Pixabay as fallback.
// Skips hopeless test listings.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

// ---- CONFIG ----
$pixabayKey = getenv('PIXABAY_API_KEY') ?: '';
$pexelsKey  = getenv('PEXELS_API_KEY')  ?: '';

if (empty($pexelsKey)) {
    die("ERROR: PEXELS_API_KEY missing from .env\n");
}

$targetDir = __DIR__ . '/../assets/uploads/listings/';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$logFile = __DIR__ . '/../logs/pixabay-fetch-v3.log';
$sleepBetweenRequests = 3;

// Titles to skip entirely (hopeless test data)
$skipTitles = ['', 'test 1', 'test 2', 'test 3', 'n-a', 'electronics'];

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
    'nokia'          => 'nokia phone',
    'huawei'         => 'smartphone',
    'oneplus'        => 'smartphone',
    'xiaomi'         => 'smartphone',
    'redmi'          => 'smartphone',
    'itel'           => 'smartphone',
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
    'xps'            => 'laptop',
    'gram 17'        => 'laptop',
    'stream 11'      => 'laptop',
    'chuwi'          => 'laptop',
    'surface'        => 'tablet',
    'ipad'           => 'ipad',
    'tablet'         => 'tablet',
    'camera'         => 'camera',
    'dslr'           => 'camera',
    'canon'          => 'camera',
    'nikon'          => 'camera',
    'sony'           => 'camera',
    'fujifilm'       => 'camera',
    'panasonic'      => 'camera',
    'gopro'          => 'camera',
    'dji'            => 'drone',
    'instax'         => 'camera',
    'cyber-shot'     => 'camera',
    'powershot'      => 'camera',
    'blackmagic'     => 'video camera',
    'leica'          => 'camera',
    'lens'           => 'camera lens',
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
    'keyboard'       => 'piano keyboard',
    'guitar'         => 'guitar',
    'drum'           => 'drum kit',
    'microphone'     => 'microphone',
    'mixer'          => 'audio mixer',
    'yamaha'         => 'piano keyboard',
    'playstation'    => 'playstation',
    'ps4'            => 'playstation',
    'ps5'            => 'playstation 5',
    'xbox'           => 'xbox',
    'nintendo'       => 'nintendo',
    'gameboy'        => 'gameboy',
    'atari'          => 'retro game',
    'retro console'  => 'retro console',
    'vr headset'     => 'vr headset',
    'oculus'         => 'vr headset',
    'gaming pc'      => 'gaming computer',
    'generator'      => 'generator',
    'inverter'       => 'power inverter',
    'solar'          => 'solar panel',
    'welding'        => 'welding machine',
    'projector'      => 'projector',
    'printer'        => '3d printer',
    'drone'          => 'drone',

    // Fashion
    'ankara'         => 'african fashion',
    'agbada'         => 'african man',
    'kaftan'         => 'african clothing',
    'aso-ebi'        => 'african fabric',
    'aso-oke'        => 'african fabric',
    'saree'          => 'saree',
    'gown'           => 'gown',
    'wedding'        => 'wedding dress',
    'bridal'         => 'bridal',
    'dress'          => 'dress',
    'blouse'         => 'blouse',
    'shirt'          => 'shirt',
    'sweater'        => 'sweater',
    'cashmere'       => 'sweater',
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
    'balenciaga'     => 'sneakers',
    'boots'          => 'boots',
    'loafers'        => 'loafers',
    'heels'          => 'high heels',
    'sandals'        => 'sandals',
    'shoes'          => 'shoes',
    'handbag'        => 'handbag',
    'clutch'         => 'clutch bag',
    'belt'           => 'leather belt',
    'sunglasses'     => 'sunglasses',
    'watch'          => 'wristwatch',
    'pajama'         => 'pajamas',
    'romper'         => 'baby clothes',
    'uniform'        => 'kids uniform',
    'outfit'         => 'kids clothes',
    'jacket'         => 'jacket',

    // Furniture
    'sofa'           => 'sofa',
    'couch'          => 'couch',
    'armchair'       => 'armchair',
    'recliner'       => 'recliner chair',
    'office chair'   => 'office chair',
    'chair'          => 'chair',
    'dining'         => 'dining table',
    'coffee table'   => 'coffee table',
    'side table'     => 'side table',
    'console table'  => 'console table',
    'study desk'     => 'study desk',
    'office desk'    => 'office desk',
    'desk'           => 'desk',
    'bookshelf'      => 'bookshelf',
    'wardrobe'       => 'wardrobe',
    'closet'         => 'walk in closet',
    'nightstand'     => 'nightstand',
    'drawers'        => 'dresser',
    'bunk'           => 'bunk bed',
    'bed'            => 'bed',
    'mattress'       => 'mattress',
    'mirror'         => 'mirror',
    'filing cabinet' => 'filing cabinet',
    'bar stool'      => 'bar stool',
    'tv stand'       => 'tv stand',
    'tv unit'        => 'tv stand',
    'patio'          => 'patio furniture',
    'bamboo'         => 'furniture',

    // Books
    'harry potter'   => 'harry potter book',
    'sherlock'       => 'sherlock holmes',
    'shakespeare'    => 'shakespeare',
    'bible'          => 'bible',
    'dictionary'     => 'dictionary book',
    'encyclopedia'   => 'encyclopedia',
    'textbook'       => 'textbook',
    'novel'          => 'novel book',
    'magazine'       => 'magazine',
    'comic'          => 'comic book',
    'poetry'         => 'poetry book',
    'wuthering'      => 'book',
    'pride and prejudice' => 'book',
    'old man and the sea' => 'book',
    'sapiens'        => 'book',
    'lean startup'   => 'book',
    'economist'      => 'magazine',
    'gatsby'         => 'book',
    'purple hibiscus'=> 'book',
    'yellow sun'     => 'book',
    'concubine'      => 'book',
    'alchemist'      => 'book',
    'atomic habits'  => 'book',
    'godfather'      => 'book',
    'things fall'    => 'book',
    'rich dad'       => 'book',
    'algorithms'     => 'textbook',
    'calculus'       => 'textbook',
    'physics'        => 'textbook',
    'chemistry'      => 'textbook',
    'mathematics'    => 'textbook',
    'microeconomics' => 'textbook',
    'business management' => 'textbook',
    'statistical'    => 'textbook',
    'engineer'       => 'textbook',
    'economic'       => 'magazine',
    'hbr'            => 'business book',
    'reader'         => 'magazine',
    'postcard'       => 'postcards',
    'book'           => 'book',

    // Collectibles
    'antique'        => 'antique',
    'vintage'        => 'vintage',
    'painting'       => 'painting art',
    'sculpture'      => 'sculpture',
    'art collection' => 'art',
    'stamp'          => 'postage stamps',
    'coin'           => 'old coins',
    'currency'       => 'currency',
    'record'         => 'vinyl record',
    'vinyl'          => 'vinyl record',
    'mask'           => 'african mask',
    'carving'        => 'wood carving',
    'photograph'     => 'photography art',
    'historical'     => 'old document',
    'ceramic'        => 'ceramic vase',
    'cigar'          => 'cigar box',
    'beer stein'     => 'beer stein',
    'typewriter'     => 'typewriter',
    'wall hanging'   => 'wall art',
    'framed'         => 'framed picture',

    // Vehicles
    'toyota'         => 'toyota car',
    'corolla'        => 'toyota corolla',
    'camry'          => 'toyota camry',
    'hiace'          => 'toyota van',
    'hilux'          => 'pickup truck',
    'rav4'           => 'toyota suv',
    'sienna'         => 'toyota van',
    'avalon'         => 'toyota sedan',
    'honda'          => 'honda car',
    'accord'         => 'honda accord',
    'civic'          => 'honda civic',
    'cr-v'           => 'honda suv',
    'pilot'          => 'honda suv',
    'kia'            => 'kia car',
    'optima'         => 'kia optima',
    'hyundai'        => 'hyundai car',
    'sonata'         => 'hyundai sonata',
    'mazda'          => 'mazda car',
    'nissan'         => 'nissan car',
    'xterra'         => 'nissan suv',
    'volvo'          => 'volvo car',
    'bmw'            => 'bmw car',
    'mercedes'       => 'mercedes',
    'audi'           => 'audi car',
    'volkswagen'     => 'volkswagen',
    'ford'           => 'ford suv',
    'range rover'    => 'range rover',
    'lexus'          => 'lexus',
    'peugeot'        => 'peugeot car',
    'suzuki'         => 'suzuki van',
    'motorcycle'     => 'motorcycle',
    'bicycle'        => 'bicycle',
    'fleet'          => 'cars',

    // Sports
    'football'       => 'soccer ball',
    'soccer'         => 'soccer',
    'basketball'     => 'basketball',
    'volleyball'     => 'volleyball',
    'tennis'         => 'tennis racket',
    'badminton'      => 'badminton',
    'cricket'        => 'cricket',
    'boxing'         => 'boxing gloves',
    'yoga'           => 'yoga mat',
    'barbell'        => 'barbell',
    'dumbbell'       => 'dumbbell',
    'kettlebell'     => 'kettlebell',
    'treadmill'      => 'treadmill',
    'spin bike'      => 'spin bike',
    'gym'            => 'gym equipment',
    'camping'        => 'camping tent',
    'tent'           => 'tent',
    'backpack'       => 'hiking backpack',
    'fishing'        => 'fishing rod',
    'swimming'       => 'swimming',
    'rowing'         => 'rowing machine',
    'pilates'        => 'pilates',
    'crossfit'       => 'crossfit',
    'weight bench'   => 'weight bench',
    'punching bag'   => 'punching bag',
    'squat rack'     => 'squat rack',
    'surfboard'      => 'surfboard',
    'sleeping bag'   => 'sleeping bag',

    // Toys
    'lego'           => 'lego',
    'chess'          => 'chess',
    'monopoly'       => 'monopoly board',
    'scrabble'       => 'scrabble',
    'uno'            => 'playing cards',
    'jenga'          => 'jenga',
    'puzzle'         => 'puzzle',
    'rubik'          => 'rubiks cube',
    'board game'     => 'board game',
    'doll'           => 'doll',
    'trampoline'     => 'trampoline',
    'swing set'      => 'swing',
    'kiddie'         => 'kids pool',
    'ride-on'        => 'kids toy car',
    'playhouse'      => 'playhouse',
    'water slide'    => 'water slide',
    'd&d'            => 'dungeons dragons',

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
    'cooker'         => 'kitchen stove',
    'vacuum'         => 'vacuum cleaner',
    'dishwasher'     => 'dishwasher',
    'lawn mower'     => 'lawn mower',
    'garden'         => 'garden',
    'water pump'     => 'water pump',
    'water tank'     => 'water tank',
    'home theater'   => 'home theater',
    'pa system'      => 'speaker system',
    'dj'             => 'dj controller',
    'stage lights'   => 'stage lighting',
    'coffee machine' => 'coffee machine',
    'kitchen set'    => 'kitchen set',
    'stand mixer'    => 'kitchen mixer',
    'kettle'         => 'electric kettle',
    'mixer'          => 'kitchen mixer',
    'meditation'     => 'meditation cushion',

    // Health & Beauty
    'skincare'       => 'skincare',
    'serum'          => 'serum',
    'makeup'         => 'makeup',
    'lipstick'       => 'lipstick',
    'foundation'     => 'makeup',
    'nail'           => 'nail polish',
    'massage'        => 'massage',
    'wellness'       => 'wellness',
    'beauty'         => 'beauty salon',
    'anti-aging'     => 'skincare',
    'face mask'      => 'face mask',

    // Pet
    'dog'            => 'dog',
    'cat'            => 'cat',
    'hamster'        => 'hamster',
    'fish tank'      => 'aquarium',
    'aquarium'       => 'aquarium',
    'bird'           => 'bird',
    'pet'            => 'pet',
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
        CURLOPT_USERAGENT      => 'MarketplaceSeeder/3.0',
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

echo "=== Image Fetcher v3 (Pexels-first) ===\n\n";

// Find listings with empty/fallback image_url
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

$okPexels = 0;
$okPixabay = 0;
$skipped = 0;
$failed = 0;

foreach ($listings as $i => $row) {
    $idx   = $i + 1;
    $id    = (int)$row['id'];
    $title = trim($row['title']);

    // Skip hopeless
    if (in_array(mb_strtolower($title), $skipTitles, true) || mb_strlen($title) < 3) {
        echo "[{$idx}/{$total}] #{$id} \"{$title}\" — skipped (hopeless)\n";
        $skipped++;
        continue;
    }

    // Pick keyword
    $keyword = pickKeyword($title, $keywordMap);
    if (!$keyword) {
        echo "[{$idx}/{$total}] #{$id} \"{$title}\" — no keyword match, skipped\n";
        logLine($logFile, "#{$id} — no keyword. Title: \"{$title}\"");
        $skipped++;
        continue;
    }

    echo "[{$idx}/{$total}] #{$id} \"{$title}\" — keyword: {$keyword}";

    $downloaded = false;
    $source = null;

    // 1) Try Pexels first
    $photos = fetchPexels($pexelsKey, $keyword);
    if ($photos) {
        $pick = $photos[$id % count($photos)];
        $url  = $pick['src']['large'] ?? $pick['src']['medium'] ?? null;
        if ($url) {
            $filename = 'pexels-' . $id . '.jpg';
            if (downloadImage($url, $targetDir . $filename)) {
                storeImage($db, $id, $filename);
                $downloaded = true;
                $source = 'pexels';
                $okPexels++;
            }
        }
    }

    // 2) Try Pixabay if Pexels failed
    if (!$downloaded && $pixabayKey) {
        sleep(1); // brief pause
        $url = 'https://pixabay.com/api/?key=' . urlencode($pixabayKey)
             . '&q=' . urlencode($keyword)
             . '&image_type=photo&orientation=horizontal&safepage=true&per_page=10';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        if (!empty($data['hits'])) {
            $pick = $data['hits'][$id % count($data['hits'])];
            $imgUrl = $pick['webformatURL'] ?? null;
            if ($imgUrl) {
                $filename = 'pixabay-' . $id . '.jpg';
                if (downloadImage($imgUrl, $targetDir . $filename)) {
                    storeImage($db, $id, $filename);
                    $downloaded = true;
                    $source = 'pixabay';
                    $okPixabay++;
                }
            }
        }
    }

    if ($downloaded) {
        echo " → {$source} ✓\n";
        logLine($logFile, "#{$id} — {$source} ({$title})");
    } else {
        echo " → failed\n";
        $failed++;
        logLine($logFile, "#{$id} — FAILED. Title: \"{$title}\"");
    }

    sleep($sleepBetweenRequests);
}

echo "\n=== DONE ===\n";
echo "Pexels:    {$okPexels}\n";
echo "Pixabay:   {$okPixabay}\n";
echo "Skipped:   {$skipped}\n";
echo "Failed:    {$failed}\n";