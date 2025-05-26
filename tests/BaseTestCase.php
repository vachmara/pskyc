<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Base test case for PrestaShop KYC module tests
 * 
 * Provides common functionality and setup for all test classes
 */
abstract class BaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset Configuration mock state
        if (class_exists('Configuration')) {
            \Configuration::reset();
        }
        
        // Set common test configuration
        \Configuration::updateValue('PS_SHOP_EMAIL', 'test@example.com');
        \Configuration::updateValue('PS_SHOP_NAME', 'Test Shop');
        \Configuration::updateValue('PS_LANG_DEFAULT', 1);
        \Configuration::updateValue('PSKYC_ENCRYPTION_KEY', bin2hex(random_bytes(32)));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock file upload array for testing
     * 
     * @param string $filename Original filename
     * @param int $size File size in bytes
     * @param string $mimeType MIME type
     * @param int $error Upload error code
     * @return array
     */
    protected function createMockFileUpload(
        string $filename = 'test.pdf',
        int $size = 1024,
        string $mimeType = 'application/pdf',
        int $error = UPLOAD_ERR_OK
    ): array {
        $tmpName = tempnam(sys_get_temp_dir(), 'test_upload_');
        file_put_contents($tmpName, str_repeat('A', $size));
        
        return [
            'name' => $filename,
            'type' => $mimeType,
            'tmp_name' => $tmpName,
            'error' => $error,
            'size' => $size
        ];
    }

    /**
     * Create a mock verification record
     * 
     * @param array $overrides Override default values
     * @return array
     */
    protected function createMockVerification(array $overrides = []): array
    {
        return array_merge([
            'id_kyc_verification' => 1,
            'id_customer' => 123,
            'status' => 'pending',
            'date_submitted' => '2024-01-01 10:00:00',
            'date_validated' => null,
            'date_expiry' => null,
            'admin_note' => null
        ], $overrides);
    }

    /**
     * Create a mock customer record
     * 
     * @param array $overrides Override default values
     * @return array
     */
    protected function createMockCustomer(array $overrides = []): array
    {
        return array_merge([
            'id_customer' => 123,
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_lang' => 1
        ], $overrides);
    }

    /**
     * Create a mock document record
     * 
     * @param array $overrides Override default values
     * @return array
     */
    protected function createMockDocument(array $overrides = []): array
    {
        return array_merge([
            'id_kyc_document' => 1,
            'verification_id' => 1,
            'type' => 'passport',
            'side' => null,
            'filename' => 'test.pdf',
            'filesize' => 1024,
            'mime' => 'application/pdf',
            'sha256' => hash('sha256', 'test content'),
            'encrypted' => 1,
            'iv' => base64_encode(random_bytes(16)),
            'date_uploaded' => '2024-01-01 10:00:00',
            'expires_at' => '2025-01-01 10:00:00'
        ], $overrides);
    }
}