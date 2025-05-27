# KYC Secure Upload (PrestaShop Module)

Open source, GDPR-compliant KYC document verification for PrestaShop.

- Proof of identity (ID)
- Proof of address (utility bill, bank statement, etc.)
- Secure file upload

## Features

- [x] Secure KYC file uploads (ID, proof of address)
- [x] End-to-end file encryption (OpenSSL)
- [x] Front-office upload & status tracking
- [ ] Back-office admin panel: validation, logs, messaging
- [ ] Automated email notifications (status updates, rejections, deletions)
- [ ] Order blocking for sensitive products until KYC is validated
- [ ] Easy installation, configuration, and uninstall
- [ ] Multi-language ready

## Installation

1. Download the latest release ZIP
2. Upload to your PrestaShop back office (Modules > Module Manager > Upload)
3. Configure in the modules section

## Security

- Files are stored encrypted with OpenSSL (`AES-256-CBC`)
- Keys and IV managed securely
- .htaccess restricts direct access
- GDPR retention and deletion supported

## Contribution

PRs welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.