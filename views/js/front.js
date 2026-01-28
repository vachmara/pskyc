/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * Frontend JavaScript for KYC Secure Upload module
 */

(function() {
    'use strict';
    
    /**
     * KYC Checkout Validation
     * Prevents checkout submission if KYC is required but not approved
     */
    function initKycCheckoutValidation() {
        // Check if KYC warning is present
        var kycWarning = document.querySelector('.pskyc-checkout-warning[data-kyc-required="true"]');
        
        if (!kycWarning) {
            return; // No KYC requirement, nothing to do
        }
        
        var kycUrl = kycWarning.getAttribute('data-kyc-url');
        
        // Find checkout/order confirmation buttons
        var checkoutButtons = document.querySelectorAll([
            'button[name="confirmDeliveryOption"]',
            'button[type="submit"][name*="confirm"]',
            'button[data-action="confirm-order"]',
            '.checkout-step button[type="submit"]',
            '#payment-confirmation button[type="submit"]'
        ].join(','));
        
        // Add click handler to all potential checkout buttons
        checkoutButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                // Check if KYC is still required
                var warningStillPresent = document.querySelector('.pskyc-checkout-warning[data-kyc-required="true"]');
                
                if (warningStillPresent && kycUrl) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Show alert
                    if (window.confirm('Identity verification is required before completing your order. Would you like to complete the verification now?')) {
                        window.location.href = kycUrl;
                    }
                    
                    return false;
                }
            });
        });
        
        // For Prestahero and other AJAX checkouts
        document.addEventListener('submit', function(e) {
            var form = e.target;
            
            // Check if this is a checkout form
            if (form.id && (form.id.includes('checkout') || form.id.includes('order'))) {
                var warningStillPresent = document.querySelector('.pskyc-checkout-warning[data-kyc-required="true"]');
                
                if (warningStillPresent && kycUrl) {
                    e.preventDefault();
                    
                    if (window.confirm('Identity verification is required before completing your order. Would you like to complete the verification now?')) {
                        window.location.href = kycUrl;
                    }
                    
                    return false;
                }
            }
        }, true);
    }
    
    /**
     * Initialize KYC status checking via AJAX
     * For dynamic checkout pages that update via AJAX
     */
    function initKycStatusCheck() {
        // Check if prestashop object is available
        if (typeof prestashop === 'undefined' || !prestashop.urls || !prestashop.urls.base_url) {
            return;
        }
        
        // Only run on checkout pages
        var isCheckoutPage = document.body.id === 'checkout' || 
                            document.querySelector('[data-module="checkout"]') !== null ||
                            window.location.pathname.includes('order') ||
                            window.location.pathname.includes('checkout');
        
        if (!isCheckoutPage) {
            return;
        }
        
        // Function to check KYC status
        function checkKycStatus() {
            // This is a placeholder - actual implementation would require
            // an AJAX endpoint in the module
            console.log('KYC status check would be performed here');
        }
        
        // Check on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', checkKycStatus);
        } else {
            checkKycStatus();
        }
    }
    
    /**
     * Dispatch custom event for KYC status changes
     * Other modules can listen to this event
     */
    function dispatchKycEvent(eventName, data) {
        var event = new CustomEvent('pskyc:' + eventName, {
            detail: data,
            bubbles: true,
            cancelable: true
        });
        
        document.dispatchEvent(event);
    }
    
    /**
     * Initialize all KYC frontend functionality
     */
    function init() {
        initKycCheckoutValidation();
        initKycStatusCheck();
        
        // Dispatch ready event
        dispatchKycEvent('ready', {
            version: '1.1.2'
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Expose utility functions for other scripts
    window.pskyc = {
        dispatchEvent: dispatchKycEvent,
        version: '1.1.2'
    };
})();
