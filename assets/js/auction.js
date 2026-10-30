// marketplace/assets/js/auction.js

/**
 * Auction Module - Handles real-time bidding, countdown timers, and bid updates
 */

const Auction = {
    // Configuration
    config: {
        pollingInterval: 3000, // Check for updates every 3 seconds
        countdownInterval: 1000, // Update countdown every second
        debug: false
    },

    // State
    state: {
        listingId: null,
        isRunning: false,
        pollingTimer: null,
        countdownTimer: null,
        lastBidTime: null,
        currentBid: 0,
        userBid: null,
        isWinning: false
    },

    /**
     * Initialize auction for a listing
     */
    init: function(listingId, options = {}) {
        this.state.listingId = listingId;
        this.config = { ...this.config, ...options };

        // Check if auction elements exist
        if (!document.getElementById('auctionContainer')) {
            if (this.config.debug) console.warn('Auction container not found');
            return;
        }

        // Start the auction
        this.start();
    },

    /**
     * Start the auction
     */
    start: function() {
        if (this.state.isRunning) return;
        this.state.isRunning = true;

        // Start countdown timer
        this.startCountdown();

        // Start polling for updates
        this.startPolling();

        // Bind event listeners
        this.bindEvents();

        if (this.config.debug) console.log('Auction started for listing:', this.state.listingId);
    },

    /**
     * Stop the auction
     */
    stop: function() {
        this.state.isRunning = false;

        if (this.state.pollingTimer) {
            clearInterval(this.state.pollingTimer);
            this.state.pollingTimer = null;
        }

        if (this.state.countdownTimer) {
            clearInterval(this.state.countdownTimer);
            this.state.countdownTimer = null;
        }

        if (this.config.debug) console.log('Auction stopped');
    },

    /**
     * Start countdown timer
     */
    startCountdown: function() {
        const timerElement = document.getElementById('auctionTimer');
        if (!timerElement) return;

        const endTime = new Date(timerElement.dataset.end).getTime();
        if (!endTime) return;

        if (this.state.countdownTimer) {
            clearInterval(this.state.countdownTimer);
        }

        this.state.countdownTimer = setInterval(() => {
            const now = new Date().getTime();
            const distance = endTime - now;

            if (distance <= 0) {
                clearInterval(this.state.countdownTimer);
                timerElement.innerHTML = '<span class="text-danger fw-bold">Auction Ended</span>';
                this.handleAuctionEnd();
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            let display = '';
            if (days > 0) display += days + 'd ';
            display += String(hours).padStart(2, '0') + 'h ';
            display += String(minutes).padStart(2, '0') + 'm ';
            display += String(seconds).padStart(2, '0') + 's';

            timerElement.innerHTML = display;

            // Check if ending soon (less than 1 hour)
            if (distance < 3600000) {
                timerElement.classList.add('text-danger', 'fw-bold');
            } else {
                timerElement.classList.remove('text-danger', 'fw-bold');
            }
        }, this.config.countdownInterval);
    },

    /**
     * Start polling for bid updates
     */
    startPolling: function() {
        if (this.state.pollingTimer) {
            clearInterval(this.state.pollingTimer);
        }

        // Initial fetch
        this.fetchBidStatus();

        this.state.pollingTimer = setInterval(() => {
            this.fetchBidStatus();
        }, this.config.pollingInterval);
    },

    /**
     * Fetch current bid status from server
     */
    fetchBidStatus: function() {
        const url = '/api/auction/get-current-bid.php?listing_id=' + this.state.listingId;

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateUI(data);
            } else {
                if (this.config.debug) console.error('Error fetching bid status:', data.message);
            }
        })
        .catch(error => {
            if (this.config.debug) console.error('Error:', error);
        });
    },

    /**
     * Update UI with new bid data
     */
    updateUI: function(data) {
        // Update current bid
        const currentBidElement = document.getElementById('currentBid');
        if (currentBidElement) {
            const oldBid = this.state.currentBid;
            this.state.currentBid = data.current_bid;
            
            // Highlight if bid changed
            if (oldBid !== data.current_bid && oldBid !== 0) {
                currentBidElement.classList.add('bid-updated');
                setTimeout(() => {
                    currentBidElement.classList.remove('bid-updated');
                }, 2000);
            }
            
            currentBidElement.textContent = '$' + parseFloat(data.current_bid).toFixed(2);
        }

        // Update total bids
        const totalBidsElement = document.getElementById('totalBids');
        if (totalBidsElement) {
            totalBidsElement.textContent = data.total_bids + ' bids';
        }

        // Update reserve status
        const reserveStatusElement = document.getElementById('reserveStatus');
        if (reserveStatusElement) {
            if (data.reserve_price) {
                if (data.is_reserve_met) {
                    reserveStatusElement.innerHTML = '<span class="badge bg-success">Reserve Met ✅</span>';
                } else {
                    reserveStatusElement.innerHTML = '<span class="badge bg-warning">Reserve Not Met ❌</span>';
                }
            }
        }

        // Update min bid
        const minBidElement = document.getElementById('minBid');
        if (minBidElement) {
            minBidElement.textContent = '$' + parseFloat(data.min_bid).toFixed(2);
            const bidInput = document.getElementById('bidAmount');
            if (bidInput) {
                bidInput.min = data.min_bid;
                bidInput.placeholder = 'Minimum: $' + parseFloat(data.min_bid).toFixed(2);
            }
        }

        // Update winning status
        if (data.is_winning !== undefined) {
            this.state.isWinning = data.is_winning;
            const winningStatusElement = document.getElementById('winningStatus');
            if (winningStatusElement) {
                if (data.is_winning) {
                    winningStatusElement.innerHTML = '<span class="badge bg-success">You are winning!</span>';
                } else {
                    winningStatusElement.innerHTML = '<span class="badge bg-danger">You are outbid</span>';
                }
            }
        }

        // Update your bid
        if (data.your_bid) {
            this.state.userBid = data.your_bid;
            const yourBidElement = document.getElementById('yourBid');
            if (yourBidElement) {
                yourBidElement.textContent = '$' + parseFloat(data.your_bid).toFixed(2);
            }
        }

        // Check if auction ended
        if (data.is_ended) {
            this.handleAuctionEnd(data);
        }

        // Update bid history if needed
        if (data.total_bids !== this.state.lastBidCount) {
            this.updateBidHistory();
        }

        this.state.lastBidCount = data.total_bids;
    },

    /**
     * Update bid history
     */
    updateBidHistory: function() {
        const historyContainer = document.getElementById('bidHistory');
        if (!historyContainer) return;

        const url = '/api/auction/get-bid-history.php?listing_id=' + this.state.listingId;

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.history.length > 0) {
                let html = '';
                data.history.forEach((bid, index) => {
                    const isUser = bid.bidder_id === <?php echo Session::getUserId() ?: 'null'; ?>;
                    html += `
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom ${isUser ? 'bg-light' : ''}">
                            <div>
                                <span class="fw-bold">${isUser ? 'You' : bid.bidder}</span>
                                ${bid.is_proxy ? '<span class="badge bg-info ms-1">Proxy</span>' : ''}
                                ${bid.is_winning ? '<span class="badge bg-success ms-1">Winning</span>' : ''}
                            </div>
                            <div>
                                <span class="fw-bold">$${parseFloat(bid.amount).toFixed(2)}</span>
                                <small class="text-muted ms-2">${this.formatTime(bid.time)}</small>
                            </div>
                        </div>
                    `;
                });
                historyContainer.innerHTML = html;
            }
        })
        .catch(error => {
            if (this.config.debug) console.error('Error fetching bid history:', error);
        });
    },

    /**
     * Handle auction end
     */
    handleAuctionEnd: function(data = null) {
        // Stop polling
        this.stop();

        // Show ended message
        const container = document.getElementById('auctionContainer');
        if (container) {
            container.innerHTML = `
                <div class="alert alert-info text-center p-4">
                    <i class="bi bi-clock display-4 d-block"></i>
                    <h4 class="mt-2">Auction Ended</h4>
                    ${data && data.winner ? `<p>Winner: ${data.winner}</p>` : ''}
                    ${data && data.winning_bid ? `<p>Winning Bid: $${parseFloat(data.winning_bid).toFixed(2)}</p>` : ''}
                    ${data && data.order_id ? `<a href="/buyer/order-detail.php?id=${data.order_id}" class="btn btn-primary">View Order</a>` : ''}
                </div>
            `;
        }

        // Show winner notification
        if (data && data.winner) {
            const notification = document.createElement('div');
            notification.className = 'alert alert-success text-center p-4';
            notification.innerHTML = `
                <i class="bi bi-trophy display-4 d-block text-warning"></i>
                <h4 class="mt-2">🏆 Congratulations!</h4>
                <p>You won this auction!</p>
                <a href="/buyer/order-detail.php?id=${data.order_id}" class="btn btn-success">Complete Payment</a>
            `;
            document.getElementById('auctionContainer')?.prepend(notification);
        }

        if (this.config.debug) console.log('Auction ended');
    },

    /**
     * Place a bid
     */
    placeBid: function(bidAmount, isProxy = false, maxProxyAmount = null) {
        const formData = new FormData();
        formData.append('listing_id', this.state.listingId);
        formData.append('bid_amount', bidAmount);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

        if (isProxy && maxProxyAmount) {
            formData.append('proxy_bid', 'on');
            formData.append('max_proxy_amount', maxProxyAmount);
        }

        const bidButton = document.getElementById('placeBidBtn');
        if (bidButton) {
            bidButton.disabled = true;
            bidButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
        }

        fetch('/api/auction/place-bid.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                this.showNotification('Bid placed successfully!', 'success');
                // Update UI immediately
                this.fetchBidStatus();
            } else {
                // Show error message
                this.showNotification(data.message || 'Failed to place bid', 'danger');
            }
        })
        .catch(error => {
            this.showNotification('An error occurred. Please try again.', 'danger');
            if (this.config.debug) console.error('Error:', error);
        })
        .finally(() => {
            if (bidButton) {
                bidButton.disabled = false;
                bidButton.innerHTML = '<i class="bi bi-gavel"></i> Place Bid';
            }
        });
    },

    /**
     * Show notification
     */
    showNotification: function(message, type = 'info') {
        const container = document.getElementById('notificationContainer');
        if (!container) return;

        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        container.appendChild(alert);

        // Auto dismiss after 5 seconds
        setTimeout(() => {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    },

    /**
     * Format time
     */
    formatTime: function(datetime) {
        const date = new Date(datetime);
        return date.toLocaleTimeString();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function() {
        // Bid form submission
        const bidForm = document.getElementById('bidForm');
        if (bidForm) {
            bidForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(bidForm);
                const bidAmount = parseFloat(formData.get('bid_amount'));
                const isProxy = formData.get('proxy_bid') === 'on';
                const maxProxyAmount = isProxy ? parseFloat(formData.get('max_proxy_amount')) : null;

                if (isNaN(bidAmount) || bidAmount <= 0) {
                    this.showNotification('Please enter a valid bid amount', 'danger');
                    return;
                }

                if (isProxy && (isNaN(maxProxyAmount) || maxProxyAmount < bidAmount)) {
                    this.showNotification('Maximum bid must be greater than your bid', 'danger');
                    return;
                }

                this.placeBid(bidAmount, isProxy, maxProxyAmount);
            });
        }

        // Proxy bid toggle
        const proxyCheckbox = document.getElementById('proxyBid');
        if (proxyCheckbox) {
            proxyCheckbox.addEventListener('change', function() {
                const proxySection = document.getElementById('proxyBidSection');
                if (proxySection) {
                    proxySection.style.display = this.checked ? 'block' : 'none';
                }
            });
        }

        // Refresh button
        const refreshBtn = document.getElementById('refreshBids');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.fetchBidStatus();
                this.updateBidHistory();
                this.showNotification('Refreshed!', 'info');
            });
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('auctionContainer');
    if (container) {
        const listingId = container.dataset.listingId;
        if (listingId) {
            Auction.init(parseInt(listingId));
        }
    }
});

// Make Auction globally available
window.Auction = Auction;