# Integration Guide for Third-Party Checkout Modules

## Table of Contents
- [Overview](#overview)
- [PrestaShop Standard Hooks Used](#prestashop-standard-hooks-used)
- [Prestahero One Page Checkout Integration](#prestahero-one-page-checkout-integration)
- [Custom Validation Integration](#custom-validation-integration)
- [Override Classes](#override-classes)
- [Extension Points](#extension-points)
- [Troubleshooting](#troubleshooting)

---

## Overview

The **KYC Secure Upload** module is designed to work seamlessly with PrestaShop's standard checkout process. However, many merchants use third-party checkout modules like **Prestahero One Page Checkout & Social Login**, which replace or extend the standard checkout workflow.

This guide provides comprehensive documentation on integrating the KYC module with third-party checkout modules while maintaining compatibility and avoiding direct core modifications.

---

## PrestaShop Standard Hooks Used

The KYC Secure Upload module leverages the following PrestaShop hooks for checkout integration:

### Primary Hooks

| Hook Name | Type | Trigger Point | Purpose | Parameters |
|-----------|------|---------------|---------|------------|
| `actionCheckoutRender` | Action | Before checkout page renders | Inject KYC step into checkout process | `['checkoutProcess']` |
| `actionValidateOrder` | Action | Before order confirmation | Block order if KYC not approved | `['cart', 'order', 'customer']` |
| `displayBeforeCarrier` | Display | Before carrier selection | Show KYC warning/requirements | `[]` |
| `displayCustomerAccount` | Display | Customer account page | Display KYC verification link | `[]` |
| `actionFrontControllerSetMedia` | Action | Page asset loading | Load KYC-related CSS/JS | `[]` |

### Supporting Hooks

| Hook Name | Purpose |
|-----------|---------|
| `displayAdminCustomers` | Display KYC status in admin customer view |
| `displayAdminOrder` | Display KYC status in admin order view |
| `actionExportGDPRData` | Export customer KYC data (GDPR) |
| `actionDeleteGDPRCustomer` | Delete customer KYC data (GDPR) |

---

## Prestahero One Page Checkout Integration

### Understanding Prestahero's Workflow

Prestahero One Page Checkout **replaces** the standard PrestaShop checkout with its own single-page flow. Key differences:

1. **Custom Hook Flow**: Uses specialized hooks that differ from standard PrestaShop hooks
2. **AJAX-Based**: Heavy use of AJAX for real-time validation
3. **Extension Points**: Provides specific extension points for custom validation
4. **No Standard Steps**: Does not use PrestaShop's `AbstractCheckoutStepCore` system

### Integration Strategy

There are **three recommended approaches** for integrating KYC verification with Prestahero:

#### Approach 1: Pre-Order Validation Hook (Recommended)

Use the `actionValidateOrder` hook to block order creation if KYC is not approved.

**Pros:**
- ✅ Works with any checkout module
- ✅ No override classes needed
- ✅ Update-safe for both modules

**Cons:**
- ⚠️ Customer reaches final step before seeing error
- ⚠️ May confuse customers

**Implementation:**
The module already implements this in `pskyc.php`:

```php
public function hookActionValidateOrder($params)
{
    $cart = $params['cart'];
    $customer = $params['customer'];
    
    // Check if KYC is required for cart products
    if (!$this->isKycRequiredForCart($cart)) {
        return true;
    }
    
    // Get customer's verification status
    $verificationService = $this->get('PrestaShop\\Module\\Pskyc\\Service\\VerificationService');
    $verification = $verificationService->getMostRecentVerification($customer->id);
    
    // Block order if not approved
    if (!$verification || $verification['status'] !== 'approved') {
        // Redirect to KYC verification page
        $kycUrl = $this->context->link->getModuleLink('pskyc', 'verify', [], true);
        Tools::redirect($kycUrl . '?error=kyc_required');
        
        return false;
    }
    
    return true;
}
```

#### Approach 2: Prestahero-Specific Override (Advanced)

Create a custom override class that extends Prestahero's checkout controller.

**Pros:**
- ✅ Early validation in checkout flow
- ✅ Better user experience
- ✅ Can customize error messages

**Cons:**
- ⚠️ Requires Prestahero-specific code
- ⚠️ May need updates when Prestahero updates
- ⚠️ More complex implementation

**Implementation:**
See the [Override Classes](#override-classes) section below for detailed code examples.

#### Approach 3: Frontend JavaScript Validation

Inject JavaScript that validates KYC status before order submission.

**Pros:**
- ✅ Immediate feedback to customer
- ✅ No server-side override needed
- ✅ Works with AJAX checkout

**Cons:**
- ⚠️ Can be bypassed (always use server-side validation too)
- ⚠️ Requires knowledge of Prestahero's DOM structure

**Implementation:**
See the [Custom Validation Integration](#custom-validation-integration) section.

---

## Custom Validation Integration

### Server-Side Validation

The module provides a validation service that can be used in any context:

```php
<?php
// Get the verification service
$verificationService = Module::getInstanceByName('pskyc')
    ->get('PrestaShop\\Module\\Pskyc\\Service\\VerificationService');

// Check if customer is verified
$customerId = Context::getContext()->customer->id;
$verification = $verificationService->getMostRecentVerification($customerId);

$isVerified = $verification && $verification['status'] === 'approved';

if (!$isVerified) {
    // Handle unverified customer
    // Redirect to KYC page or show error
}
```

### AJAX Validation Endpoint

For AJAX-based checkouts, you can call the verification check via AJAX:

```javascript
// Example AJAX validation call
$.ajax({
    url: prestashop.urls.base_url + 'module/pskyc/verify',
    method: 'POST',
    data: {
        action: 'check_status',
        ajax: true
    },
    success: function(response) {
        if (response.status !== 'approved') {
            // Show warning or block checkout
            alert('KYC verification required before checkout');
        }
    }
});
```

### Frontend JavaScript Hook

Add this code to your theme or module to validate KYC before order placement:

```javascript
// Add to your checkout JavaScript
(function() {
    'use strict';
    
    // Hook into form submission
    var checkoutForm = document.querySelector('[data-module="prestahero_onepagecheckout"]');
    if (!checkoutForm) {
        // Fallback for standard checkout
        checkoutForm = document.querySelector('form[id*="checkout"]');
    }
    
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            // Check if KYC validation is needed
            var kycRequired = document.querySelector('[data-kyc-required="true"]');
            if (kycRequired) {
                e.preventDefault();
                
                // Show message and redirect
                alert('Please complete KYC verification before placing your order.');
                window.location.href = kycRequired.getAttribute('data-kyc-url');
                
                return false;
            }
        });
    }
})();
```

---

## Override Classes

### Creating a Safe Override

When creating override classes for compatibility, follow these best practices:

1. **Only override what's necessary** - Don't modify unrelated functionality
2. **Check for method existence** - Ensure compatibility across versions
3. **Document your changes** - Add comments explaining why the override exists
4. **Test thoroughly** - Test with and without the KYC module enabled

### Example: Prestahero Checkout Override

Create this file in your theme or custom module:

**File:** `override/modules/prestahero_onepagecheckout/controllers/front/order.php`

```php
<?php
/**
 * Override for Prestahero One Page Checkout
 * Adds KYC verification check before order placement
 * 
 * This override is safe to use and does not modify Prestahero core files
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Prestahero_OnepagecheckoutOrderModuleFrontControllerOverride extends Prestahero_OnepagecheckoutOrderModuleFrontController
{
    /**
     * Override init method to add KYC validation
     * 
     * @return void
     */
    public function init()
    {
        // Call parent initialization first
        parent::init();
        
        // Add KYC validation if module is installed and active
        if (!$this->validateKycIfRequired()) {
            // Redirect to KYC verification page
            $pskycModule = Module::getInstanceByName('pskyc');
            if ($pskycModule && $pskycModule->active) {
                $kycUrl = Context::getContext()->link->getModuleLink('pskyc', 'verify', ['error' => 'kyc_required'], true);
                Tools::redirect($kycUrl);
            }
        }
    }
    
    /**
     * Validate KYC requirement for cart products
     * 
     * @return bool True if validation passes or not required, false otherwise
     */
    protected function validateKycIfRequired()
    {
        $context = Context::getContext();
        
        // Check if customer is logged in
        if (!$context->customer->id) {
            return true; // Let checkout handle login
        }
        
        // Check if KYC module is active
        $pskycModule = Module::getInstanceByName('pskyc');
        if (!$pskycModule || !$pskycModule->active) {
            return true; // Module not active, skip validation
        }
        
        // Check if KYC is required for cart products
        $kycRequired = $this->isKycRequiredForCart($context->cart);
        if (!$kycRequired) {
            return true; // KYC not required for these products
        }
        
        // Get customer's verification status
        try {
            $verificationService = $pskycModule->get('PrestaShop\\Module\\Pskyc\\Service\\VerificationService');
            $verification = $verificationService->getMostRecentVerification($context->customer->id);
            
            // Check if approved
            if ($verification && $verification['status'] === 'approved') {
                return true; // Approved, allow checkout
            }
        } catch (Exception $e) {
            // Service not available, allow checkout to prevent blocking
            return true;
        }
        
        // KYC required but not approved
        return false;
    }
    
    /**
     * Check if KYC is required for products in cart
     * 
     * @param Cart $cart
     * @return bool
     */
    protected function isKycRequiredForCart($cart)
    {
        $kycRequiredCategories = json_decode(Configuration::get('PSKYC_KYC_REQUIRED_CATEGORIES') ?: '[]', true);
        
        if (empty($kycRequiredCategories)) {
            return false;
        }
        
        $products = $cart->getProducts();
        $cartCategoryIds = [];
        
        foreach ($products as $product) {
            if (!empty($product['id_product'])) {
                $productCategories = Product::getProductCategories((int)$product['id_product']);
                $cartCategoryIds = array_merge($cartCategoryIds, $productCategories);
            }
        }
        
        $cartCategoryIds = array_unique($cartCategoryIds);
        
        return count(array_intersect($kycRequiredCategories, $cartCategoryIds)) > 0;
    }
}
```

### Installing the Override

1. **Copy the file** to your PrestaShop installation:
   - Path: `override/modules/prestahero_onepagecheckout/controllers/front/order.php`

2. **Clear cache**:
   - Back office → Advanced Parameters → Performance → Clear cache
   - Or delete: `var/cache/prod/` and `var/cache/dev/`

3. **Test the integration**:
   - Add KYC-required products to cart
   - Attempt checkout without KYC approval
   - Verify redirect to KYC verification page

### Alternative: Module-Based Override

If you prefer to keep overrides within the KYC module itself, create a sub-module or use hooks:

**File:** `modules/pskyc/override/prestahero_integration.php`

```php
<?php
/**
 * Prestahero Integration Helper
 * Provides methods to integrate with Prestahero One Page Checkout
 */

namespace PrestaShop\Module\Pskyc\Integration;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PrestaheroIntegration
{
    /**
     * Check if Prestahero module is installed and active
     * 
     * @return bool
     */
    public static function isPrestaheroActive()
    {
        $module = \Module::getInstanceByName('prestahero_onepagecheckout');
        return $module && $module->active;
    }
    
    /**
     * Register Prestahero-specific hooks
     * 
     * @param \Module $module The KYC module instance
     * @return bool
     */
    public static function registerPrestaheroHooks($module)
    {
        if (!self::isPrestaheroActive()) {
            return true; // Not installed, nothing to register
        }
        
        // Register Prestahero-specific hooks
        $hooks = [
            'displayPrestaheroBeforePayment',
            'actionPrestaheroValidateOrder',
            'displayPrestaheroOrderConfirmation',
        ];
        
        foreach ($hooks as $hook) {
            if (!\Hook::getIdByName($hook)) {
                // Hook doesn't exist, skip
                continue;
            }
            
            $module->registerHook($hook);
        }
        
        return true;
    }
    
    /**
     * Get KYC warning HTML for Prestahero checkout
     * 
     * @param int $customerId
     * @param \Cart $cart
     * @return string
     */
    public static function getCheckoutWarning($customerId, $cart)
    {
        $pskycModule = \Module::getInstanceByName('pskyc');
        if (!$pskycModule || !$pskycModule->active) {
            return '';
        }
        
        // Check if KYC required
        // Implementation here...
        
        return ''; // Return HTML warning if needed
    }
}
```

---

## Extension Points

### Available Extension Points for Third-Party Modules

The KYC module provides several extension points that third-party modules can use:

#### 1. Service Layer

All business logic is in services that can be accessed by any module:

```php
// Get verification service
$verificationService = Module::getInstanceByName('pskyc')
    ->get('PrestaShop\\Module\\Pskyc\\Service\\VerificationService');

// Available methods:
$verification = $verificationService->getMostRecentVerification($customerId);
$isExpiringSoon = $verificationService->isExpiringSoon($verificationId);
$verifications = $verificationService->getVerificationsByCustomerId($customerId);
```

#### 2. Configuration API

Check module configuration programmatically:

```php
// Get KYC required categories
$requiredCategories = json_decode(
    Configuration::get('PSKYC_KYC_REQUIRED_CATEGORIES') ?: '[]',
    true
);

// Check if auto-notifications enabled
$autoNotify = (bool)Configuration::get('PSKYC_AUTO_NOTIFICATIONS');

// Get retention days
$retentionDays = (int)Configuration::get('PSKYC_RETENTION_DAYS');
```

#### 3. Template Integration

Include KYC verification links in your custom checkout:

```smarty
{* In your checkout template *}
{if Module::isInstalled('pskyc') && Module::isEnabled('pskyc')}
    {include file='module:pskyc/views/templates/front/checkout/kyc-warning.tpl'}
{/if}
```

#### 4. JavaScript Events

The module dispatches custom events you can listen to:

```javascript
// Listen for KYC status changes
document.addEventListener('pskyc:statusChanged', function(e) {
    console.log('KYC status changed:', e.detail);
    // Refresh your checkout UI
});

// Listen for KYC requirement check
document.addEventListener('pskyc:checkRequired', function(e) {
    console.log('KYC required for cart:', e.detail.required);
});
```

---

## Troubleshooting

### Common Issues

#### Issue: KYC validation not triggered in Prestahero checkout

**Possible causes:**
1. Override class not properly installed
2. Cache not cleared after installing override
3. Prestahero module updated and changed structure

**Solutions:**
1. Clear PrestaShop cache completely
2. Check override file location and class name
3. Enable debug mode to see PHP errors
4. Check PrestaShop logs: `var/logs/`

#### Issue: Customer can complete order without KYC approval

**Possible causes:**
1. `actionValidateOrder` hook not registered
2. KYC required categories not configured
3. Service error not properly handled

**Solutions:**
1. Reinstall the KYC module to re-register hooks
2. Check module configuration in back office
3. Add error logging to see what's happening:

```php
// Add to pskyc.php hookActionValidateOrder
PrestaShopLogger::addLog(
    'KYC Check: ' . json_encode([
        'customer_id' => $customer->id,
        'verification' => $verification,
        'required' => $kycRequired
    ]),
    1,
    null,
    'Pskyc'
);
```

#### Issue: Redirect loop between checkout and KYC page

**Possible causes:**
1. Verification status not properly cached
2. Multiple redirects triggering in different hooks
3. Session issue with customer data

**Solutions:**
1. Add session flag to prevent multiple redirects:

```php
// In your validation code
if (isset($this->context->cookie->kyc_redirect_attempted)) {
    return true; // Already attempted redirect
}

$this->context->cookie->kyc_redirect_attempted = 1;
$this->context->cookie->write();
```

2. Use proper HTTP redirect codes (302 for temporary)
3. Clear cookies and sessions during testing

#### Issue: Override class not loading

**Possible causes:**
1. Wrong class name or file structure
2. PrestaShop class index needs rebuilding
3. File permissions issue

**Solutions:**
1. Check class name matches file structure exactly
2. Regenerate class index: Delete `var/cache/prod/class_index.php`
3. Check file permissions (should be 644)
4. Use PrestaShop's class override naming convention

### Debug Mode

Enable debug mode to see detailed errors:

1. Edit `config/defines.inc.php`:
```php
define('_PS_MODE_DEV_', true);
```

2. Enable error display in `config/defines.inc.php`:
```php
ini_set('display_errors', 'on');
error_reporting(E_ALL);
```

3. Check logs:
```bash
tail -f var/logs/*.log
```

### Contact Support

If you're still experiencing issues:

1. **Check GitHub Issues**: [github.com/vachmara/pskyc/issues](https://github.com/vachmara/pskyc/issues)
2. **Open a New Issue**: Include:
   - PrestaShop version
   - Prestahero module version
   - KYC module version
   - Error logs
   - Steps to reproduce

---

## Best Practices

### General Guidelines

1. **Always backup** before making changes
2. **Test in staging** environment first
3. **Keep modules updated** to latest versions
4. **Document your customizations** for future reference
5. **Use version control** for override files
6. **Monitor logs** regularly for errors
7. **Clear cache** after any code changes

### Security Considerations

1. **Never expose** verification status in frontend JavaScript
2. **Always validate server-side** - never rely on client-side only
3. **Use CSRF tokens** for AJAX requests
4. **Sanitize all inputs** in override classes
5. **Keep encryption keys secure** - never commit to version control

### Performance Tips

1. **Cache verification status** when possible
2. **Minimize database queries** in checkout flow
3. **Use lazy loading** for KYC services
4. **Optimize override classes** - only override what's necessary
5. **Profile your changes** to ensure no performance degradation

---

## Additional Resources

- [PrestaShop Module Development Guide](https://devdocs.prestashop-project.org/8/modules/)
- [PrestaShop Hook Reference](https://devdocs.prestashop-project.org/8/modules/concepts/hooks/)
- [Prestahero Documentation](https://prestahero.com/en/prestahero-one-page-checkout) 
- [KYC Module GitHub Repository](https://github.com/vachmara/pskyc)
- [PrestaShop Override System](https://devdocs.prestashop-project.org/8/modules/concepts/overrides/)

---

## License

This integration guide is part of the KYC Secure Upload module, released under the MIT License.

## Contributing

Found an issue or want to improve this guide? 

- Open an issue: [github.com/vachmara/pskyc/issues](https://github.com/vachmara/pskyc/issues)
- Submit a pull request: [github.com/vachmara/pskyc/pulls](https://github.com/vachmara/pskyc/pulls)

---

**Last Updated:** 2025-01-28
**Module Version:** 1.1.2+
**Tested with:** PrestaShop 8.1.x, 8.2.x
