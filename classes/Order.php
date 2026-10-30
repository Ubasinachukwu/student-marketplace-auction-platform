<?php
// marketplace/classes/Order.php

// =============================================
// FIX: Use config/database.php instead of missing Database.php
// =============================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Listing.php';

class Order {
    private $db;
    private $id;
    private $listingId;
    private $buyerId;
    private $sellerId;
    private $orderNumber;
    private $totalAmount;
    private $shippingCost;
    private $taxAmount;
    private $platformFee;
    private $netAmount;
    private $paymentMethod;
    private $paymentStatus;
    private $paymentId;
    private $orderStatus;
    private $shippingAddress;
    private $trackingNumber;
    private $trackingCarrier;
    private $shippedDate;
    private $deliveredDate;
    private $buyerMessage;
    private $sellerNotes;
    private $createdAt;
    private $updatedAt;
    private $buyer;
    private $seller;
    private $listing;

    public function __construct($orderId = null) {
        // =============================================
        // FIX: Use the existing db() function from config/database.php
        // =============================================
        global $db;
        $this->db = $db ?? db();
        
        if ($orderId) {
            $this->load($orderId);
        }
    }

    /**
     * Load order from database
     */
    public function load($orderId) {
        $order = $this->db->fetchOne(
            "SELECT * FROM orders WHERE id = ?",
            [$orderId]
        );
        
        if ($order) {
            $this->id = $order['id'];
            $this->listingId = $order['listing_id'];
            $this->buyerId = $order['buyer_id'];
            $this->sellerId = $order['seller_id'];
            $this->orderNumber = $order['order_number'];
            $this->totalAmount = $order['total_amount'];
            $this->shippingCost = $order['shipping_cost'];
            $this->taxAmount = $order['tax_amount'];
            $this->platformFee = $order['platform_fee'];
            $this->netAmount = $order['net_amount'];
            $this->paymentMethod = $order['payment_method'];
            $this->paymentStatus = $order['payment_status'];
            $this->paymentId = $order['payment_id'];
            $this->orderStatus = $order['order_status'];
            $this->shippingAddress = $order['shipping_address'];
            $this->trackingNumber = $order['tracking_number'];
            $this->trackingCarrier = $order['tracking_carrier'];
            $this->shippedDate = $order['shipped_date'];
            $this->deliveredDate = $order['delivered_date'];
            $this->buyerMessage = $order['buyer_message'];
            $this->sellerNotes = $order['seller_notes'];
            $this->createdAt = $order['created_at'];
            $this->updatedAt = $order['updated_at'];
            
            // Load related data
            $this->buyer = new User($this->buyerId);
            $this->seller = new User($this->sellerId);
            $this->listing = new Listing($this->listingId);
            
            return true;
        }
        return false;
    }

    /**
     * Create new order
     */
    public function create($data) {
        $required = ['listing_id', 'buyer_id', 'seller_id', 'total_amount'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        $this->listingId = $data['listing_id'];
        $this->buyerId = $data['buyer_id'];
        $this->sellerId = $data['seller_id'];
        $this->totalAmount = $data['total_amount'];
        $this->shippingCost = $data['shipping_cost'] ?? 0;
        $this->taxAmount = $data['tax_amount'] ?? 0;
        $this->platformFee = $data['platform_fee'] ?? calculateFee($this->totalAmount, PLATFORM_FEE_PERCENTAGE);
        $this->netAmount = $data['net_amount'] ?? ($this->totalAmount - $this->platformFee);
        $this->orderNumber = $data['order_number'] ?? generateOrderNumber();
        $this->paymentMethod = $data['payment_method'] ?? null;
        $this->paymentStatus = 'pending';
        $this->orderStatus = 'pending';
        $this->shippingAddress = $data['shipping_address'] ?? null;
        $this->buyerMessage = $data['buyer_message'] ?? null;

        // Insert order
        $this->id = $this->db->insert(
            "INSERT INTO orders (
                listing_id, buyer_id, seller_id, order_number, total_amount,
                shipping_cost, tax_amount, platform_fee, net_amount, payment_method,
                payment_status, order_status, shipping_address, buyer_message
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->listingId,
                $this->buyerId,
                $this->sellerId,
                $this->orderNumber,
                $this->totalAmount,
                $this->shippingCost,
                $this->taxAmount,
                $this->platformFee,
                $this->netAmount,
                $this->paymentMethod,
                $this->paymentStatus,
                $this->orderStatus,
                $this->shippingAddress,
                $this->buyerMessage
            ]
        );

        // Update listing status
        $this->db->update(
            "UPDATE listings SET status = 'sold' WHERE id = ?",
            [$this->listingId]
        );

        return $this->id;
    }

    /**
     * Update order status
     */
    public function updateStatus($status, $notes = null) {
        if (!$this->id) {
            throw new Exception('Order not loaded');
        }

        $allowedStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'completed', 'cancelled', 'returned'];
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception('Invalid status');
        }

        $updateData = ['order_status' => $status];
        $params = [$status];

        if ($status === 'shipped') {
            $updateData['shipped_date'] = date('Y-m-d H:i:s');
        }

        if ($status === 'delivered') {
            $updateData['delivered_date'] = date('Y-m-d H:i:s');
        }

        if ($notes) {
            $updateData['seller_notes'] = $notes;
        }

        $setClause = [];
        foreach ($updateData as $key => $value) {
            $setClause[] = "{$key} = ?";
            $params[] = $value;
        }

        $params[] = $this->id;
        $query = "UPDATE orders SET " . implode(', ', $setClause) . " WHERE id = ?";
        
        $result = $this->db->update($query, $params);

        // If cancelled, restore listing
        if ($status === 'cancelled') {
            $this->db->update(
                "UPDATE listings SET status = 'active' WHERE id = ?",
                [$this->listingId]
            );
        }

        // Reload order
        $this->load($this->id);
        
        return $result;
    }

    /**
     * Update shipping information
     */
    public function updateShipping($trackingNumber, $carrier, $shippedDate = null) {
        if (!$this->id) {
            throw new Exception('Order not loaded');
        }

        if ($this->orderStatus !== 'confirmed') {
            throw new Exception('Order must be confirmed before shipping');
        }

        $shippedDate = $shippedDate ?? date('Y-m-d H:i:s');

        $result = $this->db->update(
            "UPDATE orders SET 
                tracking_number = ?, 
                tracking_carrier = ?, 
                shipped_date = ?,
                order_status = 'shipped'
             WHERE id = ?",
            [$trackingNumber, $carrier, $shippedDate, $this->id]
        );

        // Reload order
        $this->load($this->id);
        
        return $result;
    }

    /**
     * Update payment status
     */
    public function updatePayment($status, $paymentId = null) {
        if (!$this->id) {
            throw new Exception('Order not loaded');
        }

        $allowedStatuses = ['pending', 'paid', 'failed', 'refunded'];
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception('Invalid payment status');
        }

        $updates = ['payment_status' => $status];
        $params = [$status];

        if ($paymentId) {
            $updates['payment_id'] = $paymentId;
            $params[] = $paymentId;
        }

        if ($status === 'paid') {
            $updates['order_status'] = 'confirmed';
            $params[] = 'confirmed';
        }

        $setClause = [];
        foreach ($updates as $key => $value) {
            $setClause[] = "{$key} = ?";
            $params[] = $value;
        }

        $params[] = $this->id;
        $query = "UPDATE orders SET " . implode(', ', $setClause) . " WHERE id = ?";
        
        $result = $this->db->update($query, $params);

        // Reload order
        $this->load($this->id);
        
        return $result;
    }

    /**
     * Get order timeline
     */
    public function getTimeline() {
        $timeline = [
            ['status' => 'Order Placed', 'date' => $this->createdAt, 'icon' => 'bi-cart']
        ];

        if ($this->orderStatus == 'confirmed' || $this->orderStatus == 'shipped' || 
            $this->orderStatus == 'delivered' || $this->orderStatus == 'completed') {
            $timeline[] = ['status' => 'Order Confirmed', 'date' => $this->updatedAt, 'icon' => 'bi-check-circle'];
        }

        if ($this->orderStatus == 'shipped' || $this->orderStatus == 'delivered' || 
            $this->orderStatus == 'completed') {
            $shippedDate = $this->shippedDate ?? $this->updatedAt;
            $timeline[] = ['status' => 'Shipped', 'date' => $shippedDate, 'icon' => 'bi-truck'];
        }

        if ($this->orderStatus == 'delivered' || $this->orderStatus == 'completed') {
            $deliveredDate = $this->deliveredDate ?? $this->updatedAt;
            $timeline[] = ['status' => 'Delivered', 'date' => $deliveredDate, 'icon' => 'bi-box'];
        }

        if ($this->orderStatus == 'completed') {
            $timeline[] = ['status' => 'Completed', 'date' => $this->updatedAt, 'icon' => 'bi-check-all'];
        }

        if ($this->orderStatus == 'cancelled') {
            $timeline[] = ['status' => 'Cancelled', 'date' => $this->updatedAt, 'icon' => 'bi-x-circle'];
        }

        return $timeline;
    }

    /**
     * Get order summary
     */
    public function getSummary() {
        return [
            'subtotal' => $this->totalAmount - $this->shippingCost - $this->taxAmount,
            'shipping' => $this->shippingCost,
            'tax' => $this->taxAmount,
            'platform_fee' => $this->platformFee,
            'total' => $this->totalAmount,
            'net' => $this->netAmount
        ];
    }

    /**
     * Check if order can be cancelled
     */
    public function canCancel() {
        return in_array($this->orderStatus, ['pending', 'confirmed']);
    }

    /**
     * Check if order can be modified
     */
    public function canModify() {
        return in_array($this->orderStatus, ['pending', 'confirmed']);
    }

    /**
     * Get order data as array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'listing_id' => $this->listingId,
            'listing' => $this->listing ? $this->listing->toArray() : null,
            'buyer_id' => $this->buyerId,
            'buyer' => $this->buyer ? $this->buyer->toArray() : null,
            'seller_id' => $this->sellerId,
            'seller' => $this->seller ? $this->seller->toArray() : null,
            'order_number' => $this->orderNumber,
            'total_amount' => $this->totalAmount,
            'shipping_cost' => $this->shippingCost,
            'tax_amount' => $this->taxAmount,
            'platform_fee' => $this->platformFee,
            'net_amount' => $this->netAmount,
            'payment_method' => $this->paymentMethod,
            'payment_status' => $this->paymentStatus,
            'payment_id' => $this->paymentId,
            'order_status' => $this->orderStatus,
            'shipping_address' => $this->shippingAddress,
            'tracking_number' => $this->trackingNumber,
            'tracking_carrier' => $this->trackingCarrier,
            'shipped_date' => $this->shippedDate,
            'delivered_date' => $this->deliveredDate,
            'buyer_message' => $this->buyerMessage,
            'seller_notes' => $this->sellerNotes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'timeline' => $this->getTimeline(),
            'summary' => $this->getSummary(),
            'can_cancel' => $this->canCancel(),
            'can_modify' => $this->canModify()
        ];
    }

    // Getters
    public function getId() { return $this->id; }
    public function getListingId() { return $this->listingId; }
    public function getBuyerId() { return $this->buyerId; }
    public function getSellerId() { return $this->sellerId; }
    public function getOrderNumber() { return $this->orderNumber; }
    public function getTotalAmount() { return $this->totalAmount; }
    public function getShippingCost() { return $this->shippingCost; }
    public function getTaxAmount() { return $this->taxAmount; }
    public function getPlatformFee() { return $this->platformFee; }
    public function getNetAmount() { return $this->netAmount; }
    public function getPaymentMethod() { return $this->paymentMethod; }
    public function getPaymentStatus() { return $this->paymentStatus; }
    public function getPaymentId() { return $this->paymentId; }
    public function getOrderStatus() { return $this->orderStatus; }
    public function getShippingAddress() { return $this->shippingAddress; }
    public function getTrackingNumber() { return $this->trackingNumber; }
    public function getTrackingCarrier() { return $this->trackingCarrier; }
    public function getShippedDate() { return $this->shippedDate; }
    public function getDeliveredDate() { return $this->deliveredDate; }
    public function getBuyerMessage() { return $this->buyerMessage; }
    public function getSellerNotes() { return $this->sellerNotes; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }
    public function getBuyer() { return $this->buyer; }
    public function getSeller() { return $this->seller; }
    public function getListing() { return $this->listing; }
}