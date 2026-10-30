<?php
// marketplace/classes/Listing.php

// =============================================
// FIX: Use config/database.php instead of missing Database.php
// =============================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/User.php';

class Listing {
    private $db;
    private $id;
    private $sellerId;
    private $categoryId;
    private $title;
    private $slug;
    private $description;
    private $condition;
    private $saleType;
    private $price;
    private $startingBid;
    private $reservePrice;
    private $currentBid;
    private $bidIncrement;
    private $auctionStart;
    private $auctionEnd;
    private $autoExtend;
    private $buyItNowPrice;
    private $quantity;
    private $viewsCount;
    private $watchCount;
    private $isActive;
    private $isFeatured;
    private $status;
    private $createdAt;
    private $updatedAt;
    private $seller;
    private $images;

    public function __construct($listingId = null) {
        // =============================================
        // FIX: Use the existing db() function from config/database.php
        // =============================================
        global $db;
        $this->db = $db ?? db();
        
        if ($listingId) {
            $this->load($listingId);
        }
    }

    /**
     * Load listing from database
     */
    public function load($listingId) {
        $listing = $this->db->fetchOne(
            "SELECT * FROM listings WHERE id = ?",
            [$listingId]
        );
        
        if ($listing) {
            $this->id = $listing['id'];
            $this->sellerId = $listing['seller_id'];
            $this->categoryId = $listing['category_id'];
            $this->title = $listing['title'];
            $this->slug = $listing['slug'];
            $this->description = $listing['description'];
            $this->condition = $listing['condition'];
            $this->saleType = $listing['sale_type'];
            $this->price = $listing['price'];
            $this->startingBid = $listing['starting_bid'];
            $this->reservePrice = $listing['reserve_price'];
            $this->currentBid = $listing['current_bid'];
            $this->bidIncrement = $listing['bid_increment'];
            $this->auctionStart = $listing['auction_start'];
            $this->auctionEnd = $listing['auction_end'];
            $this->autoExtend = (bool)$listing['auto_extend'];
            $this->buyItNowPrice = $listing['buy_it_now_price'];
            $this->quantity = $listing['quantity'];
            $this->viewsCount = $listing['views_count'];
            $this->watchCount = $listing['watch_count'];
            $this->isActive = (bool)$listing['is_active'];
            $this->isFeatured = (bool)$listing['is_featured'];
            $this->status = $listing['status'];
            $this->createdAt = $listing['created_at'];
            $this->updatedAt = $listing['updated_at'];
            
            // Load seller
            $this->seller = new User($this->sellerId);
            
            // Load images
            $this->images = $this->db->fetchAll(
                "SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order",
                [$this->id]
            );
            
            return true;
        }
        return false;
    }

    /**
     * Create new listing
     */
    public function create($data) {
        $required = ['seller_id', 'title', 'description', 'sale_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        $this->sellerId = $data['seller_id'];
        $this->title = $data['title'];
        $this->slug = slugify($data['title']);
        $this->description = $data['description'];
        $this->condition = $data['condition'] ?? 'good';
        $this->saleType = $data['sale_type'];
        $this->categoryId = $data['category_id'] ?? null;
        $this->quantity = $data['quantity'] ?? 1;
        $this->isFeatured = $data['is_featured'] ?? 0;
        $this->status = 'active';

        // Handle pricing based on sale type
        if ($this->saleType === 'fixed') {
            $this->price = $data['price'] ?? 0;
            if ($this->price <= 0) {
                throw new Exception('Price must be greater than 0');
            }
        } elseif ($this->saleType === 'auction') {
            $this->startingBid = $data['starting_bid'] ?? 0;
            $this->reservePrice = $data['reserve_price'] ?? null;
            $this->bidIncrement = $data['bid_increment'] ?? 1;
            $this->auctionStart = date('Y-m-d H:i:s');
            $this->auctionEnd = date('Y-m-d H:i:s', strtotime('+' . ($data['auction_duration'] ?? 7) . ' days'));
            $this->autoExtend = $data['auto_extend'] ?? 1;
            $this->currentBid = $this->startingBid;
            
            if ($this->startingBid <= 0) {
                throw new Exception('Starting bid must be greater than 0');
            }
            if ($this->reservePrice && $this->reservePrice < $this->startingBid) {
                throw new Exception('Reserve price must be greater than starting bid');
            }
        } elseif ($this->saleType === 'hybrid') {
            $this->startingBid = $data['starting_bid'] ?? 0;
            $this->reservePrice = $data['reserve_price'] ?? null;
            $this->bidIncrement = $data['bid_increment'] ?? 1;
            $this->auctionStart = date('Y-m-d H:i:s');
            $this->auctionEnd = date('Y-m-d H:i:s', strtotime('+' . ($data['auction_duration'] ?? 7) . ' days'));
            $this->autoExtend = $data['auto_extend'] ?? 1;
            $this->currentBid = $this->startingBid;
            $this->buyItNowPrice = $data['buy_it_now_price'] ?? 0;
            
            if ($this->startingBid <= 0) {
                throw new Exception('Starting bid must be greater than 0');
            }
            if ($this->buyItNowPrice <= 0) {
                throw new Exception('Buy Now price must be greater than 0');
            }
            if ($this->buyItNowPrice <= $this->startingBid) {
                throw new Exception('Buy Now price must be greater than starting bid');
            }
        } else {
            throw new Exception('Invalid sale type');
        }

        // Insert listing
        $this->id = $this->db->insert(
            "INSERT INTO listings (
                seller_id, category_id, title, slug, description, 
                `condition`, sale_type, price, starting_bid, reserve_price,
                bid_increment, auction_start, auction_end, auto_extend,
                buy_it_now_price, quantity, is_featured, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->sellerId,
                $this->categoryId,
                $this->title,
                $this->slug,
                $this->description,
                $this->condition,
                $this->saleType,
                $this->price,
                $this->startingBid,
                $this->reservePrice,
                $this->bidIncrement,
                $this->auctionStart,
                $this->auctionEnd,
                $this->autoExtend ? 1 : 0,
                $this->buyItNowPrice,
                $this->quantity,
                $this->isFeatured ? 1 : 0,
                $this->status
            ]
        );

        // Handle images
        if (isset($data['images']) && !empty($data['images'])) {
            $this->addImages($data['images']);
        }

        return $this->id;
    }

    /**
     * Update listing
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception('Listing not loaded');
        }

        $allowedFields = ['title', 'category_id', 'description', 'condition', 'price', 'quantity', 'is_featured'];
        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $dbField = $field;
                if ($field === 'is_featured') {
                    $dbField = 'is_featured';
                }
                $updates[] = "{$dbField} = ?";
                $params[] = $data[$field];
            }
        }

        if (!empty($data['title']) && $data['title'] !== $this->title) {
            $this->slug = slugify($data['title']);
            $updates[] = "slug = ?";
            $params[] = $this->slug;
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $this->id;
        $query = "UPDATE listings SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $result = $this->db->update($query, $params);
        
        // Reload listing
        $this->load($this->id);
        
        return $result;
    }

    /**
     * Add images to listing
     */
    public function addImages($imageUrls) {
        if (!$this->id) {
            throw new Exception('Listing not loaded');
        }

        $currentImages = $this->db->fetchAll(
            "SELECT image_url FROM listing_images WHERE listing_id = ?",
            [$this->id]
        );
        $currentCount = count($currentImages);

        foreach ($imageUrls as $index => $imageUrl) {
            $this->db->insert(
                "INSERT INTO listing_images (listing_id, image_url, is_primary, sort_order) 
                 VALUES (?, ?, ?, ?)",
                [
                    $this->id,
                    $imageUrl,
                    $currentCount === 0 && $index === 0 ? 1 : 0,
                    $currentCount + $index
                ]
            );
        }

        // Reload images
        $this->images = $this->db->fetchAll(
            "SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order",
            [$this->id]
        );

        return true;
    }

    /**
     * Remove image from listing
     */
    public function removeImage($imageId) {
        if (!$this->id) {
            throw new Exception('Listing not loaded');
        }

        $image = $this->db->fetchOne(
            "SELECT image_url FROM listing_images WHERE id = ? AND listing_id = ?",
            [$imageId, $this->id]
        );

        if (!$image) {
            throw new Exception('Image not found');
        }

        // Delete file
        $filePath = LISTING_IMAGES_PATH . $image['image_url'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return $this->db->delete(
            "DELETE FROM listing_images WHERE id = ? AND listing_id = ?",
            [$imageId, $this->id]
        );
    }

    /**
     * Set primary image
     */
    public function setPrimaryImage($imageId) {
        if (!$this->id) {
            throw new Exception('Listing not loaded');
        }

        $this->db->update(
            "UPDATE listing_images SET is_primary = 0 WHERE listing_id = ?",
            [$this->id]
        );

        return $this->db->update(
            "UPDATE listing_images SET is_primary = 1 WHERE id = ? AND listing_id = ?",
            [$imageId, $this->id]
        );
    }

    /**
     * Increment view count
     */
    public function incrementViews() {
        if (!$this->id) {
            throw new Exception('Listing not loaded');
        }

        return $this->db->update(
            "UPDATE listings SET views_count = views_count + 1 WHERE id = ?",
            [$this->id]
        );
    }

    /**
     * Update status
     */
    public function updateStatus($status) {
        if (!$this->id) {
            throw new Exception('Listing not loaded');
        }

        $allowedStatuses = ['draft', 'active', 'sold', 'ended', 'cancelled'];
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception('Invalid status');
        }

        $result = $this->db->update(
            "UPDATE listings SET status = ? WHERE id = ?",
            [$status, $this->id]
        );

        $this->status = $status;
        return $result;
    }

    /**
     * Place a bid
     */
    public function placeBid($buyerId, $bidAmount, $isProxy = false, $maxProxyAmount = null) {
        if (!$this->id) {
            throw new Exception('Listing not loaded');
        }

        if (!in_array($this->saleType, ['auction', 'hybrid'])) {
            throw new Exception('This listing is not an auction');
        }

        if ($this->status !== 'active') {
            throw new Exception('Auction is not active');
        }

        if (strtotime($this->auctionEnd) < time()) {
            throw new Exception('Auction has ended');
        }

        if ($this->sellerId == $buyerId) {
            throw new Exception('You cannot bid on your own listing');
        }

        $currentBid = $this->currentBid ?? $this->startingBid;
        $minBid = $currentBid + $this->bidIncrement;

        if ($bidAmount < $minBid) {
            throw new Exception('Bid must be at least ' . number_format($minBid, 2));
        }

        if ($isProxy && $maxProxyAmount < $bidAmount) {
            throw new Exception('Maximum bid must be greater than bid amount');
        }

        try {
            $this->db->beginTransaction();

            // Place the bid
            $this->db->insert(
                "INSERT INTO bids (listing_id, buyer_id, bid_amount, is_proxy_bid, max_proxy_amount, is_winning) 
                 VALUES (?, ?, ?, ?, ?, 1)",
                [$this->id, $buyerId, $bidAmount, $isProxy ? 1 : 0, $isProxy ? $maxProxyAmount : null]
            );

            // Update current bid
            $this->db->update(
                "UPDATE listings SET current_bid = ? WHERE id = ?",
                [$bidAmount, $this->id]
            );

            // Mark other bids as not winning
            $this->db->update(
                "UPDATE bids SET is_winning = 0 
                 WHERE listing_id = ? AND buyer_id != ? AND is_retracted = 0",
                [$this->id, $buyerId]
            );

            // Check for proxy bids
            if ($isProxy) {
                $this->handleProxyBidding($buyerId, $bidAmount, $maxProxyAmount);
            } else {
                $this->checkProxyBids($buyerId, $bidAmount);
            }

            $this->db->commit();
            
            // Reload listing
            $this->load($this->id);
            
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Handle proxy bidding (when user places proxy bid)
     */
    private function handleProxyBidding($buyerId, $bidAmount, $maxProxyAmount) {
        $otherProxies = $this->db->fetchAll(
            "SELECT b.*, u.email, u.full_name
             FROM bids b
             JOIN users u ON b.buyer_id = u.id
             WHERE b.listing_id = ? 
               AND b.buyer_id != ? 
               AND b.is_proxy_bid = 1 
               AND b.is_retracted = 0
               AND b.max_proxy_amount > ?
             ORDER BY b.max_proxy_amount DESC",
            [$this->id, $buyerId, $bidAmount]
        );

        if (!empty($otherProxies)) {
            $highestProxy = $otherProxies[0];
            $newBid = min(
                $highestProxy['max_proxy_amount'],
                $bidAmount + $this->bidIncrement
            );
            
            $this->db->insert(
                "INSERT INTO bids (listing_id, buyer_id, bid_amount, is_proxy_bid, max_proxy_amount, is_winning) 
                 VALUES (?, ?, ?, 1, ?, 1)",
                [$this->id, $highestProxy['buyer_id'], $newBid, $highestProxy['max_proxy_amount']]
            );

            $this->db->update(
                "UPDATE listings SET current_bid = ? WHERE id = ?",
                [$newBid, $this->id]
            );

            $this->db->update(
                "UPDATE bids SET is_winning = 0 
                 WHERE listing_id = ? AND buyer_id = ? AND is_retracted = 0",
                [$this->id, $buyerId]
            );
        }
    }

    /**
     * Check if proxy bids can beat manual bid
     */
    private function checkProxyBids($buyerId, $bidAmount) {
        $highestProxy = $this->db->fetchOne(
            "SELECT b.*, u.email, u.full_name
             FROM bids b
             JOIN users u ON b.buyer_id = u.id
             WHERE b.listing_id = ? 
               AND b.buyer_id != ? 
               AND b.is_proxy_bid = 1 
               AND b.is_retracted = 0
               AND b.max_proxy_amount > ?
             ORDER BY b.max_proxy_amount DESC
             LIMIT 1",
            [$this->id, $buyerId, $bidAmount]
        );

        if ($highestProxy) {
            $newBid = min(
                $highestProxy['max_proxy_amount'],
                $bidAmount + $this->bidIncrement
            );
            
            $this->db->insert(
                "INSERT INTO bids (listing_id, buyer_id, bid_amount, is_proxy_bid, max_proxy_amount, is_winning) 
                 VALUES (?, ?, ?, 1, ?, 1)",
                [$this->id, $highestProxy['buyer_id'], $newBid, $highestProxy['max_proxy_amount']]
            );

            $this->db->update(
                "UPDATE listings SET current_bid = ? WHERE id = ?",
                [$newBid, $this->id]
            );

            $this->db->update(
                "UPDATE bids SET is_winning = 0 
                 WHERE listing_id = ? AND buyer_id = ? AND is_retracted = 0",
                [$this->id, $buyerId]
            );
        }
    }

    /**
     * Buy Now
     */
    public function buyNow($buyerId) {
        if (!$this->id) {
            throw new Exception('Listing not loaded');
        }

        if (!in_array($this->saleType, ['fixed', 'hybrid'])) {
            throw new Exception('Buy Now is not available for this listing');
        }

        if ($this->status !== 'active') {
            throw new Exception('Listing is not active');
        }

        if ($this->sellerId == $buyerId) {
            throw new Exception('You cannot buy your own listing');
        }

        if ($this->saleType === 'hybrid' && !$this->buyItNowPrice) {
            throw new Exception('Buy Now is not available for this listing');
        }

        $price = $this->saleType === 'fixed' ? $this->price : $this->buyItNowPrice;

        // Create order
        $orderData = [
            'listing_id' => $this->id,
            'buyer_id' => $buyerId,
            'seller_id' => $this->sellerId,
            'total_amount' => $price,
            'platform_fee' => calculateFee($price, PLATFORM_FEE_PERCENTAGE),
            'net_amount' => $price - calculateFee($price, PLATFORM_FEE_PERCENTAGE)
        ];

        return $orderData;
    }

    /**
     * Get listing data as array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'seller_id' => $this->sellerId,
            'seller' => $this->seller ? $this->seller->toArray() : null,
            'category_id' => $this->categoryId,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'condition' => $this->condition,
            'sale_type' => $this->saleType,
            'price' => $this->price,
            'starting_bid' => $this->startingBid,
            'reserve_price' => $this->reservePrice,
            'current_bid' => $this->currentBid,
            'bid_increment' => $this->bidIncrement,
            'auction_start' => $this->auctionStart,
            'auction_end' => $this->auctionEnd,
            'auto_extend' => $this->autoExtend,
            'buy_it_now_price' => $this->buyItNowPrice,
            'quantity' => $this->quantity,
            'views_count' => $this->viewsCount,
            'watch_count' => $this->watchCount,
            'is_active' => $this->isActive,
            'is_featured' => $this->isFeatured,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'images' => $this->images,
            'is_auction' => in_array($this->saleType, ['auction', 'hybrid']),
            'is_fixed' => $this->saleType === 'fixed',
            'has_buy_now' => $this->saleType === 'hybrid' && $this->buyItNowPrice > 0,
            'is_ended' => $this->is_auction && strtotime($this->auctionEnd) < time(),
            'is_reserve_met' => $this->is_auction && $this->reservePrice && $this->currentBid >= $this->reservePrice
        ];
    }

    // Getters
    public function getId() { return $this->id; }
    public function getSellerId() { return $this->sellerId; }
    public function getCategoryId() { return $this->categoryId; }
    public function getTitle() { return $this->title; }
    public function getSlug() { return $this->slug; }
    public function getDescription() { return $this->description; }
    public function getCondition() { return $this->condition; }
    public function getSaleType() { return $this->saleType; }
    public function getPrice() { return $this->price; }
    public function getStartingBid() { return $this->startingBid; }
    public function getReservePrice() { return $this->reservePrice; }
    public function getCurrentBid() { return $this->currentBid; }
    public function getBidIncrement() { return $this->bidIncrement; }
    public function getAuctionStart() { return $this->auctionStart; }
    public function getAuctionEnd() { return $this->auctionEnd; }
    public function isAutoExtend() { return $this->autoExtend; }
    public function getBuyItNowPrice() { return $this->buyItNowPrice; }
    public function getQuantity() { return $this->quantity; }
    public function getViewsCount() { return $this->viewsCount; }
    public function getWatchCount() { return $this->watchCount; }
    public function isActive() { return $this->isActive; }
    public function isFeatured() { return $this->isFeatured; }
    public function getStatus() { return $this->status; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }
    public function getSeller() { return $this->seller; }
    public function getImages() { return $this->images; }
    public function getPrimaryImage() { 
        foreach ($this->images as $img) {
            if ($img['is_primary']) {
                return $img['image_url'];
            }
        }
        return $this->images[0]['image_url'] ?? null;
    }
}