// marketplace/assets/js/dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // =============================================
    // AUTO-REFRESH AUCTION TIMERS
    // =============================================
    const auctionTimers = document.querySelectorAll('.auction-timer');
    auctionTimers.forEach(timer => {
        const endTime = new Date(timer.dataset.end).getTime();
        if (!endTime) return;

        const interval = setInterval(function() {
            const now = new Date().getTime();
            const distance = endTime - now;

            if (distance <= 0) {
                clearInterval(interval);
                timer.innerHTML = '<span class="badge bg-danger">Ended</span>';
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

            timer.innerHTML = display;
        }, 1000);
    });

    // =============================================
    // MOBILE SIDEBAR TOGGLE (Admin)
    // =============================================
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // =============================================
    // DATA TABLE SEARCH
    // =============================================
    const searchInputs = document.querySelectorAll('.table-search');
    searchInputs.forEach(input => {
        input.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = this.closest('.table-responsive').querySelector('table');
            if (!table) return;

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    });

    // =============================================
    // BULK SELECT ACTIONS
    // =============================================
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.row-select');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }

    // =============================================
    // CONFIRM BULK ACTIONS
    // =============================================
    const bulkActions = document.querySelectorAll('.bulk-action');
    bulkActions.forEach(btn => {
        btn.addEventListener('click', function(e) {
            const selected = document.querySelectorAll('.row-select:checked');
            if (selected.length === 0) {
                e.preventDefault();
                alert('Please select at least one item.');
                return;
            }

            const action = this.dataset.action || 'perform';
            if (!confirm(`Are you sure you want to ${action} ${selected.length} item(s)?`)) {
                e.preventDefault();
            }
        });
    });
});