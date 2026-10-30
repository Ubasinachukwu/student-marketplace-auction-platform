<?php
// marketplace/seller/create-listing.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

Session::requireSeller();
$db = db();
$userId = Session::getUserId();
$errors = [];
$formData = [];
$step = isset($_GET['step']) ? max(1, min(3, (int)$_GET['step'])) : 1;

$categories = $db->fetchAll(
    "SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order"
);

$allSubcategories = $db->fetchAll(
    "SELECT * FROM categories WHERE parent_id IS NOT NULL AND is_active = 1 ORDER BY parent_id, sort_order"
);

$subcategoriesByParent = [];
foreach ($allSubcategories as $sub) {
    $subcategoriesByParent[$sub['parent_id']][] = $sub;
}

$subCategories = [];
if (!empty($_GET['category_id'])) {
    $subCategories = $db->fetchAll(
        "SELECT * FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order",
        [(int)$_GET['category_id']]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token. Please try again.');
        redirect(SITE_URL . '/seller/create-listing.php?step=' . $step);
    }

    // =============================================
    // BUG FIX: Only add keys to $formData if they were actually
    // submitted via $_POST. This prevents Step 2 from wiping out
    // Step 1 fields (title, category_id, description, etc.)
    // =============================================
    $formData = [];

    if ($step == 1) {
        $formData['title']          = trim((string)($_POST['title'] ?? ''));
        $formData['category_id']    = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $formData['subcategory_id'] = isset($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : 0;
        $formData['description']    = trim((string)($_POST['description'] ?? ''));
        $formData['condition']      = trim((string)($_POST['condition'] ?? 'good'));
        $formData['quantity']       = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    }

    if ($step == 2) {
        $formData['sale_type']        = trim((string)($_POST['sale_type'] ?? 'fixed'));
        $formData['price']            = isset($_POST['price']) ? (float)$_POST['price'] : 0;
        $formData['starting_bid']     = isset($_POST['starting_bid']) ? (float)$_POST['starting_bid'] : 0;
        $formData['reserve_price']    = isset($_POST['reserve_price']) ? (float)$_POST['reserve_price'] : 0;
        $formData['bid_increment']    = isset($_POST['bid_increment']) ? (float)$_POST['bid_increment'] : 1;
        $formData['auction_duration'] = isset($_POST['auction_duration']) ? (int)$_POST['auction_duration'] : 7;
        $formData['auto_extend']      = isset($_POST['auto_extend']) ? 1 : 0;
        $formData['buy_it_now_price'] = isset($_POST['buy_it_now_price']) ? (float)$_POST['buy_it_now_price'] : 0;
        $formData['is_featured']      = isset($_POST['is_featured']) ? 1 : 0;
    }

    if ($step == 3) {
        $formData['images'] = $_FILES['images'] ?? [];
    }

    // ---------------- STEP 1 ----------------
    if ($step == 1) {
        if (empty($formData['title'])) {
            $errors['title'] = 'Title is required';
        } elseif (strlen($formData['title']) < 5) {
            $errors['title'] = 'Title must be at least 5 characters';
        }
        if (empty($formData['category_id']) && empty($formData['subcategory_id'])) {
            $errors['category'] = 'Please select a category';
        }
        if (empty($formData['description'])) {
            $errors['description'] = 'Description is required';
        } elseif (strlen($formData['description']) < 20) {
            $errors['description'] = 'Description must be at least 20 characters';
        }

        if (empty($errors)) {
            $_SESSION['listing_data'] = $formData;
            redirect(SITE_URL . '/seller/create-listing.php?step=2');
        }

    // ---------------- STEP 2 ----------------
    } elseif ($step == 2) {
        $listingData = $_SESSION['listing_data'] ?? [];

        if (!in_array($formData['sale_type'], ['fixed', 'auction', 'hybrid'], true)) {
            $errors['sale_type'] = 'Please select a valid sale type';
        }

        if ($formData['sale_type'] === 'fixed') {
            if ($formData['price'] <= 0) {
                $errors['price'] = 'Please enter a valid price';
            }
        } elseif ($formData['sale_type'] === 'auction') {
            if ($formData['starting_bid'] <= 0) {
                $errors['starting_bid'] = 'Please enter a valid starting bid';
            }
            if ($formData['reserve_price'] > 0 && $formData['reserve_price'] < $formData['starting_bid']) {
                $errors['reserve_price'] = 'Reserve price must be greater than starting bid';
            }
            if ($formData['bid_increment'] <= 0) {
                $errors['bid_increment'] = 'Please enter a valid bid increment';
            }
        } elseif ($formData['sale_type'] === 'hybrid') {
            if ($formData['starting_bid'] <= 0) {
                $errors['starting_bid'] = 'Please enter a valid starting bid';
            }
            if ($formData['buy_it_now_price'] <= 0) {
                $errors['buy_it_now_price'] = 'Please enter a valid Buy Now price';
            }
            if ($formData['buy_it_now_price'] <= $formData['starting_bid']) {
                $errors['buy_it_now_price'] = 'Buy Now price must be greater than starting bid';
            }
        }

        if (empty($errors)) {
            // $formData only has step 2 fields — so $listingData's title/description survive
            $listingData = array_merge($listingData, $formData);
            $_SESSION['listing_data'] = $listingData;
            redirect(SITE_URL . '/seller/create-listing.php?step=3');
        } else {
            $listingData = array_merge($listingData, $formData);
            $_SESSION['listing_data'] = $listingData;
        }

    // ---------------- STEP 3 ----------------
    } elseif ($step == 3) {
        $listingData = $_SESSION['listing_data'] ?? [];

        if (empty($formData['images']['name'][0])) {
            $errors['images'] = 'Please upload at least one image';
        } else {
            $totalFiles = count($formData['images']['name']);
            if ($totalFiles > MAX_IMAGES_PER_LISTING) {
                $errors['images'] = 'Maximum ' . MAX_IMAGES_PER_LISTING . ' images allowed';
            }
            for ($i = 0; $i < $totalFiles; $i++) {
                $ext = strtolower(pathinfo($formData['images']['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                    $errors['images'] = 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
                    break;
                }
                if ($formData['images']['size'][$i] > MAX_FILE_SIZE) {
                    $errors['images'] = 'File too large. Maximum: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
                    break;
                }
            }
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $finalData = $listingData;
                $finalData['seller_id'] = $userId;
                $finalData['slug'] = slugify($finalData['title'] ?? '');

                // Safety: if title is empty, abort
                if (empty($finalData['title'])) {
                    throw new Exception('Title is missing. Please restart the listing creation.');
                }

                $categoryId = null;
                if (!empty($finalData['subcategory_id'])) {
                    $check = $db->fetchOne("SELECT id FROM categories WHERE id = ? AND is_active = 1", [$finalData['subcategory_id']]);
                    if ($check) $categoryId = $finalData['subcategory_id'];
                }
                if (empty($categoryId) && !empty($finalData['category_id'])) {
                    $check = $db->fetchOne("SELECT id FROM categories WHERE id = ? AND is_active = 1", [$finalData['category_id']]);
                    if ($check) $categoryId = $finalData['category_id'];
                }

                $listingId = $db->insert(
                    "INSERT INTO listings (
                        seller_id, category_id, title, slug, description, 
                        `condition`, sale_type, price, starting_bid, reserve_price,
                        current_bid, bid_increment, auction_start, auction_end, auto_extend,
                        buy_it_now_price, quantity, is_featured, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), ?, ?, ?, ?, 'active')",
                    [
                        $userId,
                        $categoryId,
                        $finalData['title'],
                        $finalData['slug'],
                        $finalData['description'],
                        $finalData['condition'] ?? 'good',
                        $finalData['sale_type'],
                        $finalData['sale_type'] === 'fixed' ? $finalData['price'] : null,
                        $finalData['sale_type'] !== 'fixed' ? $finalData['starting_bid'] : null,
                        $finalData['sale_type'] !== 'fixed' && !empty($finalData['reserve_price']) ? $finalData['reserve_price'] : null,
                        $finalData['sale_type'] !== 'fixed' ? $finalData['starting_bid'] : null,
                        $finalData['sale_type'] !== 'fixed' ? $finalData['bid_increment'] : 1,
                        $finalData['sale_type'] !== 'fixed' ? $finalData['auction_duration'] : null,
                        $finalData['sale_type'] !== 'fixed' ? $finalData['auto_extend'] : 0,
                        $finalData['sale_type'] === 'hybrid' ? $finalData['buy_it_now_price'] : null,
                        $finalData['quantity'] ?? 1,
                        $finalData['is_featured'] ?? 0
                    ]
                );

                $uploadedFiles = $_FILES['images'];
                $imagePaths = [];

                if (!is_dir(LISTING_IMAGES_PATH)) {
                    mkdir(LISTING_IMAGES_PATH, 0777, true);
                }

                for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
                    if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) continue;

                    $file = [
                        'name'     => $uploadedFiles['name'][$i],
                        'type'     => $uploadedFiles['type'][$i],
                        'tmp_name' => $uploadedFiles['tmp_name'][$i],
                        'error'    => $uploadedFiles['error'][$i],
                        'size'     => $uploadedFiles['size'][$i]
                    ];

                    $result = uploadFile($file, LISTING_IMAGES_PATH);
                    if ($result['success']) {
                        $imagePaths[] = $result['filename'];
                    }
                }

                foreach ($imagePaths as $index => $path) {
                    $db->insert(
                        "INSERT INTO listing_images (listing_id, image_url, is_primary, sort_order) 
                         VALUES (?, ?, ?, ?)",
                        [$listingId, $path, $index === 0 ? 1 : 0, $index]
                    );
                }

                $db->commit();

                unset($_SESSION['listing_data']);

                setSuccess('Listing created successfully!');
                redirect(SITE_URL . '/seller/manage-listings.php');

            } catch (Exception $e) {
                if ($db->getConnection()->inTransaction()) {
                    $db->rollback();
                }
                error_log('Create Listing Error: ' . $e->getMessage());
                setError('Failed to create listing. Please try again.');
            }
        }
    }
}

$listingData = $_SESSION['listing_data'] ?? [];

if (!empty($errors) && !empty($formData)) {
    $listingData = array_merge($listingData, $formData);
}

$csrfToken = Session::generateCSRFToken();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <?php include __DIR__ . '/../includes/seller-sidebar.php'; ?>

        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-plus-circle"></i> Create New Listing</h4>
                </div>
                <div class="card-body">

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h6><i class="bi bi-exclamation-triangle"></i> Please fix the following:</h6>
                            <ul class="mb-0">
                                <?php foreach ($errors as $msg): ?>
                                    <li><?php echo htmlspecialchars($msg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-4 text-center">
                            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">
                                <span class="step-number">1</span>
                                <div class="step-label">Basic Info</div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">
                                <span class="step-number">2</span>
                                <div class="step-label">Pricing</div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                                <span class="step-number">3</span>
                                <div class="step-label">Images</div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" id="listingForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <?php if ($step == 1): ?>
                            <div class="form-section">
                                <h5 class="section-title"><i class="bi bi-info-circle"></i> Basic Information</h5>

                                <div class="mb-3">
                                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                           id="title" name="title" 
                                           value="<?php echo htmlspecialchars($listingData['title'] ?? ''); ?>"
                                           required minlength="5" maxlength="200"
                                           placeholder="e.g., iPhone 13 Pro Max 256GB - Excellent Condition">
                                    <?php if (isset($errors['title'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['title']; ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">Be descriptive. Include brand, model, color, and condition.</small>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                        <select class="form-select <?php echo isset($errors['category']) ? 'is-invalid' : ''; ?>" 
                                                id="category_id" name="category_id"
                                                onchange="loadSubcategories(this.value)">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>" 
                                                        data-icon="<?php echo getCategoryIcon($cat['slug']); ?>"
                                                        <?php echo (($listingData['category_id'] ?? 0) == $cat['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['category'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['category']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="subcategory_id" class="form-label">Subcategory (Optional)</label>
                                        <select class="form-select" id="subcategory_id" name="subcategory_id">
                                            <option value="">Select Subcategory</option>
                                            <?php if (!empty($subCategories)): ?>
                                                <?php foreach ($subCategories as $sub): ?>
                                                    <option value="<?php echo $sub['id']; ?>"
                                                            data-icon="<?php echo getCategoryIcon($sub['slug']); ?>"
                                                            <?php echo (($listingData['subcategory_id'] ?? 0) == $sub['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($sub['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <div id="categoryPreview" class="mt-3" style="display: none;">
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i id="previewIcon" class="bi bi-tag fs-3 me-3"></i>
                                        <div>
                                            <strong>Selected Category:</strong>
                                            <span id="previewName"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3 mt-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                                              id="description" name="description" rows="6"
                                              required minlength="20"
                                              placeholder="Describe your item in detail..."><?php echo htmlspecialchars($listingData['description'] ?? ''); ?></textarea>
                                    <?php if (isset($errors['description'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['description']; ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        <span id="charCount"><?php echo strlen($listingData['description'] ?? ''); ?></span> characters (minimum 20).
                                    </small>
                                </div>

                                <div class="mb-3">
                                    <label for="condition" class="form-label">Condition <span class="text-danger">*</span></label>
                                    <select class="form-select" id="condition" name="condition">
                                        <?php foreach (['new','like_new','good','fair','poor'] as $c): ?>
                                            <option value="<?php echo $c; ?>" <?php echo ($listingData['condition'] ?? 'good') == $c ? 'selected' : ''; ?>>
                                                <?php echo getConditionLabel($c); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantity</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                           value="<?php echo $listingData['quantity'] ?? 1; ?>"
                                           min="1" max="999">
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-arrow-right"></i> Next Step
                                    </button>
                                </div>
                            </div>

                        <?php elseif ($step == 2): ?>
                            <div class="form-section">
                                <h5 class="section-title"><i class="bi bi-currency-naira"></i> Pricing & Sale Type</h5>

                                <div class="mb-3">
                                    <label class="form-label">Sale Type <span class="text-danger">*</span></label>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="form-check card p-3 <?php echo ($listingData['sale_type'] ?? 'fixed') === 'fixed' ? 'border-primary' : ''; ?>" data-sale-type="fixed">
                                                <input class="form-check-input" type="radio" name="sale_type" 
                                                       id="sale_type_fixed" value="fixed"
                                                       <?php echo ($listingData['sale_type'] ?? 'fixed') === 'fixed' ? 'checked' : ''; ?>>
                                                <label class="form-check-label w-100" for="sale_type_fixed">
                                                    <i class="bi bi-tag fs-3"></i>
                                                    <div><strong>Fixed Price</strong></div>
                                                    <small class="text-muted">Set a price, buyer buys instantly</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check card p-3 <?php echo ($listingData['sale_type'] ?? '') === 'auction' ? 'border-primary' : ''; ?>" data-sale-type="auction">
                                                <input class="form-check-input" type="radio" name="sale_type" 
                                                       id="sale_type_auction" value="auction"
                                                       <?php echo ($listingData['sale_type'] ?? '') === 'auction' ? 'checked' : ''; ?>>
                                                <label class="form-check-label w-100" for="sale_type_auction">
                                                    <i class="bi bi-gavel fs-3"></i>
                                                    <div><strong>Auction</strong></div>
                                                    <small class="text-muted">Buyers bid, highest wins</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check card p-3 <?php echo ($listingData['sale_type'] ?? '') === 'hybrid' ? 'border-primary' : ''; ?>" data-sale-type="hybrid">
                                                <input class="form-check-input" type="radio" name="sale_type" 
                                                       id="sale_type_hybrid" value="hybrid"
                                                       <?php echo ($listingData['sale_type'] ?? '') === 'hybrid' ? 'checked' : ''; ?>>
                                                <label class="form-check-label w-100" for="sale_type_hybrid">
                                                    <i class="bi bi-arrow-left-right fs-3"></i>
                                                    <div><strong>Hybrid</strong></div>
                                                    <small class="text-muted">Both auction and Buy Now</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="fixedFields" class="sale-type-fields" 
                                     style="display: <?php echo ($listingData['sale_type'] ?? 'fixed') === 'fixed' ? 'block' : 'none'; ?>;">
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">₦</span>
                                            <input type="number" 
                                                   class="form-control <?php echo isset($errors['price']) ? 'is-invalid' : ''; ?>" 
                                                   id="price" name="price" 
                                                   value="<?php echo $listingData['price'] ?? ''; ?>"
                                                   step="0.01" min="0.01" placeholder="0.00">
                                        </div>
                                        <?php if (isset($errors['price'])): ?>
                                            <div class="text-danger small mt-1"><?php echo $errors['price']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div id="auctionFields" class="sale-type-fields"
                                     style="display: <?php echo in_array($listingData['sale_type'] ?? '', ['auction', 'hybrid']) ? 'block' : 'none'; ?>;">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="starting_bid" class="form-label">Starting Bid <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">₦</span>
                                                <input type="number" 
                                                       class="form-control <?php echo isset($errors['starting_bid']) ? 'is-invalid' : ''; ?>" 
                                                       id="starting_bid" name="starting_bid" 
                                                       value="<?php echo $listingData['starting_bid'] ?? ''; ?>"
                                                       step="0.01" min="0.01" placeholder="0.00">
                                            </div>
                                            <?php if (isset($errors['starting_bid'])): ?>
                                                <div class="text-danger small mt-1"><?php echo $errors['starting_bid']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="reserve_price" class="form-label">Reserve Price (Optional)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₦</span>
                                                <input type="number" 
                                                       class="form-control <?php echo isset($errors['reserve_price']) ? 'is-invalid' : ''; ?>" 
                                                       id="reserve_price" name="reserve_price" 
                                                       value="<?php echo $listingData['reserve_price'] ?? ''; ?>"
                                                       step="0.01" min="0" placeholder="0.00">
                                            </div>
                                            <?php if (isset($errors['reserve_price'])): ?>
                                                <div class="text-danger small mt-1"><?php echo $errors['reserve_price']; ?></div>
                                            <?php endif; ?>
                                            <small class="text-muted">Minimum price you'll accept.</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="bid_increment" class="form-label">Bid Increment <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">₦</span>
                                                <input type="number" 
                                                       class="form-control <?php echo isset($errors['bid_increment']) ? 'is-invalid' : ''; ?>" 
                                                       id="bid_increment" name="bid_increment" 
                                                       value="<?php echo $listingData['bid_increment'] ?? 1; ?>"
                                                       step="0.01" min="0.01" placeholder="1.00">
                                            </div>
                                            <?php if (isset($errors['bid_increment'])): ?>
                                                <div class="text-danger small mt-1"><?php echo $errors['bid_increment']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="auction_duration" class="form-label">Auction Duration <span class="text-danger">*</span></label>
                                            <select class="form-select" id="auction_duration" name="auction_duration">
                                                <?php foreach ([3,5,7,10] as $d): ?>
                                                    <option value="<?php echo $d; ?>" <?php echo ($listingData['auction_duration'] ?? 7) == $d ? 'selected' : ''; ?>>
                                                        <?php echo $d; ?> Days
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="auto_extend" name="auto_extend"
                                                       <?php echo !empty($listingData['auto_extend']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="auto_extend">
                                                    Auto-extend (2 minutes) if bid placed in last 2 minutes
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="hybridFields" class="sale-type-fields"
                                     style="display: <?php echo ($listingData['sale_type'] ?? '') === 'hybrid' ? 'block' : 'none'; ?>;">
                                    <div class="mb-3">
                                        <label for="buy_it_now_price" class="form-label">Buy Now Price <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">₦</span>
                                            <input type="number" 
                                                   class="form-control <?php echo isset($errors['buy_it_now_price']) ? 'is-invalid' : ''; ?>" 
                                                   id="buy_it_now_price" name="buy_it_now_price" 
                                                   value="<?php echo $listingData['buy_it_now_price'] ?? ''; ?>"
                                                   step="0.01" min="0.01" placeholder="0.00">
                                        </div>
                                        <?php if (isset($errors['buy_it_now_price'])): ?>
                                            <div class="text-danger small mt-1"><?php echo $errors['buy_it_now_price']; ?></div>
                                        <?php endif; ?>
                                        <small class="text-muted">Must be higher than starting bid.</small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured"
                                               <?php echo !empty($listingData['is_featured']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_featured">
                                            <i class="bi bi-star-fill text-warning"></i> Feature this listing (additional fee applies)
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="<?php echo SITE_URL; ?>/seller/create-listing.php?step=1" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Previous
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-arrow-right"></i> Next Step
                                    </button>
                                </div>
                            </div>

                        <?php elseif ($step == 3): ?>
                            <div class="form-section">
                                <h5 class="section-title"><i class="bi bi-images"></i> Upload Images</h5>

                                <div class="text-center mb-3">
                                    <p class="text-muted">Upload up to <?php echo MAX_IMAGES_PER_LISTING; ?> images</p>
                                    <p class="text-muted small">First image will be the main/cover image</p>
                                </div>

                                <div class="drop-zone" id="dropZone">
                                    <div class="drop-zone-content">
                                        <i class="bi bi-cloud-upload fs-1"></i>
                                        <h5>Drag & Drop Images Here</h5>
                                        <p class="text-muted">or click to browse</p>
                                        <input type="file" class="d-none" id="fileInput" name="images[]" 
                                               multiple accept="image/*">
                                        <button type="button" class="btn btn-primary mt-2" 
                                                onclick="document.getElementById('fileInput').click();">
                                            <i class="bi bi-folder-open"></i> Select Images
                                        </button>
                                    </div>
                                </div>

                                <?php if (isset($errors['images'])): ?>
                                    <div class="alert alert-danger mt-3"><?php echo $errors['images']; ?></div>
                                <?php endif; ?>

                                <div id="imagePreview" class="row g-3 mt-3"></div>

                                <div id="uploadStatus" class="alert alert-info mt-3" style="display:none;">
                                    <i class="bi bi-hourglass-split"></i>
                                    <span id="uploadStatusText">Uploading images, please wait...</span>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="<?php echo SITE_URL; ?>/seller/create-listing.php?step=2" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Previous
                                    </a>
                                    <button type="submit" class="btn btn-success" id="submitBtn">
                                        <i class="bi bi-check-circle"></i> Create Listing
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.step { position: relative; display: inline-block; }
.step-number {
    display: inline-block; width: 40px; height: 40px; line-height: 40px;
    border-radius: 50%; background: #e9ecef; color: #6c757d; font-weight: bold;
}
.step.active .step-number { background: #0d6efd; color: white; }
.step-label { margin-top: 5px; font-size: 0.9rem; color: #6c757d; }
.step.active .step-label { color: #0d6efd; font-weight: 600; }

.sale-type-fields {
    margin-top: 1.5rem; padding: 1.5rem;
    background: #f8f9fa; border-radius: 8px;
}

.drop-zone {
    border: 2px dashed #dee2e6; border-radius: 8px;
    padding: 3rem; text-align: center; cursor: pointer;
    transition: border-color 0.3s ease;
}
.drop-zone:hover, .drop-zone.dragover {
    border-color: #0d6efd; background: #f8f9fa;
}

.image-preview-item { position: relative; display: inline-block; }
.image-preview-item .remove-image {
    position: absolute; top: -8px; right: -8px;
    width: 24px; height: 24px; border-radius: 50%;
    background: #dc3545; color: white; border: none;
    cursor: pointer; font-size: 12px; line-height: 24px; text-align: center;
}
</style>

<script>
// Subcategories data
const subcategoriesByParent = <?php echo json_encode($subcategoriesByParent); ?>;

function loadSubcategories(categoryId) {
    const select = document.getElementById('subcategory_id');
    const preview = document.getElementById('categoryPreview');
    const previewIcon = document.getElementById('previewIcon');
    const previewName = document.getElementById('previewName');
    const selectedOption = document.querySelector('#category_id option:checked');
    const icon = selectedOption ? selectedOption.dataset.icon : 'bi-tag';

    if (!categoryId) {
        select.innerHTML = '<option value="">Select Subcategory</option>';
        preview.style.display = 'none';
        return;
    }

    previewIcon.className = 'bi ' + icon + ' fs-3 me-3';
    previewName.textContent = selectedOption ? selectedOption.textContent.trim() : '';
    preview.style.display = 'block';

    if (subcategoriesByParent[categoryId]) {
        select.innerHTML = '<option value="">Select Subcategory</option>';
        subcategoriesByParent[categoryId].forEach(sub => {
            const option = document.createElement('option');
            option.value = sub.id;
            option.textContent = sub.name;
            option.dataset.icon = 'bi-tag';
            select.appendChild(option);
        });
    } else {
        select.innerHTML = '<option value="">No subcategories available</option>';
    }
}

// Toggle sale type fields
function toggleSaleType(type) {
    document.querySelectorAll('.sale-type-fields').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.form-check.card').forEach(c => c.classList.remove('border-primary'));

    if (type === 'fixed') {
        document.getElementById('fixedFields').style.display = 'block';
        const card = document.querySelector('[data-sale-type="fixed"]');
        if (card) card.classList.add('border-primary');
    } else if (type === 'auction') {
        document.getElementById('auctionFields').style.display = 'block';
        const card = document.querySelector('[data-sale-type="auction"]');
        if (card) card.classList.add('border-primary');
    } else if (type === 'hybrid') {
        document.getElementById('auctionFields').style.display = 'block';
        document.getElementById('hybridFields').style.display = 'block';
        const card = document.querySelector('[data-sale-type="hybrid"]');
        if (card) card.classList.add('border-primary');
    }
}

// Character counter
document.getElementById('description')?.addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

// Update preview on subcategory change
document.getElementById('subcategory_id')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const previewIcon = document.getElementById('previewIcon');
    const previewName = document.getElementById('previewName');
    if (this.value && selectedOption.dataset.icon) {
        previewIcon.className = 'bi ' + selectedOption.dataset.icon + ' fs-3 me-3';
        previewName.textContent = selectedOption.textContent.trim();
    }
});

document.addEventListener('DOMContentLoaded', function() {

    document.querySelectorAll('.form-check.card[data-sale-type]').forEach(card => {
        card.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const type = this.dataset.saleType;
            const radio = this.querySelector('input[name="sale_type"]');
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
                toggleSaleType(type);
            }
        }, true);
    });

    const checkedSaleType = document.querySelector('input[name="sale_type"]:checked');
    if (checkedSaleType && document.getElementById('fixedFields')) {
        toggleSaleType(checkedSaleType.value);
    }

    document.querySelectorAll('input[name="sale_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) toggleSaleType(this.value);
        });
    });

    const listingForm = document.getElementById('listingForm');
    if (listingForm) {
        listingForm.addEventListener('submit', function() {
            ['price', 'starting_bid', 'reserve_price', 'bid_increment', 'buy_it_now_price']
                .forEach(function(id) {
                    const el = document.getElementById(id);
                    if (el) el.required = false;
                });
        });
    }

    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const previewContainer = document.getElementById('imagePreview');
    const maxImages = <?php echo MAX_IMAGES_PER_LISTING; ?>;
    const maxFileSize = <?php echo MAX_FILE_SIZE; ?>;
    let uploadedFiles = [];

    if (!dropZone || !fileInput) return;

    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', e => { e.preventDefault(); dropZone.classList.remove('dragover'); });
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', function() { handleFiles(this.files); });

    function handleFiles(files) {
        const remaining = maxImages - uploadedFiles.length;
        if (files.length > remaining) {
            alert('You can only upload ' + maxImages + ' images. You have ' + uploadedFiles.length + ' already.');
            return;
        }
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (!file.type.startsWith('image/')) { alert('Only image files are allowed.'); continue; }
            if (file.size > maxFileSize) {
                alert('File size exceeds limit. Maximum is ' + (maxFileSize / 1024 / 1024) + 'MB.');
                continue;
            }
            uploadedFiles.push(file);
        }
        const dataTransfer = new DataTransfer();
        uploadedFiles.forEach(f => dataTransfer.items.add(f));
        fileInput.files = dataTransfer.files;
        displayPreviews();
    }

    function displayPreviews() {
        previewContainer.innerHTML = '';
        uploadedFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const col = document.createElement('div');
                col.className = 'col-md-3 col-4';
                col.innerHTML = `
                    <div class="image-preview-item">
                        <img src="${e.target.result}" class="img-fluid rounded" style="height: 150px; object-fit: cover; width: 100%;">
                        <button type="button" class="remove-image" onclick="removeImage(${index})">×</button>
                        ${index === 0 ? '<span class="badge bg-primary position-absolute bottom-0 start-0 m-1">Cover</span>' : ''}
                    </div>
                `;
                previewContainer.appendChild(col);
            };
            reader.readAsDataURL(file);
        });
    }

    window.removeImage = function(index) {
        uploadedFiles.splice(index, 1);
        const dataTransfer = new DataTransfer();
        uploadedFiles.forEach(f => dataTransfer.items.add(f));
        fileInput.files = dataTransfer.files;
        displayPreviews();
    };

    const form = document.getElementById('listingForm');
    const submitBtn = document.getElementById('submitBtn');
    const uploadStatus = document.getElementById('uploadStatus');
    const uploadStatusText = document.getElementById('uploadStatusText');

    form.addEventListener('submit', function() {
        if (uploadedFiles.length === 0) return;
        if (uploadStatus && uploadStatusText) {
            uploadStatus.style.display = 'block';
            uploadStatusText.textContent = 'Uploading ' + uploadedFiles.length + ' image(s). Please wait...';
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Uploading...';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>