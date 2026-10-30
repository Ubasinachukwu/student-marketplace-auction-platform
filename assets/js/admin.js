// marketplace/assets/js/admin.js

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // =============================================
    // USER MODAL ACTIONS
    // =============================================
    const userActionBtns = document.querySelectorAll('.user-action-btn');
    userActionBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const action = this.dataset.action;
            const username = this.dataset.username;

            if (action === 'suspend') {
                if (confirm(`Are you sure you want to suspend "${username}"?`)) {
                    // AJAX call to suspend user
                    fetch(`/api/admin/users.php?action=suspend&id=${userId}`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to suspend user: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('An error occurred. Please try again.');
                    });
                }
            } else if (action === 'verify') {
                if (confirm(`Verify "${username}"?`)) {
                    fetch(`/api/admin/users.php?action=verify&id=${userId}`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to verify user: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('An error occurred. Please try again.');
                    });
                }
            } else if (action === 'delete') {
                if (confirm(`Are you sure you want to permanently delete "${username}"? This cannot be undone!`)) {
                    fetch(`/api/admin/users.php?action=delete&id=${userId}`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to delete user: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('An error occurred. Please try again.');
                    });
                }
            }
        });
    });

    // =============================================
    // LISTING MODERATION
    // =============================================
    const moderationBtns = document.querySelectorAll('.moderation-btn');
    moderationBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const listingId = this.dataset.listingId;
            const action = this.dataset.action;
            const title = this.dataset.title;

            if (action === 'approve') {
                if (confirm(`Approve listing "${title}"?`)) {
                    fetch(`/api/admin/listings.php?action=approve&id=${listingId}`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to approve listing: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('An error occurred. Please try again.');
                    });
                }
            } else if (action === 'reject') {
                const reason = prompt('Please provide a reason for rejecting this listing:');
                if (reason !== null) {
                    fetch(`/api/admin/listings.php?action=reject&id=${listingId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ reason: reason })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to reject listing: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('An error occurred. Please try again.');
                    });
                }
            } else if (action === 'delete') {
                if (confirm(`Are you sure you want to delete "${title}"? This cannot be undone!`)) {
                    fetch(`/api/admin/listings.php?action=delete&id=${listingId}`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to delete listing: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('An error occurred. Please try again.');
                    });
                }
            }
        });
    });

    // =============================================
    // DISPUTE RESOLUTION
    // =============================================
    const disputeResolveBtns = document.querySelectorAll('.resolve-dispute-btn');
    disputeResolveBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const disputeId = this.dataset.disputeId;
            const modal = document.getElementById('resolveModal');
            if (modal) {
                modal.querySelector('#disputeId').value = disputeId;
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            }
        });
    });

    // =============================================
    // FEES CONFIGURATION (Auto-save)
    // =============================================
    const feeInputs = document.querySelectorAll('.fee-input');
    feeInputs.forEach(input => {
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const form = this.closest('form');
                if (form) {
                    const formData = new FormData(form);
                    formData.append('auto_save', '1');
                    
                    fetch('/api/admin/fees.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success indicator
                            const indicator = form.querySelector('.save-indicator');
                            if (indicator) {
                                indicator.innerHTML = '<i class="bi bi-check-circle text-success"></i> Saved';
                                setTimeout(() => {
                                    indicator.innerHTML = '';
                                }, 3000);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Save error:', error);
                    });
                }
            }, 1000);
        });
    });
});