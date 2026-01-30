{*
* MIT License
* Copyright (c) 2025 Valentin Chmara
*
* KYC Warning Template for Checkout
* Displayed before carrier selection when KYC is required but not approved
*}

<div class="alert alert-warning pskyc-checkout-warning" data-kyc-required="true" data-kyc-url="{$kyc_verify_url|escape:'html':'UTF-8'}">
  <h4 class="alert-heading">
    <i class="material-icons">warning</i>
    {l s='Identity Verification Required' d='Modules.Pskyc.Shop'}
  </h4>
  <p>
    {l s='Your cart contains products that require identity verification before you can complete your purchase.' d='Modules.Pskyc.Shop'}
  </p>
  
  {if $kyc_status === 'pending' || $kyc_status === 'under_review'}
    <p>
      <strong>{l s='Status:' d='Modules.Pskyc.Shop'}</strong> 
      {if $kyc_status === 'pending'}
        {l s='Your documents are pending review.' d='Modules.Pskyc.Shop'}
      {else}
        {l s='Your documents are currently under review.' d='Modules.Pskyc.Shop'}
      {/if}
      {l s='You will be notified once the verification is complete.' d='Modules.Pskyc.Shop'}
    </p>
    <p class="text-muted">
      <small>{l s='You cannot complete your order until your identity is verified and approved.' d='Modules.Pskyc.Shop'}</small>
    </p>
  {elseif $kyc_status === 'rejected' || $kyc_status === 'requested_more_info'}
    <p>
      <strong>{l s='Status:' d='Modules.Pskyc.Shop'}</strong> 
      {if $kyc_status === 'rejected'}
        {l s='Your verification was not approved.' d='Modules.Pskyc.Shop'}
      {else}
        {l s='Additional information is required.' d='Modules.Pskyc.Shop'}
      {/if}
    </p>
    <a href="{$kyc_verify_url|escape:'html':'UTF-8'}" class="btn btn-warning">
      {l s='Update Your Verification' d='Modules.Pskyc.Shop'}
    </a>
  {else}
    <p>
      {l s='Please complete the verification process to proceed with your order.' d='Modules.Pskyc.Shop'}
    </p>
    <a href="{$kyc_verify_url|escape:'html':'UTF-8'}" class="btn btn-primary">
      {l s='Start Verification' d='Modules.Pskyc.Shop'}
    </a>
  {/if}
</div>

<style>
.pskyc-checkout-warning {
  margin-bottom: 1rem;
  padding: 1rem;
}

.pskyc-checkout-warning .alert-heading {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.pskyc-checkout-warning .material-icons {
  font-size: 1.5rem;
}

.pskyc-checkout-warning .btn {
  margin-top: 0.5rem;
}
</style>
