{extends file='customer/page.tpl'}

{block name='page_title'}
    {l s='KYC - Verify your identity' d='Modules.Pskyc.Shop'}
{/block}

{block name='page_content'}
    <div class="container">
        <div class="row">
            <div class="col-xs-12">
                <h1 class="page-heading">
                    {l s='KYC - Verify your identity' d='Modules.Pskyc.Shop'}
                </h1>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12">
                {include file='modules/pskyc/views/templates/front/kyc_form.tpl'}
            </div>
        </div>
    </div>
{/block}