{extends file='customer/page.tpl'}

{block name='page_title'}
    {l s='KYC - Verify your identity' d='Modules.Pskyc.Shop'}
{/block}

{block name='page_content'}
    <div class="container">
        <section class="page_content">
            {include file='modules/pskyc/views/templates/front/account/form.tpl'}
        </section>
    </div>
{/block}