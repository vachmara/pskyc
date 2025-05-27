{*
* MIT License
* Copyright (c) 2025 Valentin Chmara
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i> {l s='KYC Secure Upload Configuration' mod='pskyc'}
    </div>
    
    <div class="panel-body">
        <div class="alert alert-info">
            <p><strong>{l s='Welcome to KYC Secure Upload!' mod='pskyc'}</strong></p>
            <p>{l s='This module allows your customers to upload identity documents for verification. Configure the settings below to customize the behavior.' mod='pskyc'}</p>
        </div>

        {if isset($confirmation)}
            <div class="alert alert-success">
                {l s='Settings updated successfully!' mod='pskyc'}
            </div>
        {/if}

        {if isset($errors) && count($errors)}
            <div class="alert alert-danger">
                <ul>
                    {foreach from=$errors item=error}
                        <li>{$error}</li>
                    {/foreach}
                </ul>
            </div>
        {/if}

        {$form_html nofilter}
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-info"></i> {l s='Module Information' mod='pskyc'}
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <h4>{l s='Current Status' mod='pskyc'}</h4>
                <ul class="list-unstyled">
                    <li><strong>{l s='Version:' mod='pskyc'}</strong> 0.1.0</li>
                    <li><strong>{l s='Upload Directory:' mod='pskyc'}</strong> {$module_dir}secure_upload/</li>
                    <li><strong>{l s='Encryption:' mod='pskyc'}</strong> 
                        {if function_exists('openssl_encrypt')}
                            <span class="label label-success">{l s='Available' mod='pskyc'}</span>
                        {else}
                            <span class="label label-danger">{l s='Not Available' mod='pskyc'}</span>
                        {/if}
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h4>{l s='Quick Actions' mod='pskyc'}</h4>
                <p>
                    <a href="#" class="btn btn-default" onclick="alert('Feature coming soon!');">
                        <i class="icon-list"></i> {l s='View Verifications' mod='pskyc'}
                    </a>
                </p>
                <p>
                    <a href="#" class="btn btn-default" onclick="alert('Feature coming soon!');">
                        <i class="icon-cogs"></i> {l s='Manage Documents' mod='pskyc'}
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>