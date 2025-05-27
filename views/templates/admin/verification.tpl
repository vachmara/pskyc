{*
* MIT License
* Copyright (c) 2025 Valentin Chmara
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-eye"></i>
        {l s='KYC Verification Details' mod='pskyc'}
        <span class="panel-heading-action">
            <a class="list-toolbar-btn" href="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}">
                <span title="" data-toggle="tooltip" class="label-tooltip" data-original-title="{l s='Back to list' mod='pskyc'}" data-html="true">
                    <i class="process-icon-back"></i>
                </span>
            </a>
        </span>
    </div>

    <div class="panel-body">
        {* Customer Information *}
        <div class="row">
            <div class="col-md-6">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-user"></i> {l s='Customer Information' mod='pskyc'}
                    </div>
                    <div class="panel-body">
                        <table class="table">
                            <tr>
                                <td><strong>{l s='Customer ID:' mod='pskyc'}</strong></td>
                                <td>{$verification.id_customer|escape:'html':'UTF-8'}</td>
                            </tr>
                            <tr>
                                <td><strong>{l s='Name:' mod='pskyc'}</strong></td>
                                <td>{$verification.customer_firstname|escape:'html':'UTF-8'} {$verification.customer_lastname|escape:'html':'UTF-8'}</td>
                            </tr>
                            <tr>
                                <td><strong>{l s='Email:' mod='pskyc'}</strong></td>
                                <td>{$verification.customer_email|escape:'html':'UTF-8'}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel">
                    <div class="panel-heading">
                        <i class="icon-info"></i> {l s='Verification Status' mod='pskyc'}
                    </div>
                    <div class="panel-body">
                        <table class="table">
                            <tr>
                                <td><strong>{l s='Status:' mod='pskyc'}</strong></td>
                                <td>
                                    {if $verification.status == 'pending'}
                                        <span class="label label-warning">{l s='Pending' mod='pskyc'}</span>
                                    {elseif $verification.status == 'under_review'}
                                        <span class="label label-info">{l s='Under Review' mod='pskyc'}</span>
                                    {elseif $verification.status == 'approved'}
                                        <span class="label label-success">{l s='Approved' mod='pskyc'}</span>
                                    {elseif $verification.status == 'rejected'}
                                        <span class="label label-danger">{l s='Rejected' mod='pskyc'}</span>
                                    {elseif $verification.status == 'requested_more_info'}
                                        <span class="label label-warning">{l s='More Info Required' mod='pskyc'}</span>
                                    {else}
                                        <span class="label label-default">{$verification.status|escape:'html':'UTF-8'}</span>
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <td><strong>{l s='Submitted:' mod='pskyc'}</strong></td>
                                <td>{$verification.date_submitted|date_format:'%Y-%m-%d %H:%M:%S'}</td>
                            </tr>
                            <tr>
                                <td><strong>{l s='Last Updated:' mod='pskyc'}</strong></td>
                                <td>{$verification.date_updated|date_format:'%Y-%m-%d %H:%M:%S'}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {* Documents Section *}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-file"></i> {l s='Submitted Documents' mod='pskyc'}
            </div>
            <div class="panel-body">
                {if $documents && count($documents) > 0}
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{l s='Document Type' mod='pskyc'}</th>
                                    <th>{l s='Original Name' mod='pskyc'}</th>
                                    <th>{l s='Upload Date' mod='pskyc'}</th>
                                    <th>{l s='Actions' mod='pskyc'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach $documents as $document}
                                    <tr>
                                        <td>
                                            {if $document.document_type == 'identity'}
                                                <i class="icon-credit-card"></i> {l s='Identity Document' mod='pskyc'}
                                            {elseif $document.document_type == 'address_proof'}
                                                <i class="icon-home"></i> {l s='Address Proof' mod='pskyc'}
                                            {else}
                                                <i class="icon-file"></i> {$document.document_type|escape:'html':'UTF-8'}
                                            {/if}
                                        </td>
                                        <td>{$document.original_filename|escape:'html':'UTF-8'}</td>
                                        <td>{$document.date_uploaded|date_format:'%Y-%m-%d %H:%M:%S'}</td>
                                        <td>
                                            <a href="{$current_index|escape:'html':'UTF-8'}&action=download_document&id_document={$document.id_kyc_document|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" 
                                               class="btn btn-default btn-sm" title="{l s='Download' mod='pskyc'}">
                                                <i class="icon-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                {else}
                    <div class="alert alert-warning">
                        <i class="icon-warning"></i> {l s='No documents uploaded yet.' mod='pskyc'}
                    </div>
                {/if}
            </div>
        </div>

        {* Admin Notes *}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-comment"></i> {l s='Admin Notes' mod='pskyc'}
            </div>
            <div class="panel-body">
                {if $verification.admin_note}
                    <div class="alert alert-info">
                        <strong>{l s='Current Note:' mod='pskyc'}</strong><br>
                        {$verification.admin_note|escape:'html':'UTF-8'|nl2br}
                    </div>
                {/if}

                <form method="post" action="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}">
                    <input type="hidden" name="id_kyc_verification" value="{$verification.id_kyc_verification|escape:'html':'UTF-8'}">
                    
                    <div class="form-group">
                        <label for="admin_note">{l s='Add/Update Note:' mod='pskyc'}</label>
                        <textarea name="admin_note" id="admin_note" class="form-control" rows="3" 
                                  placeholder="{l s='Add your notes here...' mod='pskyc'}">{$verification.admin_note|escape:'html':'UTF-8'}</textarea>
                    </div>

                    <div class="form-group">
                        <div class="btn-group">
                            <button type="submit" name="submitApprove" class="btn btn-success">
                                <i class="icon-check"></i> {l s='Approve' mod='pskyc'}
                            </button>
                            <button type="submit" name="submitReject" class="btn btn-danger">
                                <i class="icon-remove"></i> {l s='Reject' mod='pskyc'}
                            </button>
                            <button type="submit" name="submitRequest_info" class="btn btn-warning">
                                <i class="icon-question"></i> {l s='Request More Info' mod='pskyc'}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>