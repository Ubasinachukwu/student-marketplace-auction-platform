// marketplace/assets/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // =============================================
    // AUTO-DISMISS ALERTS
    // =============================================
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            }
        }, 5000);
    });
    
    // =============================================
    // IMAGE PREVIEW FOR FORMS
    // =============================================
    const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const previewId = this.dataset.preview;
            const preview = document.getElementById(previewId);
            if (!preview) return;
            
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    preview.src = event.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    // =============================================
    // CONFIRM DELETE
    // =============================================
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', function(e) {
            const message = this.dataset.confirm || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // =============================================
    // PRICE FORMATTING
    // =============================================
    const priceInputs = document.querySelectorAll('.price-input');
    priceInputs.forEach(input => {
        input.addEventListener('blur', function() {
            let value = parseFloat(this.value);
            if (!isNaN(value)) {
                this.value = value.toFixed(2);
            }
        });
    });
    
    // =============================================
    // TOOLTIPS
    // =============================================
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // =============================================
    // POPOVERS
    // =============================================
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // =============================================
    // DROPZONE (File Upload)
    // =============================================
    const dropZones = document.querySelectorAll('.drop-zone');
    dropZones.forEach(zone => {
        const input = zone.querySelector('input[type="file"]');
        if (!input) return;
        
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        zone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });
        
        zone.addEventListener('click', function() {
            input.click();
        });
    });
    
    // =============================================
    // IMAGE PREVIEW FOR MULTIPLE FILES
    // =============================================
    const multiFileInputs = document.querySelectorAll('input[type="file"][multiple][data-preview-container]');
    multiFileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const container = document.getElementById(this.dataset.previewContainer);
            if (!container) return;
            
            container.innerHTML = '';
            const files = this.files;
            
            for (let i = 0; i < files.length && i < 10; i++) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const div = document.createElement('div');
                    div.className = 'col-3';
                    div.innerHTML = `
                        <div class="position-relative">
                            <img src="${event.target.result}" class="img-fluid rounded" style="height: 100px; object-fit: cover;">
                            <span class="badge bg-primary position-absolute top-0 end-0 m-1">${i + 1}</span>
                        </div>
                    `;
                    container.appendChild(div);
                };
                reader.readAsDataURL(files[i]);
            }
        });
    });
    
    // =============================================
    // SALE TYPE TOGGLE
    // =============================================
    const saleTypeRadios = document.querySelectorAll('input[name="sale_type"]');
    saleTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const form = this.closest('form');
            const fixedSection = form.querySelector('.sale-type-fixed');
            const auctionSection = form.querySelector('.sale-type-auction');
            const hybridSection = form.querySelector('.sale-type-hybrid');
            
            if (fixedSection) fixedSection.style.display = 'none';
            if (auctionSection) auctionSection.style.display = 'none';
            if (hybridSection) hybridSection.style.display = 'none';
            
            if (this.value === 'fixed' && fixedSection) {
                fixedSection.style.display = 'block';
            } else if (this.value === 'auction' && auctionSection) {
                auctionSection.style.display = 'block';
            } else if (this.value === 'hybrid' && hybridSection) {
                hybridSection.style.display = 'block';
                if (fixedSection) fixedSection.style.display = 'block';
                if (auctionSection) auctionSection.style.display = 'block';
            }
        });
    });
    
    // =============================================
    // AJAX LOADING SPINNER
    // =============================================
    window.showLoading = function() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) spinner.classList.add('show');
    };
    
    window.hideLoading = function() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) spinner.classList.remove('show');
    };
    
    // =============================================
    // AJAX ERROR HANDLING
    // =============================================
    window.handleAjaxError = function(error) {
        console.error('AJAX Error:', error);
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show';
        alert.innerHTML = `
            <strong>Error!</strong> Something went wrong. Please try again.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.container')?.prepend(alert);
    };
});

// =============================================
// UTILITY FUNCTIONS (Global)
// =============================================

// Format price
function formatPrice(amount, currency = '₦') {
    return currency + parseFloat(amount).toFixed(2);
}

// Get CSRF token from meta tag
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// AJAX GET request
function ajaxGet(url, data = {}) {
    const params = new URLSearchParams(data);
    const fullUrl = params.toString() ? url + '?' + params.toString() : url;
    
    return fetch(fullUrl, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': getCsrfToken()
        }
    })
    .then(response => response.json())
    .catch(error => {
        console.error('AJAX GET Error:', error);
        throw error;
    });
}

// AJAX POST request
function ajaxPost(url, data = {}) {
    const formData = new FormData();
    for (const key in data) {
        if (data.hasOwnProperty(key)) {
            formData.append(key, data[key]);
        }
    }
    formData.append('csrf_token', getCsrfToken());
    
    return fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .catch(error => {
        console.error('AJAX POST Error:', error);
        throw error;
    });
}

// =============================================
// COUNTDOWN TIMER (For Auctions)
// =============================================
function startCountdown(endTime, elementId, onEnd) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const interval = setInterval(function() {
        const now = new Date().getTime();
        const distance = endTime - now;
        
        if (distance <= 0) {
            clearInterval(interval);
            element.innerHTML = 'Auction Ended';
            if (onEnd) onEnd();
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
        
        element.innerHTML = display;
    }, 1000);
}