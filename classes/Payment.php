<?php
// marketplace/classes/Payment.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Order.php';
require_once __DIR__ . '/User.php';

class Payment {
    private $db;
    private $id;
    private $orderId;
    private $userId;
    private $amount;
    private $type;
    private $status;
    private $gateway;
    private $transactionId;
    private $description;
    private $createdAt;
    private $updatedAt;
    private $order;
    private $user;

    public function __construct($paymentId = null) {
        global $db;
        $this->db = $db ?? db();

        if ($paymentId) {
            $this->load($paymentId);
        }
    }

    public function load($paymentId) {
        $payment = $this->db->fetchOne(
            "SELECT * FROM transactions WHERE id = ?",
            [$paymentId]
        );

        if ($payment) {
            $this->id = $payment['id'];
            $this->orderId = $payment['order_id'];
            $this->userId = $payment['user_id'];
            $this->amount = $payment['amount'];
            $this->type = $payment['type'];
            $this->status = $payment['status'];
            $this->gateway = $payment['gateway_transaction_id'] ? 'stripe' : null;
            $this->transactionId = $payment['gateway_transaction_id'];
            $this->description = $payment['description'];
            $this->createdAt = $payment['created_at'];
            $this->updatedAt = $payment['updated_at'] ?? $payment['created_at'];

            if ($this->orderId) {
                $this->order = new Order($this->orderId);
            }
            $this->user = new User($this->userId);

            return true;
        }
        return false;
    }

    public function processPayment($data) {
        $required = ['order_id', 'user_id', 'amount', 'payment_method'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        $this->orderId = $data['order_id'];
        $this->userId = $data['user_id'];
        $this->amount = $data['amount'];
        $this->type = 'payment';
        $this->status = 'pending';
        $this->description = 'Payment for order #' . $data['order_id'];

        $order = new Order($this->orderId);
        if (!$order->getId()) {
            throw new Exception('Order not found');
        }

        if ($order->getPaymentStatus() === 'paid') {
            throw new Exception('Order already paid');
        }

        $this->id = $this->db->insert(
            "INSERT INTO transactions (order_id, user_id, type, amount, description, status) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $this->orderId,
                $this->userId,
                $this->type,
                $this->amount,
                $this->description,
                $this->status
            ]
        );

        $result = $this->processWithGateway($data['payment_method'], $data);

        if ($result['success']) {
            $this->status = 'completed';
            $this->transactionId = $result['transaction_id'];
            $this->gateway = $data['payment_method'];

            $this->db->update(
                "UPDATE transactions SET 
                    status = 'completed', 
                    gateway_transaction_id = ? 
                 WHERE id = ?",
                [$this->transactionId, $this->id]
            );

            $order->updatePayment('paid', $this->transactionId);

            $orderNumber = $order->getOrderNumber();
            $formattedAmount = formatNaira($this->amount);
            $notificationMessage = 'Your payment of ' . $formattedAmount . ' for order #' . $orderNumber . ' was successful.';
            $notificationLink = '/buyer/order-detail.php?id=' . $this->orderId;

            $this->db->insert(
                "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
                 VALUES (?, 'payment', 'Payment Successful', ?, ?, NOW())",
                [
                    $this->userId,
                    $notificationMessage,
                    $notificationLink
                ]
            );

            return [
                'success' => true,
                'payment_id' => $this->id,
                'transaction_id' => $this->transactionId
            ];
        } else {
            $this->status = 'failed';
            $this->db->update(
                "UPDATE transactions SET status = 'failed' WHERE id = ?",
                [$this->id]
            );

            throw new Exception($result['message'] ?? 'Payment failed');
        }
    }

    private function processWithGateway($method, $data) {
        // Gateway integration placeholder.
        // Real payment processing is handled by the Flutterwave and Paystack
        // helper modules (config/flutterwave-helper.php, config/paystack-helper.php)
        // which are called directly from buyer/checkout.php.

        $success = true;
        $transactionId = 'TXN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Supported gateway identifiers for this helper class
        if (!in_array($method, ['stripe'], true)) {
            return [
                'success' => false,
                'message' => 'Payment method not supported by this helper'
            ];
        }

        return [
            'success' => $success,
            'transaction_id' => $transactionId
        ];
    }

    public function processRefund($data) {
        $required = ['order_id', 'user_id', 'amount'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        $orderId = $data['order_id'];
        $userId = $data['user_id'];
        $amount = $data['amount'];

        $order = new Order($orderId);
        if (!$order->getId()) {
            throw new Exception('Order not found');
        }

        $refundId = $this->db->insert(
            "INSERT INTO transactions (order_id, user_id, type, amount, description, status) 
             VALUES (?, ?, 'refund', ?, ?, 'completed')",
            [
                $orderId,
                $userId,
                $amount,
                'Refund for order #' . $order->getOrderNumber()
            ]
        );

        $newTotal = $order->getTotalAmount() - $amount;
        $this->db->update(
            "UPDATE orders SET total_amount = ? WHERE id = ?",
            [$newTotal, $orderId]
        );

        $orderNumber = $order->getOrderNumber();
        $formattedAmount = formatNaira($amount);
        $notificationMessage = 'A refund of ' . $formattedAmount . ' has been issued for order #' . $orderNumber . '.';
        $notificationLink = '/buyer/order-detail.php?id=' . $orderId;

        $this->db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
             VALUES (?, 'refund', 'Refund Issued', ?, ?, NOW())",
            [
                $userId,
                $notificationMessage,
                $notificationLink
            ]
        );

        return [
            'success' => true,
            'refund_id' => $refundId
        ];
    }

    public function processPayout($sellerId, $amount, $orderId = null) {
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than 0');
        }

        $seller = $this->db->fetchOne(
            "SELECT payout_method, payout_details FROM seller_profiles WHERE user_id = ?",
            [$sellerId]
        );

        if (!$seller) {
            throw new Exception('Seller profile not found');
        }

        $payoutId = $this->db->insert(
            "INSERT INTO transactions (order_id, user_id, type, amount, description, status) 
             VALUES (?, ?, 'payout', ?, ?, 'pending')",
            [
                $orderId,
                $sellerId,
                $amount,
                'Payout for order #' . ($orderId ? 'ORD-' . $orderId : '')
            ]
        );

        // In production, initiate the bank transfer through your payout provider here.

        $this->db->update(
            "UPDATE transactions SET status = 'completed' WHERE id = ?",
            [$payoutId]
        );

        return [
            'success' => true,
            'payout_id' => $payoutId
        ];
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'order' => $this->order ? $this->order->toArray() : null,
            'user_id' => $this->userId,
            'user' => $this->user ? $this->user->toArray() : null,
            'amount' => $this->amount,
            'type' => $this->type,
            'status' => $this->status,
            'gateway' => $this->gateway,
            'transaction_id' => $this->transactionId,
            'description' => $this->description,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }

    public function getId() { return $this->id; }
    public function getOrderId() { return $this->orderId; }
    public function getUserId() { return $this->userId; }
    public function getAmount() { return $this->amount; }
    public function getType() { return $this->type; }
    public function getStatus() { return $this->status; }
    public function getGateway() { return $this->gateway; }
    public function getTransactionId() { return $this->transactionId; }
    public function getDescription() { return $this->description; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }
    public function getOrder() { return $this->order; }
    public function getUser() { return $this->user; }
}