# Makefile for PrestaShop KYC Module Testing
# Provides convenient commands for running tests, coverage, and code quality checks

.PHONY: help install test test-unit test-integration coverage coverage-html phpstan cs-check cs-fix clean

# Default target
help:
	@echo "Available commands:"
	@echo "  make install       - Install dependencies"
	@echo "  make test          - Run all tests"
	@echo "  make test-unit     - Run unit tests only"
	@echo "  make test-integration - Run integration tests only"
	@echo "  make coverage      - Run tests with coverage report"
	@echo "  make coverage-html - Generate HTML coverage report"
	@echo "  make phpstan       - Run static analysis"
	@echo "  make cs-check      - Check coding standards"
	@echo "  make cs-fix        - Fix coding standards"
	@echo "  make clean         - Clean generated files"

# Install dependencies
install:
	composer install --no-interaction --prefer-dist

# Run all tests
test:
	vendor/bin/phpunit

# Run unit tests only
test-unit:
	vendor/bin/phpunit --testsuite Unit

# Run integration tests only
test-integration:
	vendor/bin/phpunit --testsuite Integration

# Run tests with text coverage
coverage:
	vendor/bin/phpunit --coverage-text

# Generate HTML coverage report
coverage-html:
	vendor/bin/phpunit --coverage-html coverage/html
	@echo "Coverage report generated in coverage/html/index.html"

# Run static analysis
phpstan:
	vendor/bin/phpstan analyse src/ --level=5

# Check coding standards
cs-check:
	vendor/bin/phpcs --standard=PSR12 src/

# Fix coding standards
cs-fix:
	vendor/bin/phpcbf --standard=PSR12 src/

# Clean generated files
clean:
	rm -rf coverage/
	rm -rf .phpunit.cache/
	rm -f .phpunit.result.cache