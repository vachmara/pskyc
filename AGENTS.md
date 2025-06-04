# KYC Secure Upload - AI Agent Guide

Welcome to the KYC Secure Upload project! This document provides essential information for AI agents and developers contributing to this PrestaShop 8 module.

## 📋 Project Overview

**KYC Secure Upload** is an open-source PrestaShop module that provides secure Know Your Customer (KYC) document verification functionality. The module enables merchants to collect and verify customer identity documents before allowing purchases of specific products or categories.

### Key Features
- 🔐 **Secure Document Storage**: AES-256-CBC encryption with files stored outside web root
- 📋 **KYC Verification System**: Identity and address proof validation workflow
- 🛒 **Checkout Integration**: Block orders for KYC-required categories until verification
- 🔧 **Admin Management**: Symfony-based admin interface for document review
- 📧 **Email Notifications**: Automated notifications for all verification stages
- 🌍 **Multi-language Support**: English and French translations included
- 🛡️ **GDPR Compliance**: Data export, deletion, and retention management
- 🧹 **Automated Cleanup**: Configurable document retention periods

### Technical Stack
- **PrestaShop**: 8.0+ compatibility
- **PHP**: 8.1+ 
- **Framework**: Symfony components, Twig templating
- **Database**: MySQL/MariaDB
- **Security**: OpenSSL encryption, CSRF protection
- **Testing**: PHPUnit with 90%+ coverage
- **CI/CD**: GitHub Actions with PHPStan, PHP-CS-Fixer

## 🏗️ Architecture

```
pskyc/
├── src/                    # Modern PHP classes (PSR-4)
│   ├── Controller/Admin/   # Symfony admin controllers
│   ├── Entity/            # Data models (Document, Verification, Log)
│   ├── Repository/        # Database access layer
│   ├── Service/           # Business logic services
│   └── Grid/              # Admin list/filter definitions
├── controllers/front/     # Legacy front controllers
├── views/templates/       # Smarty/Twig templates
├── config/               # Symfony services and routes
├── mails/                # Email templates (HTML/text)
├── secure_upload/        # Encrypted document storage
└── tests/                # PHPUnit test suite
```

## 🤝 How to Contribute

We welcome contributions from AI agents and human developers! Please follow these guidelines:

### 1. **Issue Linking**
- Every pull request should link to an existing issue
- Use the format: `Closes #[issue-number]` in your PR description
- If no issue exists, create one first to discuss the change

### 2. **Conventional Commits**
All PR titles must follow [Conventional Commits](https://conventionalcommits.org) format:

```
<type>[optional scope]: <description>

Examples:
feat: add customer email verification step
fix(admin): resolve document upload validation error
docs: update installation instructions
refactor(service): optimize document encryption performance
```

**Types:**
- `feat`: New features
- `fix`: Bug fixes
- `docs`: Documentation changes
- `style`: Code formatting (no logic changes)
- `refactor`: Code restructuring (no feature changes)
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

### 3. **Pull Request Process**

1. **Use the PR Template**: We have a comprehensive PR template in `.github/pull_request_template.md`
2. **Fill all sections**:
   - Link the related issue
   - Select type of change
   - Provide detailed description
   - Include testing steps
   - Complete the checklist

3. **Quality Standards**:
   - [ ] Follow PSR-12 and PrestaShop coding standards
   - [ ] Write/update PHPDoc comments in English
   - [ ] Add tests for new functionality
   - [ ] Update documentation if needed
   - [ ] Ensure CI/CD checks pass (PHPStan, tests, linting)

### 4. **Development Workflow**

```bash
# 1. Fork and clone the repository
git clone https://github.com/your-username/pskyc.git
cd pskyc

# 2. Create a feature branch (use descriptive names)
git checkout -b feat/customer-document-preview
# or
git checkout -b fix/admin-grid-filtering

# 3. Install dependencies
composer install

# 4. Make your changes following coding standards

# 5. Run tests and quality checks
composer lint          # Run PHP-CS-Fixer
composer test          # Run PHPUnit tests
composer test:coverage  # Check test coverage

# 6. Commit with conventional format
git commit -m "feat: add document preview in customer account"

# 7. Push and create PR
git push origin feat/customer-document-preview
```

### 5. **Code Standards**

- **PHP**: Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) and [PrestaShop best practices](https://devdocs.prestashop-project.org/8/modules/creation/good-practices/)
- **JavaScript**: ES6+ with proper error handling
- **Templates**: Use Twig for new admin templates, Smarty for front-office
- **Database**: Use PrestaShop's DB abstraction, no raw SQL
- **Security**: Always validate/sanitize input, use CSRF tokens
- **Documentation**: Write clear PHPDoc comments in English

### 6. **Testing Requirements**

- **Unit Tests**: All new services and repositories must have tests
- **Integration Tests**: Test admin controllers and front workflows
- **Coverage**: Maintain 90%+ test coverage
- **Manual Testing**: Test in PrestaShop 8.0+ environment

## 📝 Changelog Management

We follow [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format in `CHANGELOG.md`:

### Sections:
- **Added**: New features
- **Changed**: Changes in existing functionality
- **Deprecated**: Soon-to-be removed features
- **Removed**: Removed features
- **Fixed**: Bug fixes
- **Security**: Security improvements

### Format:
```markdown
## [Unreleased]

### Added
- New customer email verification step

### Fixed
- Admin grid filtering for document status

### Security
- Enhanced file upload validation
```

## 🎯 Common Contribution Areas

### High Priority
- 🐛 **Bug Fixes**: Check GitHub issues labeled `bug`
- 🔒 **Security**: Vulnerability reports and security enhancements
- 🌍 **Translations**: Add support for new languages
- ♿ **Accessibility**: Improve admin and front-office accessibility

### Medium Priority
- 📱 **Mobile**: Improve responsive design
- ⚡ **Performance**: Database query optimization
- 🧪 **Testing**: Increase test coverage
- 📚 **Documentation**: Improve code documentation

### Enhancement Ideas
- 📊 **Reporting**: KYC verification statistics
- 🔗 **Integrations**: Third-party verification services
- 📋 **Templates**: Additional email templates
- 🎨 **UI/UX**: Admin interface improvements

## 🚀 Quick Start for AI Agents

1. **Understand the codebase**: Review `src/` directory structure
2. **Check existing issues**: Look for `good first issue` labels
3. **Follow the patterns**: Study existing controllers, services, and tests
4. **Use the tools**: Leverage Docker setup for testing
5. **Ask questions**: Open discussions for clarification

## 📚 Resources

- [PrestaShop 8 Module Documentation](https://devdocs.prestashop-project.org/8/modules/)
- [Project Contributing Guidelines](CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)
- [License](LICENSE.md)
---

**Ready to contribute?** 
1. Check [open issues](https://github.com/vachmara/pskyc/issues)
2. Follow our [PR template](.github/pull_request_template.md)
3. Use conventional commits for PR titles
4. Link your issue in the PR description

Thank you for helping make KYC verification better for the PrestaShop community! 🙏