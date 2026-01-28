# KYC Module Hooks Reference

Quick reference guide for all PrestaShop hooks used by the KYC Secure Upload module.

## Table of Contents
- [Checkout & Order Hooks](#checkout--order-hooks)
- [Admin Hooks](#admin-hooks)
- [Customer Account Hooks](#customer-account-hooks)
- [GDPR Compliance Hooks](#gdpr-compliance-hooks)
- [Email & Media Hooks](#email--media-hooks)

---

## Checkout & Order Hooks

### hookActionCheckoutRender
**Type:** Action Hook  
**When:** Before checkout page renders (standard PrestaShop checkout)  
**Purpose:** Inject KYC verification step into the checkout process  
**Parameters:**
```php
[
    'checkoutProcess' => CheckoutProcessCore // The checkout process object
]
```
**Returns:** void

**Usage:**
```php
public function hookActionCheckoutRender($params)
{
    // Get checkout process
    $checkoutProcess = $params['checkoutProcess'];
    
    // Add KYC step if required
    if ($this->isKycRequired()) {
        $kycStep = new KycStep($this->context, $this->getTranslator());
        $steps = $checkoutProcess->getSteps();
        array_splice($steps, 1, 0, [$kycStep]);
        $checkoutProcess->setSteps($steps);
    }
}
```

---

### hookActionValidateOrder
**Type:** Action Hook  
**When:** Before order is validated and created  
**Purpose:** Block order creation if KYC verification not approved  
**Parameters:**
```php
[
    'cart' => Cart,           // Customer's cart
    'order' => Order,         // Order being created (may be null)
    'customer' => Customer,   // Customer object
    'currency' => Currency,   // Order currency
    'orderStatus' => OrderState // Initial order status
]
```
**Returns:** bool (true = allow order, false = block order)

**Usage:**
```php
public function hookActionValidateOrder($params)
{
    $customer = $params['customer'];
    $cart = $params['cart'];
    
    if (!$this->isKycRequiredForCart($cart)) {
        return true; // Allow order
    }
    
    $verification = $this->getCustomerVerification($customer->id);
    if ($verification && $verification['status'] === 'approved') {
        return true; // KYC approved, allow order
    }
    
    // Block order
    $this->context->controller->errors[] = 'KYC verification required';
    return false;
}
```

**Critical for:** Third-party checkout modules (Prestahero, etc.)

---

### hookDisplayBeforeCarrier
**Type:** Display Hook  
**When:** Before carrier selection step in checkout  
**Purpose:** Display KYC warning/requirement notice  
**Parameters:** []  
**Returns:** string (HTML content)

**Usage:**
```php
public function hookDisplayBeforeCarrier($params)
{
    if (!$this->isKycRequired()) {
        return '';
    }
    
    $this->context->smarty->assign([
        'kyc_verify_url' => $this->getVerifyUrl(),
        'kyc_status' => $this->getCustomerStatus()
    ]);
    
    return $this->fetch('module:pskyc/views/templates/front/checkout/kyc-warning.tpl');
}
```

---

## Admin Hooks

### hookDisplayAdminCustomers
**Type:** Display Hook  
**When:** In admin customer view page  
**Purpose:** Display customer's KYC verification status  
**Parameters:**
```php
[
    'id_customer' => int // Customer ID being viewed
]
```
**Returns:** string (HTML content)

**Usage:**
```php
public function hookDisplayAdminCustomers($params)
{
    $customerId = $params['id_customer'];
    $verifications = $this->verificationService->getVerificationsByCustomerId($customerId);
    
    return $this->render('kyc_status.html.twig', [
        'verifications' => $verifications
    ]);
}
```

---

### hookDisplayAdminOrder
**Type:** Display Hook  
**When:** In admin order view page  
**Purpose:** Display KYC status for the order's customer  
**Parameters:**
```php
[
    'id_order' => int // Order ID being viewed
]
```
**Returns:** string (HTML content)

---

## Customer Account Hooks

### hookDisplayCustomerAccount
**Type:** Display Hook  
**When:** Customer account page (My Account)  
**Purpose:** Display link to KYC verification page  
**Parameters:** []  
**Returns:** string (HTML content)

**Usage:**
```php
public function hookDisplayCustomerAccount()
{
    $this->context->smarty->assign([
        'frontController' => $this->context->link->getModuleLink($this->name, 'verify'),
        'customerId' => $this->context->customer->id
    ]);
    
    return $this->fetch('module:pskyc/views/templates/front/account/box.tpl');
}
```

---

### hookActionFrontControllerSetMedia
**Type:** Action Hook  
**When:** Before page assets are loaded on frontend  
**Purpose:** Register CSS/JS files for KYC functionality  
**Parameters:** []  
**Returns:** void

**Usage:**
```php
public function hookActionFrontControllerSetMedia()
{
    // Register CSS
    $this->context->controller->registerStylesheet(
        'module-pskyc-front',
        'modules/pskyc/views/css/front.css'
    );
    
    // Register JS
    $this->context->controller->registerJavascript(
        'module-pskyc-front',
        'modules/pskyc/views/js/front.js'
    );
}
```

---

## GDPR Compliance Hooks

### hookRegisterGDPRConsent
**Type:** Action Hook  
**When:** GDPR consent registration  
**Purpose:** Register consent for KYC data collection  
**Parameters:** []  
**Returns:** bool

---

### hookActionExportGDPRData
**Type:** Action Hook  
**When:** Customer requests GDPR data export  
**Purpose:** Export customer's KYC verification data  
**Parameters:**
```php
[
    'customer' => Customer // Customer requesting export
]
```
**Returns:** string (JSON encoded data)

**Usage:**
```php
public function hookActionExportGDPRData($params)
{
    $customer = $params['customer'];
    $verifications = $this->verificationService->getGdprData($customer->id);
    
    return json_encode($verifications);
}
```

---

### hookActionDeleteGDPRCustomer
**Type:** Action Hook  
**When:** Customer account is deleted (GDPR right to erasure)  
**Purpose:** Delete all KYC data for the customer  
**Parameters:**
```php
[
    'customer' => Customer // Customer being deleted
]
```
**Returns:** void

**Usage:**
```php
public function hookActionDeleteGDPRCustomer($params)
{
    $customer = $params['customer'];
    $this->verificationService->deleteVerificationsByCustomerId($customer->id);
}
```

---

## Email & Media Hooks

### hookActionListMailThemes
**Type:** Action Hook  
**When:** Email theme list is being built  
**Purpose:** Register KYC email templates  
**Parameters:**
```php
[
    'mailThemes' => ThemeCollectionInterface
]
```
**Returns:** void

**Usage:**
```php
public function hookActionListMailThemes(array $hookParams)
{
    $themes = $hookParams['mailThemes'];
    
    foreach ($themes as $theme) {
        $this->addLayoutsToTheme($theme, $theme->getName());
    }
}
```

---

## Hook Registration

All hooks are registered during module installation in `install()` method:

```php
public function install()
{
    return parent::install()
        && $this->registerHook('actionCheckoutRender')
        && $this->registerHook('actionValidateOrder')
        && $this->registerHook('displayBeforeCarrier')
        && $this->registerHook('actionFrontControllerSetMedia')
        && $this->registerHook('displayAdminCustomers')
        && $this->registerHook('displayAdminOrder')
        && $this->registerHook('displayCustomerAccount')
        && $this->registerHook('registerGDPRConsent')
        && $this->registerHook('actionDeleteGDPRCustomer')
        && $this->registerHook('actionExportGDPRData')
        && $this->registerHook('actionListMailThemes');
}
```

---

## Third-Party Checkout Compatibility

### Critical Hooks for Third-Party Checkouts

The following hooks are **essential** for compatibility with third-party checkout modules:

1. **hookActionValidateOrder** - Universal order blocking point
2. **hookDisplayBeforeCarrier** - Early warning display
3. **hookActionFrontControllerSetMedia** - Asset loading

### Prestahero-Specific Integration

For Prestahero One Page Checkout, the module relies on:
- `hookActionValidateOrder` (primary validation point)
- Frontend JavaScript validation (views/js/front.js)
- Override class option (see INTEGRATION.md)

### Custom Checkout Module Integration

If your checkout module doesn't trigger standard hooks:
1. Use override classes (see INTEGRATION.md)
2. Call verification service directly
3. Listen to custom JavaScript events

---

## Testing Hooks

### Verify Hook Registration

```php
// Check if hook is registered
$hookId = Hook::getIdByName('actionValidateOrder');
$isRegistered = Hook::isModuleRegisteredOnHook($this, $hookId);
```

### Trigger Hook Manually (Testing)

```php
// Trigger a hook for testing
Hook::exec('actionValidateOrder', [
    'cart' => $cart,
    'customer' => $customer
]);
```

### Debug Hook Execution

Add logging to your hook methods:

```php
public function hookActionValidateOrder($params)
{
    PrestaShopLogger::addLog(
        'KYC hookActionValidateOrder triggered: ' . json_encode([
            'customer_id' => $params['customer']->id,
            'cart_id' => $params['cart']->id
        ]),
        1,
        null,
        'Pskyc'
    );
    
    // Your hook logic...
}
```

---

## Common Issues

### Hook Not Firing
**Causes:**
- Hook not registered during install
- Module disabled
- Hook doesn't exist in PrestaShop version

**Solutions:**
1. Reinstall module
2. Check hook exists: `Hook::getIdByName('hookName')`
3. Check module is active

### Hook Registered But No Effect
**Causes:**
- Return value incorrect
- Exception thrown in hook
- Another module blocks hook

**Solutions:**
1. Check return types (bool for action, string for display)
2. Add try-catch and logging
3. Check hook execution order

---

## Resources

- [PrestaShop Hook Documentation](https://devdocs.prestashop-project.org/8/modules/concepts/hooks/)
- [Module Integration Guide](INTEGRATION.md)
- [PrestaShop 8 Hook List](https://devdocs.prestashop-project.org/8/modules/concepts/hooks/list-of-hooks/)

---

**Last Updated:** 2025-01-28  
**Module Version:** 1.1.2+
