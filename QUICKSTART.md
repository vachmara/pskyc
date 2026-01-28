# Quick Start: Prestahero One Page Checkout Integration

**Fast track guide for integrating KYC Secure Upload with Prestahero checkout.**

## ✅ Automatic Integration (Recommended)

**Good news!** The KYC module already works with Prestahero out-of-the-box.

### What You Get Automatically

1. **Order Blocking** - Orders are automatically blocked if KYC is not approved
2. **Validation** - Server-side validation prevents bypassing
3. **Logging** - All blocked orders are logged for review

### Setup Steps

1. Install/Update the KYC Secure Upload module
2. Configure KYC-required categories in module settings
3. That's it! The integration works automatically via `hookActionValidateOrder`

### How It Works

```
Customer adds KYC-required product → Proceeds to checkout → 
Attempts to place order → Module checks KYC status →
If NOT approved: Order blocked + Redirect to verification
If approved: Order proceeds normally
```

---

## 🎨 Enhanced Integration (Optional)

For better user experience, add early warnings in the checkout flow.

### Option A: Use Built-in Warning (Easy)

The module automatically displays a warning before carrier selection via `hookDisplayBeforeCarrier`. No action needed!

### Option B: Custom Warning Position (Advanced)

Add the warning to a different position in Prestahero's checkout:

**In your theme template:**
```smarty
{* Add wherever you want the warning to appear *}
{if Module::isInstalled('pskyc') && Module::isEnabled('pskyc')}
    {hook h='displayBeforeCarrier'}
{/if}
```

---

## 🔧 Advanced Customization (For Developers)

If you need to customize behavior, see [INTEGRATION.md](INTEGRATION.md) for:
- Override class templates
- JavaScript validation
- Custom styling
- Troubleshooting

---

## 📊 Testing Your Integration

### Test Checklist

1. **Configure module:**
   ```
   Modules → KYC Secure Upload → Configure
   Select categories that require KYC
   ```

2. **Test as unverified customer:**
   - Add KYC-required product to cart
   - Go through Prestahero checkout
   - Try to place order
   - ✅ Should see error and redirect to KYC page

3. **Test as verified customer:**
   - Complete KYC verification (admin approves)
   - Add same product to cart
   - Complete checkout
   - ✅ Order should process normally

4. **Test with non-KYC products:**
   - Add regular product to cart
   - Complete checkout
   - ✅ No KYC check, order processes normally

---

## 🐛 Common Issues

### "Order goes through without KYC approval"

**Fix:** Reinstall the module to ensure `hookActionValidateOrder` is registered:
```
Modules → KYC Secure Upload → Uninstall → Install
```

### "Customer sees error but doesn't redirect"

**Fix:** Check PrestaShop logs at `var/logs/` for errors. Ensure module is active.

### "Warning doesn't appear in checkout"

**Fix:** Clear cache:
```
Advanced Parameters → Performance → Clear Cache
```

---

## 💡 Pro Tips

1. **Test in staging first** - Always test integration in a non-production environment
2. **Clear cache after changes** - PrestaShop caches templates and configs
3. **Enable debug mode** - Helps identify issues during setup
4. **Check logs** - Module logs all KYC-related actions

---

## 📚 Need More Help?

- **Full Integration Guide:** [INTEGRATION.md](INTEGRATION.md)
- **Hook Reference:** [HOOKS.md](HOOKS.md)
- **GitHub Issues:** [github.com/vachmara/pskyc/issues](https://github.com/vachmara/pskyc/issues)

---

## 🎯 Summary

✅ **Zero Configuration** - Works automatically with Prestahero  
✅ **Server-Side Secure** - Cannot be bypassed  
✅ **User Friendly** - Clear error messages  
✅ **Production Ready** - Tested and documented  

**Installation time:** 5 minutes  
**Configuration time:** 2 minutes  
**Testing time:** 5 minutes  

**Total setup time: ~12 minutes**

---

**Last Updated:** 2025-01-28  
**Module Version:** 1.1.2+  
**Tested With:** Prestahero One Page Checkout 4.x, 5.x
