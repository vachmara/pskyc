# Integration Guide for Third-Party Checkout Modules

## Table of Contents
- [Overview](#overview)
- [PrestaShop Standard Hooks Used](#prestashop-standard-hooks-used)
- [Integration Strategies](#integration-strategies)
- [Custom Validation Integration](#custom-validation-integration)
- [Override Classes](#override-classes)
- [Extension Points](#extension-points)
- [Troubleshooting](#troubleshooting)

---

## Overview

The **KYC Secure Upload** module is designed to work seamlessly with PrestaShop's standard checkout process. However, many merchants use third-party checkout modules that replace or extend the standard checkout workflow.

This guide provides documentation on integrating the KYC module with third-party checkout modules while maintaining compatibility and avoiding direct core modifications.

---

## PrestaShop Standard Hooks Used

The KYC Secure Upload module leverages the following PrestaShop hooks for checkout integration:

### Primary Hooks

| Hook Name | Type | Trigger Point | Purpose | Parameters |
|-----------|------|---------------|---------|------------|
| `actionCheckoutRender` | Action | Before checkout page renders | Inject KYC step into checkout process | `['checkoutProcess']` |
| `actionValidateOrderBefore` | Action | Before order validation | Block order if KYC not approved | `['cart', 'customer', 'currency', 'id_order_state', 'payment_method']` |
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

## Integration Strategies

There are several approaches to integrate KYC verification with third-party checkout modules:

### 1. Pre-Order Validation Hook (Recommended)

Use the `actionValidateOrderBefore` hook to block order creation if KYC is not approved.

**Pros:**
- ✅ Works with any checkout module
- ✅ No override classes needed
- ✅ Update-safe

**Cons:**
- ⚠️ Customer reaches final step before seeing error

### 2. Custom Override Classes

Create override classes that extend the third-party checkout controller to add early KYC validation.

**Pros:**
- ✅ Early validation in checkout flow
- ✅ Better user experience

**Cons:**
- ⚠️ Requires module-specific code
- ⚠️ May need updates when the third-party module updates

### 3. Frontend JavaScript Validation

Inject JavaScript that validates KYC status before order submission.

**Pros:**
- ✅ Immediate feedback to customer
- ✅ Works with AJAX checkout

**Cons:**
- ⚠️ Can be bypassed (always use server-side validation too)

## PrestaShop 8.x Compatibility Matrix (Important)

The **KYC Secure Upload** module provides a PrestaShop-9–style `actionValidateOrderBefore` hook on **PrestaShop 8.x** through an **optional `PaymentModule` override shim**.

This allows blocking order creation *server-side* **only if the checkout module uses `PaymentModule::validateOrder()`**.

Because many checkout modules modify or replace the core order-validation flow, compatibility must be assessed per module.

> **TODO (maintainers):** Before completing this matrix, identify whether each checkout/payment module calls  
> `PaymentModule::validateOrder()` → (Hard-block possible),  
> OR bypasses it → (Requires module-specific integration).

---

### Compatibility Levels
- **A — Fully Compatible**  
  Uses core `PaymentModule::validateOrder()` → PS8 hard-block works.
- **B — Partially Compatible**  
  Uses some core hooks but may bypass parts of validation → soft-block + payment gating recommended.
- **C — Requires Integration**  
  Custom flow / AJAX confirm controller → override shim has no effect.
- **D — Unknown / Not Tested**

### Risk Levels
- **Low**: Standard checkout flow
- **Medium**: OPC with custom rendering but calls `validateOrder()`
- **High**: OPC creates orders via custom controllers / endpoints

---

## Checkout Module Compatibility Matrix

| Checkout Module | Vendor | PS8 Compatibility | Enforcement Level | Risk | Notes |
|-----------------|--------|------------------|-------------------|-------|-------|
| **Default Checkout** | PrestaShop | **A** | Hard-block + Payment Gating | Low | Fully standard; recommended baseline. |
| **Prestahero One Page Checkout** | PrestaHero | **B / C (verify)** | Soft-block; may need integration | Medium/High | Some versions skip standard hooks; confirm if `validateOrder()` is used. |
| **Knowband SuperCheckout** | Knowband | **C (likely)** | Soft-block; integration recommended | High | Known to bypass multiple core hooks; confirm their "confirm order" controller. |
| **One Page Checkout PS** | PresTeamShop | **B / C (verify)** | Soft-block | Medium | Hybrid OPC; may or may not trigger core validation. |
| **Revolut / Stripe / PayPal modules** | Payments | **A** | Hard-block | Low | Most payment modules call `validateOrder()` normally. |
| **Custom Checkout Modules** | Varies | **D** | Varies | High | Must check call chain manually. |

> ⚠ **Warning:**  
> On PrestaShop 8.x, the hard-block mechanism **only works** with checkout modules that call  
> `PaymentModule::validateOrder()`.  
> Modules that bypass this method require **specific integration** (controller override or validation hook in their flow).

---

## How to Verify a Module's Compatibility (Quick Test)

1. **Search the module for:**  
   ```
   validateOrder(
   ```
   If found → Likely **A** (hard-block possible).

2. If not found, search for:  
   ```
   placeOrder(
   createOrder(
   OrderController(
   ajaxValidate
   ```
   → Likely **C** (custom confirm flow → override shim won't execute).

3. Test by enabling KYC hard-block and attempting checkout:
   - If hook fires → module is compatible.  
   - If order is created anyway → requires integration.

---

## Summary

- **PS9 → native hard-block** (no overrides)  
- **PS8 → optional override shim** adds `actionValidateOrderBefore`  
- Works with modules using **standard core validation**  
- Custom OPC modules may require **module-specific integration**  
- Always enable **payment gating** for consistent UX

---

## Custom Validation Integration

### Server-Side Validation

Use the verification service to check KYC status:

```php
$verificationService = Module::getInstanceByName('pskyc')
    ->get('PrestaShop\\Module\\Pskyc\\Service\\VerificationService');

$verification = $verificationService->getMostRecentVerification($customerId);
$isVerified = $verification && $verification['status'] === 'approved';
```

### AJAX Validation

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

When creating override classes for third-party checkout modules:

1. Only override necessary methods
2. Check method existence for compatibility
3. Document changes clearly
4. Test thoroughly

**Example structure:**
```php
class ThirdPartyCheckoutOverride extends ThirdPartyCheckoutController
{
    public function init()
    {
        parent::init();
        
        if (!$this->validateKyc()) {
            // Redirect to KYC page
        }
    }
    
    protected function validateKyc()
    {
        // KYC validation logic
    }
}
```

---

## Extension Points

The KYC module provides extension points for third-party modules:

- **Service Layer**: Access verification services programmatically
- **Configuration API**: Check module settings
- **Template Integration**: Include KYC elements in custom templates
- **JavaScript Events**: Listen to KYC status changes

---

## Troubleshooting

### Common Issues

#### Issue: KYC validation not triggered in third-party checkout

**Possible causes:**
1. Override class not properly installed
2. Cache not cleared after installing override
3. Third-party module updated and changed structure

**Solutions:**
1. Clear PrestaShop cache completely
2. Check override file location and class name
3. Enable debug mode to see PHP errors

#### Issue: Customer can complete order without KYC approval

**Possible causes:**
1. `actionValidateOrderBefore` hook not registered
2. KYC required categories not configured
3. Service error not properly handled

**Solutions:**
1. Reinstall the KYC module to re-register hooks
2. Check module configuration in back office
3. Add error logging

#### Issue: Redirect loop between checkout and KYC page

**Possible causes:**
1. Verification status not properly cached
2. Multiple redirects triggering in different hooks
3. Session issue with customer data

**Solutions:**
1. Add session flag to prevent multiple redirects
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

### Debug Mode

Enable debug mode to see detailed errors:

1. Edit `config/defines.inc.php`:
```php
define('_PS_MODE_DEV_', true);
```

2. Check logs: `var/logs/`

---

## Best Practices

### General Guidelines

1. Always backup before making changes
2. Test in staging environment first
3. Keep modules updated to latest versions
4. Document your customizations
5. Use version control for override files
6. Monitor logs regularly
7. Clear cache after any code changes

### Security Considerations

1. Never expose verification status in frontend JavaScript
2. Always validate server-side
3. Use CSRF tokens for AJAX requests
4. Sanitize all inputs in override classes
5. Keep encryption keys secure

### Performance Tips

1. Cache verification status when possible
2. Minimize database queries in checkout flow
3. Use lazy loading for KYC services
4. Optimize override classes

---

## Additional Resources

- [PrestaShop Module Development Guide](https://devdocs.prestashop-project.org/8/modules/)
- [PrestaShop Hook Reference](https://devdocs.prestashop-project.org/8/modules/concepts/hooks/)
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

**Last Updated:** 2026-02-01
**Module Version:** 1.1.3
**Tested with:** PrestaShop 8.1.x, 8.2.x
