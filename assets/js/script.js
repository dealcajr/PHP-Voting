// SSLG Voting System JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        if (!alert.classList.contains('alert-permanent')) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        }
    });

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // Password strength indicator
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            checkPasswordStrength(this);
        });
    });

    // Form validation
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // Auto-generate voter ID
    const generateIdButtons = document.querySelectorAll('[data-generate-id]');
    generateIdButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const targetInput = document.getElementById(this.getAttribute('data-generate-id'));
            if (targetInput) {
                targetInput.value = generateVoterId();
            }
        });
    });

    // Real-time search/filter
    const searchInputs = document.querySelectorAll('[data-search]');
    searchInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            const target = this.getAttribute('data-search');
            const rows = document.querySelectorAll(target + ' tbody tr');
            const searchTerm = this.value.toLowerCase();

            rows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    });

    // Session timeout warning
    setupSessionTimeout();

    // Voting progress animation
    animateProgressBars();

    // Initialize charts if Chart.js is available
    if (typeof Chart !== 'undefined') {
        initializeCharts();
    }
});

// Password strength checker
function checkPasswordStrength(input) {
    const password = input.value;
    const strengthIndicator = document.getElementById(input.id + '_strength');

    if (!strengthIndicator) return;

    let strength = 0;
    let feedback = [];

    if (password.length >= 8) strength++;
    else feedback.push('At least 8 characters');

    if (/[a-z]/.test(password)) strength++;
    else feedback.push('Lowercase letter');

    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('Uppercase letter');

    if (/[0-9]/.test(password)) strength++;
    else feedback.push('Number');

    if (/[^A-Za-z0-9]/.test(password)) strength++;
    else feedback.push('Special character');

    const colors = ['#dc3545', '#ffc107', '#fd7e14', '#20c997', '#28a745'];
    const labels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];

    strengthIndicator.style.width = (strength * 20) + '%';
    strengthIndicator.style.backgroundColor = colors[strength - 1] || colors[0];
    strengthIndicator.textContent = labels[strength - 1] || labels[0];

    const feedbackElement = document.getElementById(input.id + '_feedback');
    if (feedbackElement) {
        feedbackElement.innerHTML = feedback.length > 0 ?
            '<small class="text-muted">Suggestions: ' + feedback.join(', ') + '</small>' : '';
    }
}

// Form validation
function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');

    requiredFields.forEach(function(field) {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });

    // Email validation
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(function(field) {
        if (field.value && !isValidEmail(field.value)) {
            field.classList.add('is-invalid');
            isValid = false;
        }
    });

    // Password confirmation
    const passwordFields = form.querySelectorAll('input[data-confirm]');
    passwordFields.forEach(function(field) {
        const confirmField = document.getElementById(field.getAttribute('data-confirm'));
        if (confirmField && field.value !== confirmField.value) {
            confirmField.classList.add('is-invalid');
            isValid = false;
        }
    });

    return isValid;
}

// Email validation
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Generate voter ID
function generateVoterId() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = 'VOTER-';
    for (let i = 0; i < 8; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
}

// Session timeout setup
function setupSessionTimeout() {
    let warningShown = false;
    const timeout = 25 * 60 * 1000; // 25 minutes
    const warning = 5 * 60 * 1000; // 5 minutes before timeout

    setInterval(function() {
        // This would be handled by server-side session management
        // Here we just show a client-side warning
        if (!warningShown && Date.now() - (window.lastActivity || Date.now()) > warning) {
            showTimeoutWarning();
            warningShown = true;
        }
    }, 60000); // Check every minute

    // Reset activity timer
    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, function() {
            window.lastActivity = Date.now();
            warningShown = false;
        });
    });
}

// Show timeout warning
function showTimeoutWarning() {
    const alert = document.createElement('div');
    alert.className = 'alert alert-warning alert-dismissible fade show position-fixed';
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alert.innerHTML = `
        <strong>Session Timeout Warning</strong><br>
        Your session will expire in 5 minutes due to inactivity.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);

    setTimeout(function() {
        alert.remove();
    }, 10000);
}

// Animate progress bars
function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(function(bar) {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(function() {
            bar.style.width = width;
        }, 500);
    });
}

// Initialize charts
function initializeCharts() {
    // This would be called for results page charts
    // Implementation depends on specific chart requirements
}

// Utility functions
function showLoading(button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="loading"></span> Loading...';
    button.disabled = true;

    return function() {
        button.innerHTML = originalText;
        button.disabled = false;
    };
}

function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Copied to clipboard!', 'success');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Copied to clipboard!', 'success');
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    const container = document.querySelector('.toast-container') || createToastContainer();
    container.appendChild(toast);

    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// Export functions for global use
window.SSLGVoting = {
    showLoading: showLoading,
    copyToClipboard: copyToClipboard,
    showToast: showToast,
    generateVoterId: generateVoterId
};
