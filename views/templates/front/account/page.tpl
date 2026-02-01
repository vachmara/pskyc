{**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *}
{extends file='customer/page.tpl'}

{block name='page_title'}
    {l s='KYC - Verify your identity' d='Modules.Pskyc.Shop'}
{/block}

{block name='page_content'}
    <div class="container">
        <section class="page_content">
            {if $kyc_required_alert}
                <div class="alert alert-warning">
                    <p>{l s='Your order requires identity verification. Please complete the verification process below.' d='Modules.Pskyc.Shop'}</p>
                </div>
            {/if}
            {include file='modules/pskyc/views/templates/front/account/form.tpl'}
        </section>
    </div>
{/block}