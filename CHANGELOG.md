# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.2] - 2025-09-17

### Fixed
- Fix remove admin note in status mail notification
- Escape KYC form validation messages and loading labels for safe front-office JavaScript handling when two-sided IDs are required
- Only compatible with PrestaShop 8.1 and 8.2 (not 8.0 anymore)
- Update GitHub Actions and docker-compose to use PrestaShop 8.1 and 8.2

## [1.1.1] - 2025-06-05

### Fixed
- Fix emails templating for KYC notifications

## [1.1.0] - 2025-06-05

### Added

- Logo image for the module
- French translation for the module
- Display customer note back office
- Display a redirection to the customer profile in the back office
- Front controller cron tests
- Improve the README, codecov, and CI badges

### Fixed

- .htaccess too complex and blocking logo.png
- isUsingNewTranslationSystem method to use the correct translation system
- KYC checkout step not shown for products with non-default categories
- fallback emails for KYC notifications / status (add html and text versions)
- Cron endpoint for KYC document cleanup and expiration emails

## [1.0.0] - 2025-06-01

### Added
- KYC document verification system for PrestaShop 8
- Secure encrypted document storage with AES-256-CBC encryption
- Admin management interface with Symfony controllers
- Customer front-office upload interface
- Checkout integration for category-based KYC requirements
- GDPR compliance hooks and data export/deletion
- Email notifications system with multiple templates
- Automated file retention and cleanup system
- Multi-language support
- Comprehensive security measures (.htaccess protection, file validation)
- PHPUnit test suite with 90%+ coverage
- CI/CD pipeline with PHP linting, PHPStan, and automated testing

### Security
- Files stored outside web root with .htaccess protection
- Document encryption with secure key management
- Input validation and sanitization
- CSRF protection on forms
- Directory listing prevention

### Documentation
- Comprehensive inline documentation
- Installation and configuration guides
- GDPR compliance documentation
- Development setup instructions

### Beta Notes
- This is a beta release for testing and feedback
- All core functionality is implemented and tested
- Ready for PrestaShop 8.0+ environments
- Please report any issues on GitHub

[Unreleased]: https://github.com/vachmara/pskyc/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/vachmara/pskyc/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/vachmara/pskyc/releases/tag/v1.0.0
