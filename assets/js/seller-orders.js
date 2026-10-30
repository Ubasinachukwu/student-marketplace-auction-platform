// marketplace/assets/js/seller-orders.js
// Seller orders list page — confirm order + shipping modal

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const cfg = window.SellerOrdersConfig || {};
        const siteUrl   = cfg.siteUrl   || '';
        const csrfToken = cfg.csrfToken || '';

        const shippingModalEl = document.getElementById('shippingModal');
        const shippingModal   = shippingModalEl && window.bootstrap
            ? new bootstrap.Modal(shippingModalEl)
            : null;

        const shippingOrderIdInput = document.getElementById('shippingOrderId');
        const carrierSelect        = document.getElementById('tracking_carrier');
        const otherWrap            = document.getElementById('otherCarrierWrap');
        const otherInput           = document.getElementById('other_carrier');
        const shippingForm         = document.getElementById('shippingForm');

        // ---------------------------------------------
        // Confirm Order (pending → confirmed)
        // ---------------------------------------------
        document.querySelectorAll('.js-confirm-order').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const orderId = this.dataset.orderId;

                if (!confirm('Confirm this order?')) return;

                fetch(siteUrl + '/api/orders/update-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'order_id=' + encodeURIComponent(orderId)
                        + '&status=confirmed'
                        + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to confirm order');
                    }
                })
                .catch(function (err) {
                    console.error('Error:', err);
                    alert('An error occurred. Please try again.');
                });
            });
        });

        // ---------------------------------------------
        // Open shipping modal (confirmed → shipped)
        // ---------------------------------------------
        document.querySelectorAll('.js-open-shipping').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const orderId = this.dataset.orderId;
                if (shippingOrderIdInput) {
                    shippingOrderIdInput.value = orderId;
                }
                if (shippingModal) {
                    shippingModal.show();
                }
            });
        });

        // ---------------------------------------------
        // Carrier dropdown — toggle "Other" text input
        // ---------------------------------------------
        if (carrierSelect && otherWrap) {
            carrierSelect.addEventListener('change', function () {
                const isOther = this.value === 'Other';
                otherWrap.classList.toggle('d-none', !isOther);
                if (otherInput) otherInput.required = isOther;
            });
        }

        // ---------------------------------------------
        // Shipping form submit
        // ---------------------------------------------
        if (shippingForm) {
            shippingForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);

                // Resolve "Other" to free-text value
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
    });
})();