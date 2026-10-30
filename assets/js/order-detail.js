// marketplace/assets/js/order-detail.js
// Seller order-detail page interactions.
// Config is injected by an inline bootstrap block in seller/order-detail.php:
//   window.OrderDetailConfig = { siteUrl, csrfToken };

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const cfg = window.OrderDetailConfig || {};
        const siteUrl   = cfg.siteUrl   || '';
        const csrfToken = cfg.csrfToken || '';

        // =========================================
        // 1. Update order status buttons
        // =========================================
        document.querySelectorAll('.update-status-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const orderId = this.dataset.orderId;
                const status  = this.dataset.status;

                if (!confirm('Update order status to ' + status + '?')) return;

                fetch(siteUrl + '/api/orders/update-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'order_id=' + encodeURIComponent(orderId)
                        + '&status=' + encodeURIComponent(status)
                        + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to update order status');
                    }
                })
                .catch(function (err) {
                    console.error('Error:', err);
                    alert('An error occurred. Please try again.');
                });
            });
        });

        // =========================================
        // 2. Cancel order buttons
        // =========================================
        document.querySelectorAll('.cancel-order-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const orderId = this.dataset.orderId;

                if (!confirm('Are you sure you want to cancel this order? This cannot be undone.')) return;

                fetch(siteUrl + '/api/orders/cancel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'order_id=' + encodeURIComponent(orderId)
                        + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to cancel order');
                    }
                })
                .catch(function (err) {
                    console.error('Error:', err);
                    alert('An error occurred. Please try again.');
                });
            });
        });

        // =========================================
        // 3. Nigerian carrier picker — toggle "Other" input
        // =========================================
        const carrierSelect = document.getElementById('tracking_carrier');
        const otherWrap     = document.getElementById('otherCarrierWrap');
        const otherInput    = document.getElementById('other_carrier');

        if (carrierSelect && otherWrap) {
            carrierSelect.addEventListener('change', function () {
                const isOther = this.value === 'Other';
                otherWrap.classList.toggle('d-none', !isOther);
                if (otherInput) otherInput.required = isOther;
            });
        }

        // =========================================
        // 4. Shipping form submission
        // =========================================
        const shippingForm = document.getElementById('shippingForm');
        if (shippingForm) {
            shippingForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);

                // If "Other" was selected, replace with free-text value
                if (formData.get('tracking_carrier') === 'Other') {
                    const other = (formData.get('other_carrier') || '').toString().trim();
                    if (!other) {
                        alert('Please specify the carrier name.');
                        return;
                    }
                    formData.set('tracking_carrier', other);
                }

                fetch(siteUrl + '/api/orders/shipping.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to update shipping');
                    }
                })
                .catch(function (err) {
                    console.error('Error:', err);
                    alert('An error occurred. Please try again.');
                });
            });
        }

        // =========================================
        // 5. Message form
        // =========================================
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);

                fetch(siteUrl + '/api/messages/send.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to send message');
                    }
                })
                .catch(function (err) {
                    console.error('Error:', err);
                    alert('An error occurred. Please try again.');
                });
            });
        }

        // =========================================
        // 6. Scroll to bottom of messages
        // =========================================
        const messagesContainer = document.querySelector('.messages-container');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    });
})();