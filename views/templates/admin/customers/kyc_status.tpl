<div class="col">
  <div class="card">
    <h3 class="card-header">
      <i class="material-icons">verified_user</i> {l s='Know Your Customer' d='Modules.Pskyc.Shop'}
      <span class="badge badge-primary rounded">{$count}</span>
    </h3>
    <div class="card-body">
      {if $verifications && count($verifications) > 0}
        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead>
              <tr>
                <th>{l s='Document Type' d='Modules.Pskyc.Shop'}</th>
                <th>{l s='Status' d='Modules.Pskyc.Shop'}</th>
                <th>{l s='Submission Date' d='Modules.Pskyc.Shop'}</th>
                <th>{l s='Last Update' d='Modules.Pskyc.Shop'}</th>
                <th class="text-center">{l s='Actions' d='Modules.Pskyc.Shop'}</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$verifications item=verification}
                <tr>
                  <td>
                    <i class="material-icons text-muted mr-1">description</i>
                    {$verification.document_type|escape:'html':'UTF-8'}
                  </td>
                  <td>
                    {if $verification.status == 'pending'}
                      <span class="badge badge-warning">
                        <i class="material-icons">schedule</i>
                        {l s='Pending Review' d='Modules.Pskyc.Shop'}
                      </span>
                    {elseif $verification.status == 'approved'}
                      <span class="badge badge-success">
                        <i class="material-icons">check_circle</i>
                        {l s='Approved' d='Modules.Pskyc.Shop'}
                      </span>
                    {elseif $verification.status == 'rejected'}
                      <span class="badge badge-danger">
                        <i class="material-icons">cancel</i>
                        {l s='Rejected' d='Modules.Pskyc.Shop'}
                      </span>
                    {else}
                      <span class="badge badge-secondary">
                        <i class="material-icons">help</i>
                        {$verification.status|escape:'html':'UTF-8'}
                      </span>
                    {/if}
                  </td>
                  <td>
                    <small class="text-muted">
                      {dateFormat date=$verification.date_add format="Y-m-d H:i:s"}
                    </small>
                  </td>
                  <td>
                    <small class="text-muted">
                      {if $verification.date_upd != $verification.date_add}
                        {dateFormat date=$verification.date_upd format="Y-m-d H:i:s"}
                      {else}
                        -
                      {/if}
                    </small>
                  </td>
                  <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                      <a href="{$link->getAdminLink('AdminPskyc')}&action=view&id_verification={$verification.id_verification|intval}" 
                         class="btn btn-outline-primary btn-sm" 
                         title="{l s='View Details' d='Modules.Pskyc.Shop'}">
                        <i class="material-icons">visibility</i>
                      </a>
                      
                      {if $verification.status == 'pending'}
                        <a href="{$link->getAdminLink('AdminPskyc')}&action=approve&id_verification={$verification.id_verification|intval}" 
                           class="btn btn-outline-success btn-sm" 
                           title="{l s='Approve' d='Modules.Pskyc.Shop'}"
                           onclick="return confirm('{l s='Are you sure you want to approve this verification?' d='Modules.Pskyc.Shop'}')">
                          <i class="material-icons">check</i>
                        </a>
                        
                        <a href="{$link->getAdminLink('AdminPskyc')}&action=reject&id_verification={$verification.id_verification|intval}" 
                           class="btn btn-outline-danger btn-sm" 
                           title="{l s='Reject' d='Modules.Pskyc.Shop'}"
                           onclick="return confirm('{l s='Are you sure you want to reject this verification?' d='Modules.Pskyc.Shop'}')">
                          <i class="material-icons">close</i>
                        </a>
                      {/if}
                      
                      {if $verification.has_document}
                        <a href="{$link->getAdminLink('AdminPskyc')}&action=download&id_verification={$verification.id_verification|intval}" 
                           class="btn btn-outline-info btn-sm" 
                           title="{l s='Download Document' d='Modules.Pskyc.Shop'}">
                          <i class="material-icons">download</i>
                        </a>
                      {/if}
                    </div>
                  </td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        </div>
        
        <div class="mt-3">
          <a href="{$link->getAdminLink('AdminPskyc')}&id_customer={$customerId|intval}" 
             class="btn btn-primary">
            <i class="material-icons">launch</i>
            {l s='Manage All KYC Verifications' d='Modules.Pskyc.Shop'}
          </a>
        </div>
      {else}
        <div class="alert alert-info">
          <strong>{l s='No KYC verifications found' d='Modules.Pskyc.Shop'}</strong>
        </div>
      {/if}
    </div>
  </div>
</div>

<style>
.badge i.material-icons {
  font-size: 14px;
  vertical-align: middle;
  margin-right: 2px;
}

.btn-group-sm .btn i.material-icons {
  font-size: 16px;
}

.table th {
  border-top: none;
  font-weight: 600;
  color: #6c757d;
  font-size: 0.875rem;
}

.table td {
  vertical-align: middle;
}
</style>