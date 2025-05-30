{*
* MIT License
* Copyright (c) 2025 Valentin Chmara
*}

<section id="checkout-kyc-step" class="checkout-step -current -reachable">
  <h1 class="step-title">
    <span class="step-number">{$position}</span>
    {l s='Identity Verification Required' d='Modules.Pskyc.Shop'}
  </h1>

  <div class="content">
    <p>{l s='Your cart contains products that require identity verification before you can complete your purchase.' d='Modules.Pskyc.Shop'}</p>
    
    <a href="{$kyc_url|escape:'html':'UTF-8'}" class="btn btn-primary">
      {l s='Complete Identity Verification' d='Modules.Pskyc.Shop'}
    </a>
    
    <p class="text-muted">{l s='You will be redirected to upload your documents.' d='Modules.Pskyc.Shop'}</p>
  </div>
</section>