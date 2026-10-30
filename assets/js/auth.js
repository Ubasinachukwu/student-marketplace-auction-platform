// marketplace/assets/js/auth.js

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // =============================================
    // PASSWORD STRENGTH METER (Registration)
    // =============================================
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('passwordStrengthText');

    if (passwordInput && strengthBar && strengthText) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let score = 0;

            // Length check
            if (password.length >= 8) score += 1;
            if (password.length >= 12) score += 1;

            // Complexity checks
            if (/[a-z]/.test(password)) score += 1;
            if (/[A-Z]/.test(password)) score += 1;
            if (/[0-9]/.test(password)) score += 1;
            if (/[^a-zA-Z0-9]/.test(password)) score += 1;

            // Calculate percentage
            const percentage = Math.min((score / 6) * 100, 100);
            
            // Update bar
            strengthBar.style.width = percentage + '%';
            
            // Update colors and text
            let color, label;
            if (percentage < 20) {
                color = '#dc3545';
                label = 'Very Weak';
            } else if (percentage < 40) {
                color = '#ffc107';
                label = 'Weak';
            } else if (percentage < 60) {
                color = '#fd7e14';
                label = 'Fair';
            } else if (percentage < 80) {
                color = '#0d6efd';
                label = 'Good';
            } else {
                color = '#198754';
                label = 'Strong';
            }

            strengthBar.style.background = color;
            strengthText.textContent = 'Password strength: ' + label;
            strengthText.style.color = color;
        });
    }

    // =============================================
    // PASSWORD VISIBILITY TOGGLE (IMPROVED)
    // =============================================
    // Function to toggle password visibility
    function togglePasswordVisibility(input, icon) {
        if (!input || !icon) return;
        
        if (input.type === 'password') {
            input.type = 'text';
            // Try Bootstrap Icons first, fallback to Font Awesome
            if (icon.classList.contains('bi')) {
                icon.className = 'bi bi-eye-slash';
            } else {
                icon.className = 'fas fa-eye-slash';
            }
            input.setAttribute('aria-label', 'Hide password');
        } else {
            input.type = 'password';
            // Try Bootstrap Icons first, fallback to Font Awesome
            if (icon.classList.contains('bi')) {
                icon.className = 'bi bi-eye';
            } else {
                icon.className = 'fas fa-eye';
            }
            input.setAttribute('aria-label', 'Show password');
        }
        input.focus();
    }

    // Find all toggle password buttons
    const toggleButtons = document.querySelectorAll('#togglePassword, .toggle-password');
    
    toggleButtons.forEach(btn => {
        // Remove any existing listeners to prevent duplicates
        btn.removeEventListener('click', handleToggleClick);
        btn.addEventListener('click', handleToggleClick);
    });

    function handleToggleClick(e) {
        e.preventDefault();
        const btn = this;
        const inputGroup = btn.closest('.input-group');
        
        if (!inputGroup) {
            // If no input-group, try finding the password input by ID
            const passwordInput = document.getElementById('password');
            const icon = btn.querySelector('i');
            if (passwordInput && icon) {
                togglePasswordVisibility(passwordInput, icon);
            }
            return;
        }
        
        // Find password input (could be type password or text)
        const passwordInput = inputGroup.querySelector('input[type="password"], input[type="text"]');
        const icon = btn.querySelector('i');
        
        if (passwordInput && icon) {
            togglePasswordVisibility(passwordInput, icon);
        }
    }

    // Also support keyboard shortcut (Ctrl+Shift+P)
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'p' || e.key === 'P')) {
            e.preventDefault();
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('#togglePassword, .toggle-password');
            if (passwordInput && toggleBtn) {
                const icon = toggleBtn.querySelector('i');
                if (icon) {
                    togglePasswordVisibility(passwordInput, icon);
                }
            }
        }
    });

    // =============================================
    // PASSWORD CONFIRMATION VALIDATION
    // =============================================
    const confirmPassword = document.getElementById('password_confirm');
    const passwordField = document.getElementById('password');

    if (confirmPassword && passwordField) {
        confirmPassword.addEventListener('input', function() {
            if (this.value !== passwordField.value) {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });

        passwordField.addEventListener('input', function() {
            if (confirmPassword.value && confirmPassword.value !== this.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
                confirmPassword.classList.add('is-invalid');
            } else if (confirmPassword.value) {
                confirmPassword.setCustomValidity('');
                confirmPassword.classList.remove('is-invalid');
            }
        });
    }

    // =============================================
    // FORM SUBMISSION VALIDATION
    // =============================================
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const termsCheckbox = document.getElementById('terms');
            if (termsCheckbox && !termsCheckbox.checked) {
                e.preventDefault();
                termsCheckbox.classList.add('is-invalid');
                alert('Please agree to the Terms of Service and Privacy Policy.');
                return false;
            }
        });
    }

    // =============================================
    // USER TYPE CARDS SELECTION
    // =============================================
    const userTypeCards = document.querySelectorAll('.form-check.card');
    userTypeCards.forEach(card => {
        card.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                
                // Remove border from all cards
                userTypeCards.forEach(c => c.classList.remove('border-primary'));
                // Add border to selected
                this.classList.add('border-primary');
            }
        });
    });

    // =============================================
    // DEMO LOGIN AUTO-FILL
    // =============================================
    const demoButtons = document.querySelectorAll('.demo-login');
    demoButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const email = this.dataset.email;
            const password = this.dataset.password;
            
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            if (emailInput) emailInput.value = email;
            if (passwordInput) passwordInput.value = password;
            
            // Auto-submit the form
            const form = this.closest('form') || document.getElementById('loginForm');
            if (form) {
                setTimeout(() => form.submit(), 300);
            }
        });
    });
});