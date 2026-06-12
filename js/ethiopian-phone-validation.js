/**
 * Ethiopian Phone Number Validation Library
 * Supports formats: 09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, +2517xxxxxxxx
 */

// Ethiopian phone number validation function
function validateEthiopianPhone(phone) {
    const cleanPhone = phone.replace(/[\s\-\(\)]/g, '');
    return /^(\+251[79]|09|07)\d{8}$/.test(cleanPhone);
}

// Format Ethiopian phone number as user types
function formatEthiopianPhone(input) {
    let value = input.value.replace(/[^\d\+]/g, '');
    
    // Handle different Ethiopian formats
    if (value.startsWith('+251')) {
        // International format: +2519xxxxxxxx or +2517xxxxxxxx
        if (value.length > 13) value = value.substring(0, 13);
        // Ensure it's +2519 or +2517
        if (value.length >= 5 && !value.startsWith('+2519') && !value.startsWith('+2517')) {
            value = value.substring(0, 4); // Keep only +251
        }
    } else if (value.startsWith('09') || value.startsWith('07')) {
        // Local format: 09xxxxxxxx or 07xxxxxxxx
        if (value.length > 10) value = value.substring(0, 10);
    } else if (value.startsWith('9') || value.startsWith('7')) {
        // Auto-add 0 prefix for local numbers
        value = '0' + value;
        if (value.length > 10) value = value.substring(0, 10);
    } else if (value.startsWith('251')) {
        // Auto-add + for international format
        value = '+' + value;
        if (value.length > 13) value = value.substring(0, 13);
    }
    
    input.value = value;
    
    // Visual validation feedback
    if (value.length >= 10) {
        if (validateEthiopianPhone(value)) {
            input.style.borderColor = '#4caf50';
            input.style.boxShadow = '0 0 5px rgba(76,175,80,0.3)';
            removePhoneError(input);
        } else {
            input.style.borderColor = '#f44336';
            input.style.boxShadow = '0 0 5px rgba(244,67,54,0.3)';
        }
    } else {
        input.style.borderColor = '#ddd';
        input.style.boxShadow = 'none';
        removePhoneError(input);
    }
}

// Show phone error message
function showPhoneError(input, message = 'Invalid Ethiopian phone number format') {
    removePhoneError(input);
    const errorMsg = document.createElement('small');
    errorMsg.className = 'phone-error';
    errorMsg.style.color = '#f44336';
    errorMsg.style.fontSize = '0.8rem';
    errorMsg.style.display = 'block';
    errorMsg.style.marginTop = '5px';
    errorMsg.textContent = message;
    input.parentNode.appendChild(errorMsg);
}

// Remove phone error message
function removePhoneError(input) {
    const errorMsg = input.parentNode.querySelector('.phone-error');
    if (errorMsg) errorMsg.remove();
}

// Initialize Ethiopian phone validation for all tel inputs
function initEthiopianPhoneValidation() {
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    
    phoneInputs.forEach(input => {
        // Add placeholder if not set
        if (!input.placeholder) {
            input.placeholder = '09xxxxxxxx or +2519xxxxxxxx';
        }
        
        // Format as user types
        input.addEventListener('input', function() {
            formatEthiopianPhone(this);
        });
        
        // Validate on blur
        input.addEventListener('blur', function() {
            if (this.value && !validateEthiopianPhone(this.value)) {
                this.style.borderColor = '#f44336';
                showPhoneError(this, 'Please enter a valid Ethiopian phone number (09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, or +2517xxxxxxxx)');
            } else {
                this.style.borderColor = '#ddd';
                removePhoneError(this);
            }
        });
        
        // Clear error on focus
        input.addEventListener('focus', function() {
            removePhoneError(this);
        });
    });
}

// Form submission validation
function validatePhoneOnSubmit(form) {
    const phoneInputs = form.querySelectorAll('input[type="tel"]');
    let hasPhoneError = false;
    
    phoneInputs.forEach(input => {
        if (input.value && !validateEthiopianPhone(input.value)) {
            input.style.borderColor = '#f44336';
            showPhoneError(input);
            hasPhoneError = true;
        }
    });
    
    return !hasPhoneError;
}

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initEthiopianPhoneValidation();
    
    // Add form validation to all forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validatePhoneOnSubmit(this)) {
                e.preventDefault();
                alert('Please enter valid Ethiopian phone numbers in the correct format.');
            }
        });
    });
});

// Export functions for manual use
window.EthiopianPhoneValidator = {
    validate: validateEthiopianPhone,
    format: formatEthiopianPhone,
    init: initEthiopianPhoneValidation,
    validateForm: validatePhoneOnSubmit
};