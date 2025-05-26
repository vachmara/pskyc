{*
* MIT License
* Copyright (c) 2025 Valentin Chmara
*}

<div class="kyc-verification-form">
  {* Current Status Section *}
  {if isset($verification) && $verification}
    <div class="alert alert-info kyc-status">
      <h4><i class="material-icons">info</i> {l s='Current Verification Status' d='Modules.Pskyc.Shop'}</h4>
      <p>
        <strong>{l s='Status:' d='Modules.Pskyc.Shop'}</strong>
        <span class="status-badge status-{$verification.status|escape:'html':'UTF-8'}">
          {if $verification.status == 'pending'}
            {l s='Pending Review' d='Modules.Pskyc.Shop'}
          {elseif $verification.status == 'under_review'}
            {l s='Under Review' d='Modules.Pskyc.Shop'}
          {elseif $verification.status == 'approved'}
            {l s='Approved' d='Modules.Pskyc.Shop'}
          {elseif $verification.status == 'rejected'}
            {l s='Rejected' d='Modules.Pskyc.Shop'}
          {elseif $verification.status == 'requested_more_info'}
            {l s='More Information Required' d='Modules.Pskyc.Shop'}
          {elseif $verification.status == 'expired'}
            {l s='Expired' d='Modules.Pskyc.Shop'}
          {else}
            {$verification.status|escape:'html':'UTF-8'}
          {/if}
        </span>
      </p>
      {if $verification.admin_note}
        <p><strong>{l s='Admin Note:' d='Modules.Pskyc.Shop'}</strong></p>
        <p class="admin-note">{$verification.admin_note|escape:'html':'UTF-8'|nl2br}</p>
      {/if}
      <p><small>{l s='Submitted on:' d='Modules.Pskyc.Shop'}
          {$verification.date_submitted|date_format:'%B %e, %Y at %H:%M'}</small></p>
    </div>
  {/if}

  {* Upload Form *}
  {if !isset($verification) || $verification.status != 'approved'}
    <form id="kyc-upload-form" method="post" enctype="multipart/form-data"
      action="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}">
      <input type="hidden" name="action" value="upload_documents" />
      <input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}" />

      <div class="form-section">
        <h3><i class="material-icons">account_box</i> {l s='Identity Document' d='Modules.Pskyc.Shop'}</h3>
        <p class="help-text">
          {l s='Upload a clear photo or scan of your government-issued ID (passport, driver\'s license, national ID card).' d='Modules.Pskyc.Shop'}
        </p>

        <div class="form-group">
          <label for="id_document_type" class="form-control-label required">
            {l s='Document Type' d='Modules.Pskyc.Shop'}
          </label>
          <select name="id_document_type" id="id_document_type" class="form-control" required>
            <option value="">{l s='Select document type' d='Modules.Pskyc.Shop'}</option>
            <option value="passport">{l s='Passport' d='Modules.Pskyc.Shop'}</option>
            <option value="drivers_license" data-requires-both-sides="true">{l s='Driver\'s License' d='Modules.Pskyc.Shop'}</option>
            <option value="national_id" data-requires-both-sides="true">{l s='National ID Card' d='Modules.Pskyc.Shop'}</option>
            <option value="residence_permit" data-requires-both-sides="true">{l s='Residence Permit' d='Modules.Pskyc.Shop'}</option>
          </select>
        </div>

        {* Single document upload (for passports) *}
        <div class="form-group" id="id-single-upload">
          <label for="id_document" class="form-control-label required">
            {l s='Upload Identity Document' d='Modules.Pskyc.Shop'}
          </label>
          <input type="file" name="id_document" id="id_document" class="form-control-file" accept="image/*,.pdf" />
          <small class="form-text text-muted">
            {l s='Accepted formats: JPG, PNG, PDF. Maximum size: 10MB.' d='Modules.Pskyc.Shop'}
          </small>
          <div class="file-preview" id="id-preview"></div>
        </div>

        {* Front/Back document uploads (for two-sided documents) *}
        <div class="form-group" id="id-front-back-upload" style="display: none;">
          <div class="row">
            <div class="col-md-6">
              <label for="id_document_front" class="form-control-label required">
                <i class="material-icons">credit_card</i>
                {l s='Front Side' d='Modules.Pskyc.Shop'}
              </label>
              <input type="file" name="id_document_front" id="id_document_front" class="form-control-file" accept="image/*,.pdf" />
              <small class="form-text text-muted">
                {l s='Upload the front side of your document' d='Modules.Pskyc.Shop'}
              </small>
              <div class="file-preview" id="id-front-preview"></div>
            </div>
            <div class="col-md-6">
              <label for="id_document_back" class="form-control-label required">
                <i class="material-icons">flip_to_back</i>
                {l s='Back Side' d='Modules.Pskyc.Shop'}
              </label>
              <input type="file" name="id_document_back" id="id_document_back" class="form-control-file" accept="image/*,.pdf" />
              <small class="form-text text-muted">
                {l s='Upload the back side of your document' d='Modules.Pskyc.Shop'}
              </small>
              <div class="file-preview" id="id-back-preview"></div>
            </div>
          </div>
          <div class="alert alert-info mt-3">
            <i class="material-icons">info</i>
            <strong>{l s='Important:' d='Modules.Pskyc.Shop'}</strong>
            {l s='Please ensure both sides of your document are clearly visible and readable. All text and images should be sharp and unobstructed.' d='Modules.Pskyc.Shop'}
          </div>
        </div>
      </div>

      <div class="form-section">
        <h3><i class="material-icons">home</i> {l s='Proof of Address' d='Modules.Pskyc.Shop'}</h3>
        <p class="help-text">
          {l s='Upload a recent document showing your current address (utility bill, bank statement, rental agreement, etc.).' d='Modules.Pskyc.Shop'}
        </p>

        <div class="form-group">
          <label for="address_document_type" class="form-control-label required">
            {l s='Document Type' d='Modules.Pskyc.Shop'}
          </label>
          <select name="address_document_type" id="address_document_type" class="form-control" required>
            <option value="">{l s='Select document type' d='Modules.Pskyc.Shop'}</option>
            <option value="utility_bill">{l s='Utility Bill' d='Modules.Pskyc.Shop'}</option>
            <option value="bank_statement">{l s='Bank Statement' d='Modules.Pskyc.Shop'}</option>
            <option value="rental_agreement">{l s='Rental Agreement' d='Modules.Pskyc.Shop'}</option>
            <option value="tax_document">{l s='Tax Document' d='Modules.Pskyc.Shop'}</option>
            <option value="insurance_statement">{l s='Insurance Statement' d='Modules.Pskyc.Shop'}</option>
            <option value="government_letter">{l s='Government Letter' d='Modules.Pskyc.Shop'}</option>
          </select>
        </div>

        <div class="form-group">
          <label for="address_document" class="form-control-label required">
            {l s='Upload Proof of Address' d='Modules.Pskyc.Shop'}
          </label>
          <input type="file" name="address_document" id="address_document" class="form-control-file" accept="image/*,.pdf"
            required />
          <small class="form-text text-muted">
            {l s='Document must be dated within the last 3 months. Accepted formats: JPG, PNG, PDF. Maximum size: 10MB.' d='Modules.Pskyc.Shop'}
          </small>
          <div class="file-preview" id="address-preview"></div>
        </div>
      </div>

      <div class="form-section">
        <h3><i class="material-icons">description</i> {l s='Additional Information' d='Modules.Pskyc.Shop'}</h3>

        <div class="form-group">
          <label for="additional_notes" class="form-control-label">
            {l s='Additional Notes (Optional)' d='Modules.Pskyc.Shop'}
          </label>
          <textarea name="additional_notes" id="additional_notes" class="form-control" rows="3"
            placeholder="{l s='Any additional information you\'d like to provide...' d='Modules.Pskyc.Shop'}"></textarea>
        </div>
      </div>

      <div class="form-section">
        <div class="form-group">
          <div class="checkbox">
            <label class="required">
              <input type="checkbox" id="data_consent" name="data_consent" required>
              {l s='I consent to the processing of my personal data for identity verification purposes.' d='Modules.Pskyc.Shop'}
            </label>
          </div>
        </div>

        <div class="form-group">
          <div class="checkbox">
            <label class="required">
              <input type="checkbox" id="document_authenticity" name="document_authenticity" required>
              {l s='I confirm that all uploaded documents are authentic and belong to me.' d='Modules.Pskyc.Shop'}
            </label>
          </div>
        </div>
      </div>
      <div class="security-info">
        <i class="material-icons">security</i>
        <div>
          <strong>{l s='Security & Privacy' d='Modules.Pskyc.Shop'}</strong>
          <p>
            {l s='Your documents are encrypted and stored securely. We comply with GDPR and will only use your data for verification purposes.' d='Modules.Pskyc.Shop'}
          </p>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg" id="submit-verification">
          <i class="material-icons">cloud_upload</i>
          {l s='Submit for Verification' d='Modules.Pskyc.Shop'}
        </button>
        <div class="upload-progress" id="upload-progress" style="display: none;">
          <div class="progress">
            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
          </div>
          <p class="progress-text">{l s='Uploading documents...' d='Modules.Pskyc.Shop'}</p>
        </div>
      </div>
    </form>
  {else}
    <div class="alert alert-success">
      <h4><i class="material-icons">check_circle</i> {l s='Verification Complete' d='Modules.Pskyc.Shop'}</h4>
      <p>
        {l s='Your identity has been successfully verified. You can now access all features of our platform.' d='Modules.Pskyc.Shop'}
      </p>
    </div>
  {/if}

  {* Existing Documents *}
  {if isset($documents) && $documents}
    <div class="existing-documents">
      <h3><i class="material-icons">folder</i> {l s='Uploaded Documents' d='Modules.Pskyc.Shop'}</h3>
      <div class="documents-list">
        {foreach from=$documents item=document}
          <div class="document-item">
            <div class="document-info">
              <span class="document-type">{$document.type|escape:'html':'UTF-8'}</span>
              <span class="document-name">{$document.filename|escape:'html':'UTF-8'}</span>
              <span class="document-size">{($document.filesize/1024)|string_format:"%.1f"} KB</span>
              <span class="document-date">{$document.date_uploaded|date_format:'%B %e, %Y'}</span>
            </div>
            <div class="document-actions">
              <i class="material-icons document-icon">
                {if $document.mime|strpos:'image' !== false}image{elseif $document.mime|strpos:'pdf' !== false}picture_as_pdf{else}description{/if}
              </i>
            </div>
          </div>
        {/foreach}
      </div>
    </div>
  {/if}
</div>

<style>
  .kyc-verification-form {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
  }

  .form-section {
    margin-bottom: 30px;
    padding: 20px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background: #fff;
  }

  .form-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .help-text {
    color: #666;
    margin-bottom: 20px;
    font-size: 14px;
  }

  .status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
  }

  .status-pending {
    background: #ffeaa7;
    color: #2d3436;
  }

  .status-under_review {
    background: #74b9ff;
    color: #fff;
  }

  .status-approved {
    background: #00b894;
    color: #fff;
  }

  .status-rejected {
    background: #e17055;
    color: #fff;
  }

  .status-requested_more_info {
    background: #fdcb6e;
    color: #2d3436;
  }

  .status-expired {
    background: #636e72;
    color: #fff;
  }

  .file-preview {
    margin-top: 10px;
    padding: 10px;
    border: 2px dashed #ddd;
    border-radius: 4px;
    text-align: center;
    display: none;
  }

  .file-preview.active {
    display: block;
    border-color: #007bff;
  }

  .security-info {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid #007bff;
  }

  .security-info i {
    color: #007bff;
    margin-top: 2px;
  }

  .form-actions {
    text-align: center;
    margin-top: 30px;
  }

  .upload-progress {
    margin-top: 20px;
  }

  .progress {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
  }

  .progress-bar {
    height: 100%;
    background: #007bff;
    transition: width 0.3s ease;
  }

  .documents-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .document-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    background: #f8f9fa;
  }

  .document-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
  }

  .document-type {
    font-weight: bold;
    color: #007bff;
    text-transform: capitalize;
  }

  .document-name {
    font-size: 14px;
    color: #333;
  }

  .document-size,
  .document-date {
    font-size: 12px;
    color: #666;
  }

  .document-icon {
    color: #666;
    font-size: 24px;
  }

  .required::after {
    content: " *";
    color: #e74c3c;
  }

  .admin-note {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    border-left: 4px solid #17a2b8;
    margin-top: 5px;
  }

  @media (max-width: 768px) {
    .kyc-verification-form {
      padding: 10px;
    }

    .form-section {
      padding: 15px;
    }

    .document-item {
      flex-direction: column;
      align-items: flex-start;
      gap: 10px;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // File preview functionality
    function setupFilePreview(inputId, previewId) {
      const input = document.getElementById(inputId);
      const preview = document.getElementById(previewId);

      if (input && preview) {
        input.addEventListener('change', function(e) {
          const file = e.target.files[0];
          if (file) {
            preview.classList.add('active');

            let content = '<strong>' + file.name + '</strong><br>';
            content += 'Size: ' + (file.size / 1024 / 1024).toFixed(2) + ' MB<br>';
            content += 'Type: ' + file.type;

            if (file.type.startsWith('image/')) {
              const reader = new FileReader();
              reader.onload = function(e) {
                content += '<br><img src="' + e.target.result +
                  '" style="max-width: 200px; max-height: 150px; margin-top: 10px; border-radius: 4px;">';
                preview.innerHTML = content;
              };
              reader.readAsDataURL(file);
            } else {
              preview.innerHTML = content;
            }
          } else {
            preview.classList.remove('active');
          }
        });
      }
    }

    setupFilePreview('id_document', 'id-preview');
    setupFilePreview('id_document_front', 'id-front-preview');
    setupFilePreview('id_document_back', 'id-back-preview');
    setupFilePreview('address_document', 'address-preview');

    // Document type change event
    const idDocumentType = document.getElementById('id_document_type');
    const idSingleUpload = document.getElementById('id-single-upload');
    const idFrontBackUpload = document.getElementById('id-front-back-upload');

    if (idDocumentType) {
      idDocumentType.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const requiresBothSides = selectedOption.getAttribute('data-requires-both-sides');

        if (requiresBothSides === 'true') {
          idSingleUpload.style.display = 'none';
          idFrontBackUpload.style.display = 'block';
          // Clear single upload and make front/back required
          document.getElementById('id_document').removeAttribute('required');
          document.getElementById('id_document_front').setAttribute('required', 'required');
          document.getElementById('id_document_back').setAttribute('required', 'required');
        } else {
          idSingleUpload.style.display = 'block';
          idFrontBackUpload.style.display = 'none';
          // Clear front/back uploads and make single required
          document.getElementById('id_document').setAttribute('required', 'required');
          document.getElementById('id_document_front').removeAttribute('required');
          document.getElementById('id_document_back').removeAttribute('required');
        }
      });
    }

    // Form validation
    const form = document.getElementById('kyc-upload-form');
    if (form) {
      form.addEventListener('submit', function(e) {
        const idDocType = document.getElementById('id_document_type').value;
        const selectedOption = document.querySelector('#id_document_type option[value="' + idDocType + '"]');
        const requiresBothSides = selectedOption ? selectedOption.getAttribute('data-requires-both-sides') : false;
        
        const addressDoc = document.getElementById('address_document');
        const consent = document.getElementById('data_consent');
        const authenticity = document.getElementById('document_authenticity');

        let valid = true;
        let errors = [];

        // Validate identity documents based on type
        if (requiresBothSides === 'true') {
          const frontDoc = document.getElementById('id_document_front');
          const backDoc = document.getElementById('id_document_back');
          
          if (!frontDoc.files[0]) {
            errors.push('{l s="Please upload the front side of your identity document" d="Modules.Pskyc.Shop"}');
            valid = false;
          }
          if (!backDoc.files[0]) {
            errors.push('{l s="Please upload the back side of your identity document" d="Modules.Pskyc.Shop"}');
            valid = false;
          }

          // Check file sizes (10MB limit)
          if (frontDoc.files[0] && frontDoc.files[0].size > 10 * 1024 * 1024) {
            errors.push('{l s="Front side document must be smaller than 10MB" d="Modules.Pskyc.Shop"}');
            valid = false;
          }
          if (backDoc.files[0] && backDoc.files[0].size > 10 * 1024 * 1024) {
            errors.push('{l s="Back side document must be smaller than 10MB" d="Modules.Pskyc.Shop"}');
            valid = false;
          }
        } else {
          const idDoc = document.getElementById('id_document');
          
          if (!idDoc.files[0]) {
            errors.push('{l s="Please upload your identity document" d="Modules.Pskyc.Shop"}');
            valid = false;
          }

          // Check file sizes (10MB limit)
          if (idDoc.files[0] && idDoc.files[0].size > 10 * 1024 * 1024) {
            errors.push('{l s="Identity document must be smaller than 10MB" d="Modules.Pskyc.Shop"}');
            valid = false;
          }
        }

        // Check address document
        if (!addressDoc.files[0]) {
          errors.push('{l s="Please upload your proof of address document" d="Modules.Pskyc.Shop"}');
          valid = false;
        }

        if (addressDoc.files[0] && addressDoc.files[0].size > 10 * 1024 * 1024) {
          errors.push('{l s="Address document must be smaller than 10MB" d="Modules.Pskyc.Shop"}');
          valid = false;
        }

        if (!consent.checked || !authenticity.checked) {
          errors.push('{l s="Please accept all required terms and conditions" d="Modules.Pskyc.Shop"}');
          valid = false;
        }

        if (!valid) {
          e.preventDefault();
          alert(errors.join('\n'));
          return false;
        }

        // Show progress
        const progress = document.getElementById('upload-progress');
        const submitBtn = document.getElementById('submit-verification');

        if (progress && submitBtn) {
          progress.style.display = 'block';
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="material-icons">hourglass_empty</i> {l s="Processing..." d="Modules.Pskyc.Shop"}';
        }
      });
    }
  });
</script>