<?php

namespace Tests\Fixtures;

/**
 * Test data fixtures for KYC module tests
 * 
 * Provides consistent test data across all test cases
 */
class KycFixtures
{
    /**
     * Sample verification statuses and their transitions
     */
    public const VERIFICATION_STATUSES = [
        'pending' => 'Pending Review',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'expired' => 'Expired'
    ];

    /**
     * Valid status transitions
     */
    public const VALID_TRANSITIONS = [
        'pending' => ['under_review', 'rejected'],
        'under_review' => ['approved', 'rejected'],
        'approved' => ['expired'],
        'rejected' => [],
        'expired' => []
    ];

    /**
     * Document types and their configurations
     */
    public const DOCUMENT_TYPES = [
        'passport' => [
            'name' => 'Passport',
            'requires_both_sides' => false,
            'accepted_formats' => ['pdf', 'jpg', 'png']
        ],
        'id_card' => [
            'name' => 'National ID Card',
            'requires_both_sides' => true,
            'accepted_formats' => ['jpg', 'png']
        ],
        'driving_license' => [
            'name' => 'Driving License',
            'requires_both_sides' => true,
            'accepted_formats' => ['jpg', 'png']
        ],
        'utility_bill' => [
            'name' => 'Utility Bill',
            'requires_both_sides' => false,
            'accepted_formats' => ['pdf', 'jpg', 'png']
        ]
    ];

    /**
     * Sample file contents for testing
     */
    public const SAMPLE_FILE_CONTENTS = [
        'pdf' => '%PDF-1.4 Sample PDF content for testing',
        'jpg' => 'Sample JPEG content for testing',
        'png' => 'Sample PNG content for testing'
    ];

    /**
     * Sample customer data
     */
    public static function getCustomerData(array $overrides = []): array
    {
        return array_merge([
            'id_customer' => 123,
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'id_lang' => 1,
            'active' => 1,
            'date_add' => '2024-01-01 10:00:00',
            'date_upd' => '2024-01-01 10:00:00'
        ], $overrides);
    }

    /**
     * Sample verification data
     */
    public static function getVerificationData(array $overrides = []): array
    {
        return array_merge([
            'id_kyc_verification' => 1,
            'id_customer' => 123,
            'status' => 'pending',
            'date_submitted' => '2024-01-01 10:00:00',
            'date_validated' => null,
            'date_expiry' => null,
            'admin_note' => null,
            'employee_id' => null
        ], $overrides);
    }

    /**
     * Sample document data
     */
    public static function getDocumentData(array $overrides = []): array
    {
        return array_merge([
            'id_kyc_document' => 1,
            'verification_id' => 1,
            'type' => 'passport',
            'side' => null,
            'filename' => 'passport.pdf',
            'filesize' => 1024,
            'mime' => 'application/pdf',
            'sha256' => hash('sha256', 'test content'),
            'encrypted' => 1,
            'iv' => base64_encode(random_bytes(16)),
            'date_uploaded' => '2024-01-01 10:00:00',
            'expires_at' => '2025-01-01 10:00:00'
        ], $overrides);
    }

    /**
     * Sample log data
     */
    public static function getLogData(array $overrides = []): array
    {
        return array_merge([
            'id_kyc_log' => 1,
            'verification_id' => 1,
            'action' => 'status_change',
            'old_value' => 'pending',
            'new_value' => 'approved',
            'employee_id' => 1,
            'date_add' => '2024-01-01 10:00:00',
            'details' => 'Status updated by admin'
        ], $overrides);
    }

    /**
     * Create test file upload array
     */
    public static function createFileUpload(
        string $filename = 'test.pdf',
        int $size = 1024,
        string $mimeType = 'application/pdf',
        int $error = UPLOAD_ERR_OK
    ): array {
        $tmpName = tempnam(sys_get_temp_dir(), 'test_upload_');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $content = self::SAMPLE_FILE_CONTENTS[$extension] ?? str_repeat('A', $size);
        
        file_put_contents($tmpName, $content);
        
        return [
            'name' => $filename,
            'type' => $mimeType,
            'tmp_name' => $tmpName,
            'error' => $error,
            'size' => $size
        ];
    }

    /**
     * Create multiple test files for different document types
     */
    public static function createDocumentSet(): array
    {
        return [
            'passport' => self::createFileUpload('passport.pdf', 2048, 'application/pdf'),
            'id_card_front' => self::createFileUpload('id_front.jpg', 1536, 'image/jpeg'),
            'id_card_back' => self::createFileUpload('id_back.jpg', 1536, 'image/jpeg'),
            'utility_bill' => self::createFileUpload('bill.pdf', 1024, 'application/pdf')
        ];
    }

    /**
     * Get email template variables for testing
     */
    public static function getEmailTemplateVars(string $type = 'status_change'): array
    {
        $base = [
            'customer_name' => 'John Doe',
            'shop_name' => 'Test Shop',
            'shop_url' => 'https://test-shop.com',
            'verification_id' => 1,
            'date_submitted' => '2024-01-01 10:00:00'
        ];

        switch ($type) {
            case 'status_change':
                return array_merge($base, [
                    'status' => 'approved',
                    'previous_status' => 'pending',
                    'admin_note' => 'Approved after review'
                ]);
            
            case 'upload_confirmation':
                return array_merge($base, [
                    'document_count' => 2,
                    'documents' => [
                        ['type' => 'passport', 'filename' => 'passport.pdf'],
                        ['type' => 'utility_bill', 'filename' => 'bill.pdf']
                    ]
                ]);
            
            case 'admin_notification':
                return array_merge($base, [
                    'customer_email' => 'john.doe@example.com',
                    'customer_id' => 123,
                    'admin_url' => 'https://test-shop.com/admin/kyc'
                ]);
            
            default:
                return $base;
        }
    }

    /**
     * Cleanup test files
     */
    public static function cleanupTestFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_array($file) && isset($file['tmp_name'])) {
                if (file_exists($file['tmp_name'])) {
                    unlink($file['tmp_name']);
                }
            } elseif (is_string($file) && file_exists($file)) {
                unlink($file);
            }
        }
    }
}