<?php
// marketplace/seller/edit-listing.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and seller role
Session::requireSeller();

// =============================================
// FIX: Define constants if not defined in config
// =============================================
if (!defined('LISTING_IMAGES_PATH')) {
    define('LISTING_IMAGES_PATH', __DIR__ . '/../assets/uploads/listings/');
}
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
}
if (!defined('ALLOWED_EXTENSIONS')) {
    define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}

$db = db();
$userId = Session::getUserId();
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$listingId) {
    setError('Invalid listing ID');
    redirect(SITE_URL . '/seller/manage-listings.php');
}

// Get listing details
$listing = $db->fetchOne(
    "SELECT * FROM listings WHERE id = ? AND seller_id = ?",
    [$listingId, $userId]
);

if (!$listing) {
    setError('Listing not found');
    redirect(SITE_URL . '/seller/manage-listings.php');
}

// Get images
$images = $db->fetchAll(
    "SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order",
    [$listingId]
);

// Get categories
$categories = $db->fetchAll(
    "SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order"
);

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/seller/edit-listing.php?id=' . $listingId);
    }

    $formData = [
        'title' => sanitize($_POST['title'] ?? ''),
        'category_id' => isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0,
        'description' => sanitize($_POST['description'] ?? ''),
        'condition' => sanitize($_POST['condition'] ?? 'good'),
        'price' => isset($_POST['price']) ? (float)$_POST['price'] : 0,
        'quantity' => isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
    ];

    // Validate
    if (empty($formData['title'])) {
        $errors['title'] = 'Title is required';
    } elseif (strlen($formData['title']) < 5) {
        $errors['title'] = 'Title must be at least 5 characters';
    }

    if (empty($formData['category_id'])) {
        $errors['category'] = 'Please select a category';
    }

    if (empty($formData['description'])) {
        $errors['description'] = 'Description is required';
    } elseif (strlen($formData['description']) < 20) {
        $errors['description'] = 'Description must be at least 20 characters';
    }

    if ($listing['sale_type'] == 'fixed' && $formData['price'] <= 0) {
        $errors['price'] = 'Please enter a valid price';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Update listing
            $db->update(
                "UPDATE listings SET 
                    title = ?, 
                    category_id = ?, 
                    description = ?, 
                    `condition` = ?,
                    price = ?,
                    quantity = ?,
                    is_featured = ?
                 WHERE id = ? AND seller_id = ?",
                [
                    $formData['title'],
                    $formData['category_id'],
                    $formData['description'],
                    $formData['condition'],
                    $listing['sale_type'] == 'fixed' ? $formData['price'] : null,
                    $formData['quantity'],
                    $formData['is_featured'],
                    $listingId,
                    $userId
                ]
            );

            // Update slug
            $slug = slugify($formData['title']);
            $db->update(
                "UPDATE listings SET slug = ? WHERE id = ? AND seller_id = ?",
                [$slug, $listingId, $userId]
            );

            // Handle image uploads
            if (!empty($_FILES['images']['name'][0])) {
                // Create directory if it doesn't exist
                if (!is_dir(LISTING_IMAGES_PATH)) {
                    mkdir(LISTING_IMAGES_PATH, 0777, true);
                }

                $uploadedFiles = $_FILES['images'];
                $imagePaths = [];

                for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
                    if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $uploadedFiles['name'][$i],
                            'type' => $uploadedFiles['type'][$i],
                            'tmp_name' => $uploadedFiles['tmp_name'][$i],
                            'error' => $uploadedFiles['error'][$i],
                            'size' => $uploadedFiles['size'][$i]
                        ];

                        $result = uploadFile($file, LISTING_IMAGES_PATH);
                        if ($result['success']) {
                            $imagePaths[] = $result['filename'];
                        }
                    }
                }

                // Get current image count
                $currentImages = $db->fetchAll(
                    "SELECT image_url FROM listing_images WHERE listing_id = ?",
                    [$listingId]
                );
                $currentCount = count($currentImages);

                // Save new images
                foreach ($imagePaths as $index => $path) {
                    $db->insert(
                        "INSERT INTO listing_images (listing_id, image_url, is_primary, sort_order) 
                         VALUES (?, ?, ?, ?)",
                        [
                            $listingId,
                            $path,
                            $currentCount === 0 && $index === 0 ? 1 : 0,
                            $currentCount + $index
                        ]
                    );
                }
            }

            // Handle image deletion
            if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $imageId) {
                    $image = $db->fetchOne(
                        "SELECT image_url FROM listing_images WHERE id = ? AND listing_id = ?",
                        [$imageId, $listingId]
                    );
                    if ($image) {
                        $filePath = LISTING_IMAGES_PATH . $image['image_url'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        $db->delete(
                            "DELETE FROM listing_images WHERE id = ? AND listing_id = ?",
                            [$imageId, $listingId]
                        );
                    }
                }
            }

            // Set primary image if changed
            if (isset($_POST['primary_image']) && is_numeric($_POST['primary_image'])) {
                $primaryId = (int)$_POST['primary_image'];
                $db->update(
                    "UPDATE listing_images SET is_primary = 0 WHERE listing_id = ?",
                    [$listingId]
                );
                $db->update(
                    "UPDATE listing_images SET is_primary = 1 WHERE id = ? AND listing_id = ?",
                    [$primaryId, $listingId]
                );
            }

            $db->commit();
            setSuccess('Listing updated successfully!');
            redirect(SITE_URL . '/seller/manage-listings.php');

        } catch (Exception $e) {
            $db->rollback();
            setError('Failed to update listing: ' . $e->getMessage());
            error_log('Edit Listing Error: ' . $e->getMessage());
        }
    }
}

$csrfToken = Session::generateCSRFToken();
$pageJS = 'create-listing.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- ============================================
             SIDEBAR - Using centralized include file
        ============================================= -->
        <?php include __DIR__ . '/../includes/seller-sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-pencil"></i> Edit Listing</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="row g-4">
                            <div class="col-md-8">
                                <!-- Title -->
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                           id="title" 
                                           name="title" 
                                           value="<?php echo htmlspecialchars($listing['title']); ?>"
                                           required
                                           minlength="5"
                                           maxlength="200">
                                    <?php if (isset($errors['title'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['title']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Category -->
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select <?php echo isset($errors['category']) ? 'is-invalid' : ''; ?>" 
                                            id="category_id" 
                                            name="category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" 
                                                    <?php echo $listing['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['category'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['category']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Description -->
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                                              id="description" 
                                              name="description" 
                                              rows="6"
                                              required
                                              minlength="20"><?php echo htmlspecialchars($listing['description']); ?></textarea>
                                    <?php if (isset($errors['description'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['description']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <!-- Condition -->
                                <div class="mb-3">
                                    <label for="condition" class="form-label">Condition <span class="text-danger">*</span></label>
                                    <select class="form-select" id="condition" name="condition">
                                        <option value="new" <?php echo $listing['condition'] == 'new' ? 'selected' : ''; ?>>New</option>
                                        <option value="like_new" <?php echo $listing['condition'] == 'like_new' ? 'selected' : ''; ?>>Like New</option>
                                        <option value="good" <?php echo $listing['condition'] == 'good' ? 'selected' : ''; ?>>Good</option>
                                        <option value="fair" <?php echo $listing['condition'] == 'fair' ? 'selected' : ''; ?>>Fair</option>
                                        <option value="poor" <?php echo $listing['condition'] == 'poor' ? 'selected' : ''; ?>>Poor</option>
                                    </select>
                                </div>

                                <!-- Price (Fixed Price only) -->
                                <?php if ($listing['sale_type'] == 'fixed'): ?>
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">₦</span>
                                            <input type="number" 
                                                   class="form-control <?php echo isset($errors['price']) ? 'is-invalid' : ''; ?>" 
                                                   id="price" 
                                                   name="price" 
                                                   value="<?php echo $listing['price']; ?>"
                                                   step="0.01"
                                                   min="0.01">
                                            <?php if (isset($errors['price'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['price']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php elseif ($listing['sale_type'] != 'fixed'): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Current Bid</label>
                                        <div class="h4 text-primary"><?php echo formatNaira($listing['current_bid'] ?? $listing['starting_bid']); ?></div>
                                        <small class="text-muted">Starting bid: <?php echo formatNaira($listing['starting_bid']); ?></small>
                                    </div>
                                    <?php if ($listing['reserve_price']): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Reserve Price</label>
                                            <div><?php echo formatNaira($listing['reserve_price']); ?></div>
                                            <small class="text-muted">
                                                <?php echo ($listing['current_bid'] >= $listing['reserve_price']) ? '✅ Reserve met' : '❌ Reserve not met'; ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($listing['buy_it_now_price']): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Buy Now Price</label>
                                            <div><?php echo formatNaira($listing['buy_it_now_price']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label class="form-label">Auction Ends</label>
                                        <div><?php echo formatDateTime($listing['auction_end'] ?? ''); ?></div>
                                        <?php if (!empty($listing['auction_end'])): ?>
                                            <small class="text-muted"><?php echo timeRemaining($listing['auction_end']); ?> remaining</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Quantity -->
                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantity</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="quantity" 
                                           name="quantity" 
                                           value="<?php echo $listing['quantity']; ?>"
                                           min="1"
                                           max="999">
                                </div>

                                <!-- Featured -->
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="is_featured" 
                                               name="is_featured"
                                               <?php echo $listing['is_featured'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_featured">
                                            <i class="bi bi-star-fill text-warning"></i> Feature this listing
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <div>
                                        <span class="badge bg-<?php echo getStatusBadgeClass($listing['status']); ?> fs-6">
                                            <?php echo ucfirst($listing['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Images -->
                        <hr>
                        <h5 class="mb-3"><i class="bi bi-images"></i> Images</h5>

                        <?php if (!empty($images)): ?>
                            <div class="row g-3 mb-3">
                                <?php foreach ($images as $img): ?>
                                    <div class="col-md-3 col-4">
                                        <div class="position-relative">
                                            <img src="<?php echo getImageUrl($img['image_url'], 'listing'); ?>" 
                                                 class="img-fluid rounded" 
                                                 style="height: 150px; width: 100%; object-fit: cover;">
                                            <?php if ($img['is_primary']): ?>
                                                <span class="badge bg-primary position-absolute bottom-0 start-0 m-1">Cover</span>
                                            <?php endif; ?>
                                            <div class="position-absolute top-0 end-0 m-1">
                                                <button type="button" class="btn btn-sm btn-danger remove-image-btn" 
                                                        data-image-id="<?php echo $img['id']; ?>">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                            <?php if (!$img['is_primary']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary set-primary-btn"
                                                        data-image-id="<?php echo $img['id']; ?>"
                                                        style="position: absolute; bottom: 0; right: 0; margin: 1px;">
                                                    <i class="bi bi-star"></i>
                                                </button>
                                            <?php endif; ?>
                                            <input type="hidden" name="delete_images[]" id="delete_img_<?php echo $img['id']; ?>" value="">
                                            <input type="radio" name="primary_image" value="<?php echo $img['id']; ?>" 
                                                   <?php echo $img['is_primary'] ? 'checked' : ''; ?> 
                                                   style="position: absolute; top: 0; left: 0; opacity: 0;">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Upload New Images -->
                        <div class="drop-zone" id="dropZone">
                            <div class="drop-zone-content">
                                <i class="bi bi-cloud-upload fs-1"></i>
                                <h5>Add More Images</h5>
                                <p class="text-muted">Drag & drop or click to browse</p>
                                <input type="file" 
                                       class="d-none" 
                                       id="fileInput" 
                                       name="images[]" 
                                       multiple
                                       accept="image/*">
                                <button type="button" class="btn btn-primary mt-2" onclick="document.getElementById('fileInput').click();">
                                    <i class="bi bi-folder-open"></i> Select Images
                                </button>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            <a href="<?php echo SITE_URL; ?>/seller/manage-listings.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Remove image
    document.querySelectorAll('.remove-image-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const imageId = this.dataset.imageId;
            const hiddenInput = document.getElementById('delete_img_' + imageId);
            if (hiddenInput) {
                hiddenInput.value = imageId;
                this.closest('.col-md-3').style.opacity = '0.5';
                this.closest('.col-md-3').style.pointerEvents = 'none';
            }
        });
    });

    // Set primary image
    document.querySelectorAll('.set-primary-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const imageId = this.dataset.imageId;
            document.querySelectorAll('input[name="primary_image"]').forEach(radio => {
                radio.checked = radio.value == imageId;
            });
            // Update visual indicators
            document.querySelectorAll('.badge.bg-primary').forEach(badge => badge.remove());
            const parent = this.closest('.col-md-3').querySelector('.position-relative');
            const coverBadge = document.createElement('span');
            coverBadge.className = 'badge bg-primary position-absolute bottom-0 start-0 m-1';
            coverBadge.textContent = 'Cover';
            parent.appendChild(coverBadge);
        });
    });

    // Drop zone
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    
    if (dropZone && fileInput) {
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        });

        dropZone.addEventListener('click', function() {
            fileInput.click();
        });
    }

    // File input change - show feedback
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const dropZoneContent = document.querySelector('.drop-zone-content');
                if (dropZoneContent) {
                    dropZoneContent.innerHTML = `
                        <i class="bi bi-check-circle-fill fs-1 text-success"></i>
                        <h5>${this.files.length} image(s) selected</h5>
                        <p class="text-muted">Click submit to upload</p>
                    `;
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>