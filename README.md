# KYC Secure Upload (PrestaShop Module)

Open source, GDPR-compliant KYC document verification for PrestaShop.

- Proof of identity (ID)
- Proof of address (utility bill, bank statement, etc.)
- Secure file storage with encryption
- Product order blocking until KYC is validated

## Features

- [x] Secure KYC file uploads (ID, proof of address)
- [x] End-to-end file encryption (OpenSSL)
- [x] Front-office upload & status tracking
- [x] Back-office admin panel: validation, document checking, logs
- [x] Order blocking for sensitive products until KYC is validated
- [x] Easy installation, configuration, and uninstall
- [x] Multi-language ready
- [x] Automated email notifications (status updates, document confirmations, admin alerts)

## Installation

1. Download the latest release ZIP
2. Upload to your PrestaShop back office (Modules > Module Manager > Upload)
3. Configure in the modules section

## Security

- Files are stored encrypted with OpenSSL (`AES-256-CBC`)
- Keys and IV managed securely
- .htaccess restricts direct access
- GDPR retention and deletion supported

## Email Flow Diagram

```mermaid
flowchart TD
    A[Customer Uploads Documents] --> B[Create Verification Record]
    B --> C[Upload Documents to Encrypted Storage]
    C --> D{All Documents Uploaded Successfully?}
    
    D -->|Yes| E[Send Upload Confirmation to Customer]
    D -->|No| F[Show Upload Errors]
    
    E --> G[Send Admin Notification]
    G --> H[Admin Reviews Documents]
    
    H --> I{Admin Decision}
    I -->|Approve| J[Update Status to 'approved']
    I -->|Reject| K[Update Status to 'rejected']
    I -->|Request More Info| L[Update Status to 'requested_more_info']
    I -->|Under Review| M[Update Status to 'under_review']
    
    J --> N[Send Approval Email to Customer]
    K --> O[Send Rejection Email to Customer]
    L --> P[Send Request More Info Email to Customer]
    M --> Q[Send Under Review Email to Customer]
    
    J --> R[Set Expiry Date]
    R --> S[Schedule Expiry Warning Check]
    
    S --> T{30 Days Before Expiry?}
    T -->|Yes| U[Send Expiry Warning to Customer]
    T -->|No| V[Continue Monitoring]
    
    U --> W{Documents Expired?}
    W -->|Yes| X[Auto-Update Status to 'expired']
    W -->|No| V
    
    X --> Y[Send Expiry Notification to Customer]

    style A fill:#e1f5fe
    style E fill:#c8e6c9
    style G fill:#fff3e0
    style N fill:#c8e6c9
    style O fill:#ffcdd2
    style P fill:#fff9c4
    style Q fill:#e3f2fd
    style U fill:#fff3e0
    style Y fill:#ffcdd2
```

## Email Types Summary

| Email Type | Trigger | Recipient | Template | Purpose |
|------------|---------|-----------|----------|---------|
| **Upload Confirmation** | Document upload success | Customer | `document_upload_confirmation` | Confirm receipt |
| **Admin Notification** | Document upload success | Admins | `admin_new_verification` | Alert for review |
| **Status Change** | Admin updates status | Customer | `verification_status` | Notify of decision |
| **Expiry Warning** | 30 days before expiry | Customer | `verification_expiry_warning` | Renewal reminder | 


## Email Templates

Email templates are located in:
- Text versions: `mails/{lang}/`
- HTML versions: `mails/layouts/`

Available languages: `en`, `fr`

## Contribution

PRs welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.

