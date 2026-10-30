<?php
// marketplace/classes/Auction.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/Listing.php';
require_once __DIR__ . '/User.php';

class Auction {
    private $db;
    private $listingId;
    private $listing;
    private $bids = [];
    private $highestBid = null;
    private $totalBids = 0;
    private $isActive = false;
    private $isEnded = false;
    private $isReserveMet = false;
    private $winner = null;

    public function __construct($listingId = null) {
        global $db;
        $this->db = $db ?? db();

        if ($listingId) {
            $this->load($listingId);
        }
    }

    public function load($listingId) {
        $this->listingId = $listingId;
        $this->listing = new Listing($listingId);

        if (!$this->listing->getId()) {
            throw new Exception('Listing not found');
        }

        if (!in_array($this->listing->getSaleType(), ['auction', 'hybrid'])) {
            throw new Exception('Listing is not an auction');
        }

        $this->bids = $this->db->fetchAll(
            "SELECT b.*, u.full_name as bidder_name, u.email as bidder_email
             FROM bids b
             JOIN users u ON b.buyer_id = u.id
             WHERE b.listing_id = ? AND b.is_retracted = 0
             ORDER BY b.bid_amount DESC, b.created_at DESC",
            [$listingId]
        );

        $this->totalBids = count($this->bids);

        if (!empty($this->bids)) {
            $this->highestBid = $this->bids[0];
        }

        $this->isActive = $this->listing->getStatus() === 'active';
        $this->isEnded = strtotime($this->listing->getAuctionEnd()) < time();
        $this->isReserveMet = $this->listing->getReservePrice() ?
            ($this->listing->getCurrentBid() >= $this->listing->getReservePrice()) : true;

        if ($this->isEnded && $this->isReserveMet && $this->highestBid) {
            $this->winner = new User($this->highestBid['buyer_id']);
        }

        return true;
    }

    public function placeBid($buyerId, $bidAmount, $isProxy = false, $maxProxyAmount = null) {
        if ($this->isEnded) {
            throw new Exception('Auction has ended');
        }

        if (!$this->isActive) {
            throw new Exception('Auction is not active');
        }

        if ($this->listing->getSellerId() == $buyerId) {
            throw new Exception('You cannot bid on your own listing');
        }

        $currentBid = $this->listing->getCurrentBid() ?? $this->listing->getStartingBid();
        $minBid = $currentBid + $this->listing->getBidIncrement();

        if ($bidAmount < $minBid) {
            throw new Exception('Bid must be at least ' . number_format($minBid, 2));
        }

        if ($isProxy && $maxProxyAmount < $bidAmount) {
            throw new Exception('Maximum bid must be greater than bid amount');
        }

        try {
            $this->db->beginTransaction();

            // Capture previous winner BEFORE the new bid overwrites it
            $previousWinner = $this->db->fetchOne(
                "SELECT b.buyer_id, u.full_name, u.email
                 FROM bids b
                 JOIN users u ON b.buyer_id = u.id
                 WHERE b.listing_id = ? 
                   AND b.is_winning = 1 
                   AND b.is_retracted = 0
                   AND b.buyer_id != ?
                 LIMIT 1",
                [$this->listingId, $buyerId]
            );

            $existingBid = $this->db->fetchOne(
                "SELECT id, bid_amount FROM bids 
                 WHERE listing_id = ? AND buyer_id = ? AND is_retracted = 0 
                 ORDER BY bid_amount DESC LIMIT 1",
                [$this->listingId, $buyerId]
            );

            if ($existingBid && $existingBid['bid_amount'] >= $bidAmount) {
                throw new Exception('You already have a higher or equal bid');
            }

            $this->db->insert(
                "INSERT INTO bids (listing_id, buyer_id, bid_amount, is_proxy_bid, max_proxy_amount, is_winning) 
                 VALUES (?, ?, ?, ?, ?, 1)",
                [$this->listingId, $buyerId, $bidAmount, $isProxy ? 1 : 0, $isProxy ? $maxProxyAmount : null]
            );

            $this->db->update(
                "UPDATE listings SET current_bid = ? WHERE id = ?",
                [$bidAmount, $this->listingId]
            );

            $this->db->update(
                "UPDATE bids SET is_winning = 0 
                 WHERE listing_id = ? AND buyer_id != ? AND is_retracted = 0",
                [$this->listingId, $buyerId]
            );

            // Detect final state after proxy resolution
            $finalWinnerId = $buyerId;
            if ($isProxy) {
                $this->handleProxyBidding($buyerId, $bidAmount, $maxProxyAmount);
                // If a proxy outbid our bidder, the winner changed
                $newWinnerBid = $this->db->fetchOne(
                    "SELECT buyer_id FROM bids 
                     WHERE listing_id = ? AND is_winning = 1 AND is_retracted = 0 LIMIT 1",
                    [$this->listingId]
                );
                if ($newWinnerBid) $finalWinnerId = $newWinnerBid['buyer_id'];
            } else {
                $this->checkProxyBids($buyerId, $bidAmount);
                $newWinnerBid = $this->db->fetchOne(
                    "SELECT buyer_id FROM bids 
                     WHERE listing_id = ? AND is_winning = 1 AND is_retracted = 0 LIMIT 1",
                    [$this->listingId]
                );
                if ($newWinnerBid) $finalWinnerId = $newWinnerBid['buyer_id'];
            }

            $this->db->commit();

            // =============================================
            // POST-COMMIT: SEND EMAILS
            // =============================================

            // 1) Outbid notification to previous winner (if any and not same person)
            if ($previousWinner && (int)$previousWinner['buyer_id'] !== (int)$finalWinnerId) {
                $outbidHtml = renderEmailTemplate('outbid', [
                    'USER_NAME'     => $previousWinner['full_name'],
                    'LISTING_TITLE' => $this->listing->getTitle(),
                    'NEW_BID'       => formatNaira($this->listing->getCurrentBid()),
                    'LISTING_LINK'  => SITE_URL . '/buyer/listing-detail.php?id=' . $this->listingId,
                ]);
                if (empty($outbidHtml)) {
                    $outbidHtml = '<h2>You have been outbid</h2>'
                                . '<p>Hi ' . htmlspecialchars($previousWinner['full_name']) . ',</p>'
                                . '<p>Someone placed a higher bid on <strong>' . htmlspecialchars($this->listing->getTitle()) . '</strong>.</p>'
                                . '<p>New current bid: <strong>' . formatNaira($this->listing->getCurrentBid()) . '</strong></p>'
                                . '<p><a href="' . SITE_URL . '/buyer/listing-detail.php?id=' . $this->listingId . '">Place a new bid</a></p>';
                }
                sendEmail($previousWinner['email'], 'You have been outbid — ' . SITE_NAME, $outbidHtml, $previousWinner['full_name']);
            }

            // 2) Bid placed notification to seller
            $seller = $this->db->fetchOne(
                "SELECT id, full_name, email FROM users WHERE id = ?",
                [$this->listing->getSellerId()]
            );
            if ($seller) {
                $bidPlacedHtml = renderEmailTemplate('bid-placed', [
                    'USER_NAME'     => $seller['full_name'],
                    'LISTING_TITLE' => $this->listing->getTitle(),
                    'BID_AMOUNT'    => formatNaira($bidAmount),
                    'LISTING_LINK'  => SITE_URL . '/seller/order-detail.php?id=' . $this->listingId,
                ]);
                if (empty($bidPlacedHtml)) {
                    $bidPlacedHtml = '<h2>New bid on your listing</h2>'
                                   . '<p>Hi ' . htmlspecialchars($seller['full_name']) . ',</p>'
                                   . '<p>A new bid of <strong>' . formatNaira($bidAmount) . '</strong> was placed on <strong>' . htmlspecialchars($this->listing->getTitle()) . '</strong>.</p>'
                                   . '<p><a href="' . SITE_URL . '/seller/manage-listings.php">View your listings</a></p>';
                }
                sendEmail($seller['email'], 'New bid on ' . $this->listing->getTitle(), $bidPlacedHtml, $seller['full_name']);
            }

            // Reload auction data
            $this->load($this->listingId);

            return [
                'success'     => true,
                'bid_amount'  => $bidAmount,
                'current_bid' => $this->listing->getCurrentBid(),
                'is_winning'  => $this->isWinning($finalWinnerId),
                'message'     => 'Bid placed successfully'
            ];

        } catch (Exception $e) {
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

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
            [$this->listingId, $buyerId, $bidAmount]
        );

        if (!empty($otherProxies)) {
            $highestProxy = $otherProxies[0];
            $newBid = min(
                $highestProxy['max_proxy_amount'],
                $bidAmount + $this->listing->getBidIncrement()
            );

            $this->db->insert(
                "INSERT INTO bids (listing_id, buyer_id, bid_amount, is_proxy_bid, max_proxy_amount, is_winning) 
                 VALUES (?, ?, ?, 1, ?, 1)",
                [
                    $this->listingId,
                    $highestProxy['buyer_id'],
                    $newBid,
                    $highestProxy['max_proxy_amount']
                ]
            );

            $this->db->update(
                "UPDATE listings SET current_bid = ? WHERE id = ?",
                [$newBid, $this->listingId]
            );

            $this->db->update(
                "UPDATE bids SET is_winning = 0 
                 WHERE listing_id = ? AND buyer_id = ? AND is_retracted = 0",
                [$this->listingId, $buyerId]
            );
        }
    }

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
            [$this->listingId, $buyerId, $bidAmount]
        );

        if ($highestProxy) {
            $newBid = min(
                $highestProxy['max_proxy_amount'],
                $bidAmount + $this->listing->getBidIncrement()
            );

            $this->db->insert(
                "INSERT INTO bids (listing_id, buyer_id, bid_amount, is_proxy_bid, max_proxy_amount, is_winning) 
                 VALUES (?, ?, ?, 1, ?, 1)",
                [
                    $this->listingId,
                    $highestProxy['buyer_id'],
                    $newBid,
                    $highestProxy['max_proxy_amount']
                ]
            );

            $this->db->update(
                "UPDATE listings SET current_bid = ? WHERE id = ?",
                [$newBid, $this->listingId]
            );

            $this->db->update(
                "UPDATE bids SET is_winning = 0 
                 WHERE listing_id = ? AND buyer_id = ? AND is_retracted = 0",
                [$this->listingId, $buyerId]
            );
        }
    }

    public function retractBid($buyerId, $bidId) {
        $bid = $this->db->fetchOne(
            "SELECT * FROM bids WHERE id = ? AND listing_id = ? AND buyer_id = ? AND is_retracted = 0",
            [$bidId, $this->listingId, $buyerId]
        );

        if (!$bid) {
            throw new Exception('Bid not found');
        }

        if ($this->isEnded) {
            throw new Exception('Cannot retract bid after auction ended');
        }

        if ($bid['is_winning'] == 1) {
            throw new Exception('Cannot retract a winning bid');
        }

        return $this->db->update(
            "UPDATE bids SET is_retracted = 1 WHERE id = ?",
            [$bidId]
        );
    }

    public function isWinning($userId) {
        $bid = $this->db->fetchOne(
            "SELECT id FROM bids 
             WHERE listing_id = ? AND buyer_id = ? AND is_winning = 1 AND is_retracted = 0",
            [$this->listingId, $userId]
        );
        return !empty($bid);
    }

    public function getUserBid($userId) {
        return $this->db->fetchOne(
            "SELECT * FROM bids 
             WHERE listing_id = ? AND buyer_id = ? AND is_retracted = 0
             ORDER BY bid_amount DESC LIMIT 1",
            [$this->listingId, $userId]
        );
    }

    public function getBidHistory($limit = 20) {
        return $this->db->fetchAll(
            "SELECT b.*, u.full_name as bidder_name
             FROM bids b
             JOIN users u ON b.buyer_id = u.id
             WHERE b.listing_id = ? AND b.is_retracted = 0
             ORDER BY b.bid_amount DESC, b.created_at DESC
             LIMIT ?",
            [$this->listingId, $limit]
        );
    }

    public function processEndedAuction() {
        if (!$this->isEnded) {
            throw new Exception('Auction has not ended yet');
        }

        if ($this->listing->getStatus() !== 'active') {
            throw new Exception('Auction already processed');
        }

        try {
            $this->db->beginTransaction();

            // Reserve not met
            if (!$this->isReserveMet) {
                $this->db->update(
                    "UPDATE listings SET status = 'ended' WHERE id = ?",
                    [$this->listingId]
                );

                // Notify seller that auction ended with no winner
                $seller = $this->db->fetchOne(
                    "SELECT id, full_name, email FROM users WHERE id = ?",
                    [$this->listing->getSellerId()]
                );

                $this->db->commit();

                if ($seller) {
                    $html = '<h2>Auction ended — reserve not met</h2>'
                          . '<p>Hi ' . htmlspecialchars($seller['full_name']) . ',</p>'
                          . '<p>Your auction for <strong>' . htmlspecialchars($this->listing->getTitle()) . '</strong> has ended.</p>'
                          . '<p>The reserve price was not met, so no sale was made.</p>'
                          . '<p><a href="' . SITE_URL . '/seller/manage-listings.php">Manage listings</a></p>';
                    sendEmail($seller['email'], 'Auction ended — ' . SITE_NAME, $html, $seller['full_name']);
                }

                return [
                    'success' => true,
                    'message' => 'Auction ended - Reserve not met',
                    'winner'  => null
                ];
            }

            // Reserve met — find winner
            if ($this->highestBid) {
                $orderData = [
                    'listing_id'   => $this->listingId,
                    'buyer_id'     => $this->highestBid['buyer_id'],
                    'seller_id'    => $this->listing->getSellerId(),
                    'total_amount' => $this->highestBid['bid_amount'],
                    'platform_fee' => calculateFee($this->highestBid['bid_amount'], PLATFORM_FEE_PERCENTAGE),
                    'net_amount'   => $this->highestBid['bid_amount'] - calculateFee($this->highestBid['bid_amount'], PLATFORM_FEE_PERCENTAGE)
                ];

                $this->db->update(
                    "UPDATE listings SET status = 'sold' WHERE id = ?",
                    [$this->listingId]
                );

                $orderNumber = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $orderId = $this->db->insert(
                    "INSERT INTO orders (
                        listing_id, buyer_id, seller_id, order_number, total_amount,
                        platform_fee, net_amount, payment_status, order_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')",
                    [
                        $this->listingId,
                        $this->highestBid['buyer_id'],
                        $this->listing->getSellerId(),
                        $orderNumber,
                        $orderData['total_amount'],
                        $orderData['platform_fee'],
                        $orderData['net_amount']
                    ]
                );

                $this->db->update(
                    "UPDATE bids SET is_winning = 0 WHERE listing_id = ?",
                    [$this->listingId]
                );

                $this->db->update(
                    "UPDATE bids SET is_winning = 1 
                     WHERE id = ? AND listing_id = ?",
                    [$this->highestBid['id'], $this->listingId]
                );

                $buyerLink  = '/buyer/order-detail.php?id=' . $orderId;
                $sellerLink = '/seller/order-detail.php?id=' . $orderId;

                // In-app notifications
                $this->db->insert(
                    "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
                     VALUES (?, 'auction', 'You Won!', ?, ?, NOW())",
                    [
                        $this->highestBid['buyer_id'],
                        "Congratulations! You won the auction for '" . $this->listing->getTitle() . "'. Please complete your payment.",
                        $buyerLink
                    ]
                );

                $this->db->insert(
                    "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
                     VALUES (?, 'auction', 'Auction Ended', ?, ?, NOW())",
                    [
                        $this->listing->getSellerId(),
                        "Your auction for '" . $this->listing->getTitle() . "' has ended with a winning bid of " . formatNaira($this->highestBid['bid_amount']) . ".",
                        $sellerLink
                    ]
                );

                $this->db->commit();

                // =============================================
                // POST-COMMIT EMAILS
                // =============================================
                $winner = $this->db->fetchOne(
                    "SELECT id, full_name, email FROM users WHERE id = ?",
                    [$this->highestBid['buyer_id']]
                );
                $seller = $this->db->fetchOne(
                    "SELECT id, full_name, email FROM users WHERE id = ?",
                    [$this->listing->getSellerId()]
                );

                // Winner email
                if ($winner) {
                    $wonHtml = renderEmailTemplate('auction-won', [
                        'USER_NAME'     => $winner['full_name'],
                        'LISTING_TITLE' => $this->listing->getTitle(),
                        'WINNING_BID'   => formatNaira($this->highestBid['bid_amount']),
                        'ORDER_LINK'    => SITE_URL . $buyerLink,
                        'ORDER_NUMBER'  => $orderNumber,
                    ]);
                    if (empty($wonHtml)) {
                        $wonHtml = '<h2>🎉 You won the auction!</h2>'
                                 . '<p>Hi ' . htmlspecialchars($winner['full_name']) . ',</p>'
                                 . '<p>Congratulations! You won <strong>' . htmlspecialchars($this->listing->getTitle()) . '</strong> with a bid of <strong>' . formatNaira($this->highestBid['bid_amount']) . '</strong>.</p>'
                                 . '<p>Order #: <strong>' . htmlspecialchars($orderNumber) . '</strong></p>'
                                 . '<p><a href="' . SITE_URL . $buyerLink . '">Complete your payment</a></p>';
                    }
                    sendEmail($winner['email'], 'You won the auction! — ' . SITE_NAME, $wonHtml, $winner['full_name']);
                }

                // Seller email
                if ($seller) {
                    $sellerHtml = '<h2>Your auction sold!</h2>'
                                . '<p>Hi ' . htmlspecialchars($seller['full_name']) . ',</p>'
                                . '<p>Your listing <strong>' . htmlspecialchars($this->listing->getTitle()) . '</strong> sold for <strong>' . formatNaira($this->highestBid['bid_amount']) . '</strong>.</p>'
                                . '<p>Net to you: <strong>' . formatNaira($orderData['net_amount']) . '</strong> (after platform fee)</p>'
                                . '<p><a href="' . SITE_URL . $sellerLink . '">View order</a></p>';
                    sendEmail($seller['email'], 'Your auction sold! — ' . SITE_NAME, $sellerHtml, $seller['full_name']);
                }

                return [
                    'success'     => true,
                    'message'     => 'Auction ended successfully',
                    'winner'      => $this->highestBid['buyer_id'],
                    'winning_bid' => $this->highestBid['bid_amount'],
                    'order_id'    => $orderId
                ];
            }

            $this->db->commit();
            return [
                'success' => false,
                'message' => 'No valid bids found'
            ];

        } catch (Exception $e) {
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    public function extendAuction() {
        if (!$this->isActive || $this->isEnded) {
            throw new Exception('Cannot extend auction');
        }

        if (!$this->listing->isAutoExtend()) {
            throw new Exception('Auto-extend is disabled for this auction');
        }

        $lastBid = $this->db->fetchOne(
            "SELECT created_at FROM bids 
             WHERE listing_id = ? AND is_retracted = 0
             ORDER BY created_at DESC LIMIT 1",
            [$this->listingId]
        );

        if ($lastBid) {
            $lastBidTime = strtotime($lastBid['created_at']);
            $auctionEndTime = strtotime($this->listing->getAuctionEnd());
            $windowSeconds = AUTO_EXTEND_MINUTES * 60;

            if (($auctionEndTime - $lastBidTime) <= $windowSeconds) {
                $newEndTime = date('Y-m-d H:i:s', $auctionEndTime + $windowSeconds);

                $this->db->update(
                    "UPDATE listings SET auction_end = ? WHERE id = ?",
                    [$newEndTime, $this->listingId]
                );

                $this->load($this->listingId);
                return true;
            }
        }

        return false;
    }

    public function toArray() {
        return [
            'listing_id'     => $this->listingId,
            'listing'        => $this->listing->toArray(),
            'total_bids'     => $this->totalBids,
            'highest_bid'    => $this->highestBid,
            'is_active'      => $this->isActive,
            'is_ended'       => $this->isEnded,
            'is_reserve_met' => $this->isReserveMet,
            'winner'         => $this->winner ? $this->winner->toArray() : null,
            'time_remaining' => $this->isEnded ? 'Ended' : $this->getTimeRemaining()
        ];
    }

    public function getTimeRemaining() {
        if ($this->isEnded) {
            return 'Ended';
        }

        $now = time();
        $end = strtotime($this->listing->getAuctionEnd());
        $diff = $end - $now;

        if ($diff <= 0) {
            return 'Ended';
        }

        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $minutes = floor(($diff % 3600) / 60);
        $seconds = $diff % 60;

        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m {$seconds}s";
        } elseif ($minutes > 0) {
            return "{$minutes}m {$seconds}s";
        } else {
            return "{$seconds}s";
        }
    }

    public function getListingId()    { return $this->listingId; }
    public function getListing()      { return $this->listing; }
    public function getBids()         { return $this->bids; }
    public function getTotalBids()    { return $this->totalBids; }
    public function getHighestBid()   { return $this->highestBid; }
    public function isActive()        { return $this->isActive; }
    public function isEnded()         { return $this->isEnded; }
    public function isReserveMet()    { return $this->isReserveMet; }
    public function getWinner()       { return $this->winner; }
}