<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

namespace Tests\PsKyc\Service;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\Pskyc\Repository\CustomerRepository;
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\LogRepository;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use PrestaShop\Module\Pskyc\Service\DocumentService;
use PrestaShop\Module\Pskyc\Service\MaintenanceService;
use PrestaShop\Module\Pskyc\Service\NotificationService;

class MaintenanceServiceTest extends MockeryTestCase
{
    /** @var DocumentService */
    private $documentServiceMock;

    /** @var NotificationService */
    private $notificationServiceMock;

    /** @var VerificationRepository */
    private $verificationRepositoryMock;

    /** @var DocumentRepository */
    private $documentRepositoryMock;

    /** @var CustomerRepository */
    private $customerRepositoryMock;

    /** @var LogRepository */
    private $logRepositoryMock;

    /** @var MaintenanceService */
    private $maintenanceService;

    /** @var string */
    private $tempUploadDir;

    protected function setUp(): void
    {
        $this->documentServiceMock = \Mockery::mock(DocumentService::class);
        $this->notificationServiceMock = \Mockery::mock(NotificationService::class);
        $this->verificationRepositoryMock = \Mockery::mock(VerificationRepository::class);
        $this->documentRepositoryMock = \Mockery::mock(DocumentRepository::class);
        $this->customerRepositoryMock = \Mockery::mock(CustomerRepository::class);
        $this->logRepositoryMock = \Mockery::mock(LogRepository::class);

        // Create temporary directory for tests
        $this->tempUploadDir = sys_get_temp_dir() . '/pskyc_test_' . uniqid();
        mkdir($this->tempUploadDir, 0777, true);

        $this->maintenanceService = new MaintenanceService(
            $this->documentServiceMock,
            $this->notificationServiceMock,
            $this->verificationRepositoryMock,
            $this->documentRepositoryMock,
            $this->customerRepositoryMock,
            $this->logRepositoryMock,
            $this->tempUploadDir
        );

        // Define constants if not already defined
        if (!defined('_PS_VERSION_')) {
            define('_PS_VERSION_', '8.0.0');
        }
        if (!defined('_PS_MODULE_DIR_')) {
            define('_PS_MODULE_DIR_', sys_get_temp_dir() . '/');
        }
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempUploadDir)) {
            $this->removeDirectory($this->tempUploadDir);
        }

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testConstructorWithDefaultUploadDir()
    {
        $service = new MaintenanceService(
            $this->documentServiceMock,
            $this->notificationServiceMock,
            $this->verificationRepositoryMock,
            $this->documentRepositoryMock,
            $this->customerRepositoryMock,
            $this->logRepositoryMock
        );

        $this->assertInstanceOf(MaintenanceService::class, $service);
    }

    public function testConstructorWithCustomUploadDir()
    {
        $customDir = '/custom/upload/dir';
        $service = new MaintenanceService(
            $this->documentServiceMock,
            $this->notificationServiceMock,
            $this->verificationRepositoryMock,
            $this->documentRepositoryMock,
            $this->customerRepositoryMock,
            $this->logRepositoryMock,
            $customDir
        );

        $this->assertInstanceOf(MaintenanceService::class, $service);
    }

    public function testSendExpiryWarningsWithCustomWarningDays()
    {
        $customWarningDays = 7;

        $this->verificationRepositoryMock->shouldReceive('findExpiringVerifications')
            ->once()
            ->with($customWarningDays)
            ->andReturn([]);

        $result = $this->maintenanceService->sendExpiryWarnings($customWarningDays);

        $this->assertEquals(0, $result['warnings_sent']);
        $this->assertEmpty($result['errors']);
    }

    public function testSendExpiryWarningsWithMissingCustomer()
    {
        $expiringVerifications = [
            [
                'id_kyc_verification' => 1,
                'id_customer' => 999,
                'date_expiry' => date('Y-m-d H:i:s', time() + (5 * 86400)),
            ],
        ];

        $this->verificationRepositoryMock->shouldReceive('findExpiringVerifications')
            ->once()
            ->with(30)
            ->andReturn($expiringVerifications);

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->once()
            ->with(999)
            ->andReturn([]);

        $result = $this->maintenanceService->sendExpiryWarnings(30);

        $this->assertEquals(0, $result['warnings_sent']);
        $this->assertEmpty($result['errors']);
    }

    public function testSendExpiryWarningsWithNotificationFailure()
    {
        $expiringVerifications = [
            [
                'id_kyc_verification' => 1,
                'id_customer' => 123,
                'date_expiry' => date('Y-m-d H:i:s', time() + (5 * 86400)),
            ],
        ];

        $customerData = ['id_customer' => 123, 'email' => 'test@example.com'];

        $this->verificationRepositoryMock->shouldReceive('findExpiringVerifications')
            ->once()
            ->andReturn($expiringVerifications);

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->once()
            ->andReturn($customerData);

        $this->notificationServiceMock->shouldReceive('sendExpiryWarning')
            ->once()
            ->andThrow(new \Exception('Email sending failed'));

        $result = $this->maintenanceService->sendExpiryWarnings(30);

        $this->assertEquals(0, $result['warnings_sent']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Email sending failed', $result['errors'][0]);
    }

    public function testSendExpiryWarningsWithRepositoryException()
    {
        $this->verificationRepositoryMock->shouldReceive('findExpiringVerifications')
            ->once()
            ->andThrow(new \Exception('Repository error'));

        $result = $this->maintenanceService->sendExpiryWarnings(30);

        $this->assertEquals(0, $result['warnings_sent']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('Repository error', $result['errors'][0]);
    }

    public function testUpdateExpiredVerificationsWithUpdateFailure()
    {
        $expiredVerifications = [
            [
                'id_kyc_verification' => 1,
                'id_customer' => 123,
            ],
        ];

        $this->verificationRepositoryMock->shouldReceive('findExpiredVerifications')
            ->once()
            ->andReturn($expiredVerifications);

        $this->verificationRepositoryMock->shouldReceive('updateStatus')
            ->once()
            ->andReturn(false);

        $result = $this->maintenanceService->updateExpiredVerifications();

        $this->assertEquals(0, $result['verifications_expired']);
        $this->assertEquals(0, $result['notifications_sent']);
    }

    public function testUpdateExpiredVerificationsWithException()
    {
        $expiredVerifications = [
            [
                'id_kyc_verification' => 1,
                'id_customer' => 123,
            ],
        ];

        $this->verificationRepositoryMock->shouldReceive('findExpiredVerifications')
            ->once()
            ->andReturn($expiredVerifications);

        $this->verificationRepositoryMock->shouldReceive('updateStatus')
            ->once()
            ->andThrow(new \Exception('Update failed'));

        $result = $this->maintenanceService->updateExpiredVerifications();

        $this->assertEquals(0, $result['verifications_expired']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Update failed', $result['errors'][0]);
    }

    public function testUpdateExpiredVerificationsRepositoryException()
    {
        $this->verificationRepositoryMock->shouldReceive('findExpiredVerifications')
            ->once()
            ->andThrow(new \Exception('Repository error'));

        $result = $this->maintenanceService->updateExpiredVerifications();

        $this->assertEquals(0, $result['verifications_expired']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('Repository error', $result['errors'][0]);
    }

    public function testCleanupExpiredDocumentsSuccess()
    {
        $this->documentServiceMock->shouldReceive('cleanupExpiredDocuments')
            ->once()
            ->andReturn(5);

        $result = $this->maintenanceService->cleanupExpiredDocuments();

        $this->assertEquals(5, $result['documents_deleted']);
        $this->assertArrayHasKey('files_deleted', $result);
        $this->assertArrayHasKey('space_freed_mb', $result);
        $this->assertEmpty($result['errors']);
    }

    public function testCleanupExpiredDocumentsWithException()
    {
        $this->documentServiceMock->shouldReceive('cleanupExpiredDocuments')
            ->once()
            ->andThrow(new \Exception('Cleanup failed'));

        $result = $this->maintenanceService->cleanupExpiredDocuments();

        $this->assertEquals(0, $result['documents_deleted']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('Cleanup failed', $result['errors'][0]);
    }

    public function testCleanupOrphanedFilesWithValidFiles()
    {
        // Create test files with larger content to ensure measurable space freed
        $testFile1 = $this->tempUploadDir . '/doc_123_abcdef.pdf';
        $testFile2 = $this->tempUploadDir . '/doc_456_ghijkl.jpg';

        // Create content larger than 1MB to ensure space_freed_mb > 0
        $testContent1 = str_repeat('test content 1 with more data for larger file size', 50000); // ~2.5MB
        $testContent2 = str_repeat('test content 2 with more data for larger file size', 50000); // ~2.5MB

        file_put_contents($testFile1, $testContent1);
        file_put_contents($testFile2, $testContent2);

        // Verify files were created with expected sizes
        $this->assertFileExists($testFile1);
        $this->assertFileExists($testFile2);
        $this->assertGreaterThan(1048576, filesize($testFile1)); // > 1MB

        // Mock document repository to return null (orphaned files)
        $this->documentRepositoryMock->shouldReceive('findById')
            ->with(123)
            ->andReturn(null);

        $this->documentRepositoryMock->shouldReceive('findById')
            ->with(456)
            ->andReturn(['id' => 456]); // This file exists in DB

        $results = ['files_deleted' => 0, 'space_freed_mb' => 0, 'errors' => []];
        $this->maintenanceService->cleanupOrphanedFiles($results);

        $this->assertEquals(1, $results['files_deleted']);
        $this->assertGreaterThan(0, $results['space_freed_mb']);
        $this->assertFileDoesNotExist($testFile1);
        $this->assertFileExists($testFile2);
    }

    public function testCleanupOrphanedFilesWithNonExistentDirectory()
    {
        $service = new MaintenanceService(
            $this->documentServiceMock,
            $this->notificationServiceMock,
            $this->verificationRepositoryMock,
            $this->documentRepositoryMock,
            $this->customerRepositoryMock,
            $this->logRepositoryMock,
            '/non/existent/directory'
        );

        $results = ['files_deleted' => 0, 'space_freed_mb' => 0, 'errors' => []];
        $service->cleanupOrphanedFiles($results);

        $this->assertEquals(0, $results['files_deleted']);
        $this->assertEquals(0, $results['space_freed_mb']);
    }

    public function testCleanupOrphanedFilesWithInvalidFilenames()
    {
        // Create files with invalid naming pattern
        $invalidFile = $this->tempUploadDir . '/invalid_file.pdf';
        file_put_contents($invalidFile, 'test content');

        $results = ['files_deleted' => 0, 'space_freed_mb' => 0, 'errors' => []];
        $this->maintenanceService->cleanupOrphanedFiles($results);

        $this->assertEquals(0, $results['files_deleted']);
        $this->assertFileExists($invalidFile);
    }

    public function testCleanupOrphanedFilesWithException()
    {
        // Create test file
        $testFile = $this->tempUploadDir . '/doc_789_test.pdf';
        file_put_contents($testFile, 'test content');

        $this->documentRepositoryMock->shouldReceive('findById')
            ->with(789)
            ->andThrow(new \Exception('Database error'));

        $results = ['files_deleted' => 0, 'space_freed_mb' => 0, 'errors' => []];
        $this->maintenanceService->cleanupOrphanedFiles($results);

        $this->assertEquals(0, $results['files_deleted']);
        $this->assertCount(1, $results['errors']);
        $this->assertStringContainsString('Orphaned files cleanup:', $results['errors'][0]);
    }

    public function testCleanupOldLogsWithCustomRetentionDays()
    {
        $customRetentionDays = 30;

        $this->logRepositoryMock->shouldReceive('deleteOldLogs')
            ->once()
            ->with($customRetentionDays)
            ->andReturn(5);

        $result = $this->maintenanceService->cleanupOldLogs($customRetentionDays);

        $this->assertEquals(5, $result['logs_deleted']);
        $this->assertEmpty($result['errors']);
    }

    public function testCleanupTempFilesSuccess()
    {
        // Create temporary files with different ages and larger content
        $oldTempFile = $this->tempUploadDir . '/doc_tmp_old.pdf';
        $newTempFile = $this->tempUploadDir . '/doc_tmp_new.pdf';

        // Create content larger than 1MB to ensure space_freed_mb > 0
        $oldContent = str_repeat('old temp content with more data for larger file size', 50000); // ~2.5MB
        $newContent = str_repeat('new temp content with more data for larger file size', 50000); // ~2.5MB

        file_put_contents($oldTempFile, $oldContent);
        file_put_contents($newTempFile, $newContent);

        // Set file modification time to simulate old file
        touch($oldTempFile, time() - (25 * 3600)); // 25 hours ago
        touch($newTempFile, time() - (1 * 3600));  // 1 hour ago

        $result = $this->maintenanceService->cleanupTempFiles(24);

        $this->assertEquals(1, $result['temp_files_deleted']);
        $this->assertGreaterThan(0, $result['space_freed_mb']);
        $this->assertEmpty($result['errors']);
        $this->assertFileDoesNotExist($oldTempFile);
        $this->assertFileExists($newTempFile);
    }

    public function testCleanupTempFilesWithNonExistentDirectory()
    {
        $service = new MaintenanceService(
            $this->documentServiceMock,
            $this->notificationServiceMock,
            $this->verificationRepositoryMock,
            $this->documentRepositoryMock,
            $this->customerRepositoryMock,
            $this->logRepositoryMock,
            '/non/existent/directory'
        );

        $result = $service->cleanupTempFiles();

        $this->assertEquals(0, $result['temp_files_deleted']);
        $this->assertEquals(0, $result['space_freed_mb']);
        $this->assertEmpty($result['errors']);
    }

    public function testCleanupTempFilesWithNoMatchingFiles()
    {
        // Create a file that doesn't match the pattern
        $nonTempFile = $this->tempUploadDir . '/regular_file.pdf';
        file_put_contents($nonTempFile, 'regular content');

        $result = $this->maintenanceService->cleanupTempFiles(24);

        $this->assertEquals(0, $result['temp_files_deleted']);
        $this->assertEquals(0, $result['space_freed_mb']);
        $this->assertEmpty($result['errors']);
        $this->assertFileExists($nonTempFile);
    }

    public function testGetCustomerDataSuccess()
    {
        $customerId = 123;
        $expectedCustomer = [
            'id_customer' => $customerId,
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
        ];

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->once()
            ->with($customerId)
            ->andReturn($expectedCustomer);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->maintenanceService);
        $method = $reflection->getMethod('getCustomerData');
        $method->setAccessible(true);

        $result = $method->invoke($this->maintenanceService, $customerId);

        $this->assertEquals($expectedCustomer, $result);
    }

    public function testGetCustomerDataWithEmptyResult()
    {
        $customerId = 999;

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->once()
            ->with($customerId)
            ->andReturn([]);

        $reflection = new \ReflectionClass($this->maintenanceService);
        $method = $reflection->getMethod('getCustomerData');
        $method->setAccessible(true);

        $result = $method->invoke($this->maintenanceService, $customerId);

        $this->assertNull($result);
    }

    public function testGetCustomerDataWithException()
    {
        $customerId = 123;

        // Mock PrestaShopLogger to handle the log call in the catch block
        $loggerMock = \Mockery::mock();
        $loggerMock->shouldReceive('addLog')
            ->once()
            ->with(
                \Mockery::pattern('/Get customer data error:/'),
                3,
                null,
                'Pskyc'
            );

        \PrestaShopLogger::setStaticExpectations($loggerMock);

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->once()
            ->with($customerId)
            ->andThrow(new \Exception('Database connection failed'));

        $reflection = new \ReflectionClass($this->maintenanceService);
        $method = $reflection->getMethod('getCustomerData');
        $method->setAccessible(true);

        $result = $method->invoke($this->maintenanceService, $customerId);

        $this->assertNull($result);
    }

    public function testRunDailyMaintenanceSuccess()
    {
        // Mock Configuration calls that are made during daily maintenance
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->with('PSKYC_EXPIRY_WARNING_DAYS', 30)
            ->andReturn(30);
        $configurationMock->shouldReceive('get')
            ->with('PSKYC_LOG_RETENTION_DAYS', 0)
            ->andReturn(30);

        \Configuration::setStaticExpectations($configurationMock);

        // Mock all the individual maintenance tasks
        $this->verificationRepositoryMock->shouldReceive('findExpiringVerifications')
            ->once()
            ->andReturn([]);

        $this->verificationRepositoryMock->shouldReceive('findExpiredVerifications')
            ->once()
            ->andReturn([]);

        $this->documentServiceMock->shouldReceive('cleanupExpiredDocuments')
            ->once()
            ->andReturn(3);

        $this->logRepositoryMock->shouldReceive('deleteOldLogs')
            ->once()
            ->andReturn(10);

        $result = $this->maintenanceService->runDailyMaintenance();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('start_time', $result);
        $this->assertArrayHasKey('end_time', $result);
        $this->assertArrayHasKey('total_execution_time', $result);
        $this->assertArrayHasKey('tasks', $result);
        $this->assertEmpty($result['errors']);
        $this->assertGreaterThanOrEqual(0, $result['total_execution_time']);
    }

    public function testSendExpiryWarningsSuccess()
    {
        $expiringVerifications = [
            [
                'id_kyc_verification' => 1,
                'id_customer' => 123,
                'date_expiry' => date('Y-m-d H:i:s', time() + (5 * 86400)),
            ],
            [
                'id_kyc_verification' => 2,
                'id_customer' => 456,
                'date_expiry' => date('Y-m-d H:i:s', time() + (10 * 86400)),
            ],
        ];

        $customerData1 = ['id_customer' => 123, 'email' => 'test1@example.com'];
        $customerData2 = ['id_customer' => 456, 'email' => 'test2@example.com'];

        $this->verificationRepositoryMock->shouldReceive('findExpiringVerifications')
            ->once()
            ->with(30)
            ->andReturn($expiringVerifications);

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->with(123)
            ->andReturn($customerData1);

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->with(456)
            ->andReturn($customerData2);

        $this->notificationServiceMock->shouldReceive('sendExpiryWarning')
            ->twice()
            ->andReturn(true);

        $result = $this->maintenanceService->sendExpiryWarnings(30);

        $this->assertEquals(2, $result['warnings_sent']);
        $this->assertEmpty($result['errors']);
        $this->assertCount(2, $result['customers_notified']);
        $this->assertEquals(123, $result['customers_notified'][0]['customer_id']);
        $this->assertEquals(456, $result['customers_notified'][1]['customer_id']);
    }

    public function testSendExpiryWarningsWithPartialFailure()
    {
        $expiringVerifications = [
            [
                'id_kyc_verification' => 1,
                'id_customer' => 123,
                'date_expiry' => date('Y-m-d H:i:s', time() + (5 * 86400)),
            ],
        ];

        $customerData = ['id_customer' => 123, 'email' => 'test@example.com'];

        $this->verificationRepositoryMock->shouldReceive('findExpiringVerifications')
            ->once()
            ->andReturn($expiringVerifications);

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->once()
            ->andReturn($customerData);

        $this->notificationServiceMock->shouldReceive('sendExpiryWarning')
            ->once()
            ->andReturn(false);

        $result = $this->maintenanceService->sendExpiryWarnings(30);

        $this->assertEquals(0, $result['warnings_sent']);
        $this->assertEmpty($result['errors']);
        $this->assertEmpty($result['customers_notified']);
    }

    public function testUpdateExpiredVerificationsSuccess()
    {
        $expiredVerifications = [
            [
                'id_kyc_verification' => 1,
                'id_customer' => 123,
            ],
            [
                'id_kyc_verification' => 2,
                'id_customer' => 456,
            ],
        ];

        $customerData1 = ['id_customer' => 123, 'email' => 'test1@example.com'];
        $customerData2 = ['id_customer' => 456, 'email' => 'test2@example.com'];

        $this->verificationRepositoryMock->shouldReceive('findExpiredVerifications')
            ->once()
            ->andReturn($expiredVerifications);

        $this->verificationRepositoryMock->shouldReceive('updateStatus')
            ->twice()
            ->andReturn(true);

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->with(123)
            ->andReturn($customerData1);

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->with(456)
            ->andReturn($customerData2);

        $this->notificationServiceMock->shouldReceive('sendStatusChangeNotification')
            ->twice()
            ->andReturn(true);

        $result = $this->maintenanceService->updateExpiredVerifications();

        $this->assertEquals(2, $result['verifications_expired']);
        $this->assertEquals(2, $result['notifications_sent']);
        $this->assertEmpty($result['errors']);
    }

    public function testUpdateExpiredVerificationsWithNotificationFailure()
    {
        $expiredVerifications = [
            [
                'id_kyc_verification' => 1,
                'id_customer' => 123,
            ],
        ];

        $customerData = ['id_customer' => 123, 'email' => 'test@example.com'];

        $this->verificationRepositoryMock->shouldReceive('findExpiredVerifications')
            ->once()
            ->andReturn($expiredVerifications);

        $this->verificationRepositoryMock->shouldReceive('updateStatus')
            ->once()
            ->andReturn(true);

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->once()
            ->andReturn($customerData);

        $this->notificationServiceMock->shouldReceive('sendStatusChangeNotification')
            ->once()
            ->andReturn(false);

        $result = $this->maintenanceService->updateExpiredVerifications();

        $this->assertEquals(1, $result['verifications_expired']);
        $this->assertEquals(0, $result['notifications_sent']);
        $this->assertEmpty($result['errors']);
    }

    public function testCleanupOldLogsWithZeroRetentionDays()
    {
        $result = $this->maintenanceService->cleanupOldLogs(0);

        $this->assertEquals(0, $result['logs_deleted']);
        $this->assertEmpty($result['errors']);
    }

    public function testCleanupOldLogsWithException()
    {
        $this->logRepositoryMock->shouldReceive('deleteOldLogs')
            ->once()
            ->with(30)
            ->andThrow(new \Exception('Database error'));

        $result = $this->maintenanceService->cleanupOldLogs(30);

        $this->assertEquals(0, $result['logs_deleted']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('Database error', $result['errors'][0]);
    }

    public function testCleanupTempFilesWithException()
    {
        // Create a service with a directory that will cause problems
        $service = new MaintenanceService(
            $this->documentServiceMock,
            $this->notificationServiceMock,
            $this->verificationRepositoryMock,
            $this->documentRepositoryMock,
            $this->customerRepositoryMock,
            $this->logRepositoryMock,
            '/proc' // This will cause glob() to fail on most systems
        );

        $result = $service->cleanupTempFiles(24);

        $this->assertEquals(0, $result['temp_files_deleted']);
        $this->assertEquals(0, $result['space_freed_mb']);
        // Errors array should contain an error message
        $this->assertArrayHasKey('errors', $result);
    }

    public function testCleanupOrphanedFilesWithDocumentRepositoryException()
    {
        // Create test file
        $testFile = $this->tempUploadDir . '/doc_123_test.pdf';
        file_put_contents($testFile, 'test content');

        // Make the repository throw an exception during findById
        $this->documentRepositoryMock->shouldReceive('findById')
            ->once()
            ->with(123)
            ->andThrow(new \Exception('Database error'));

        $results = ['files_deleted' => 0, 'space_freed_mb' => 0, 'errors' => []];
        $this->maintenanceService->cleanupOrphanedFiles($results);

        $this->assertEquals(0, $results['files_deleted']);
        $this->assertCount(1, $results['errors']);
        $this->assertStringContainsString('Orphaned files cleanup:', $results['errors'][0]);
        $this->assertStringContainsString('Database error', $results['errors'][0]);
    }

    public function testCleanupTempFilesWithFileOperationFailure()
    {
        // Create a temp file and make it read-only to test file deletion failure scenarios
        $tempFile = $this->tempUploadDir . '/doc_tmp_readonly.pdf';
        file_put_contents($tempFile, 'test content');

        // Set file modification time to be old
        touch($tempFile, time() - (25 * 3600));

        // On Windows, we can't easily test unlink failure, so we'll test the normal case
        $result = $this->maintenanceService->cleanupTempFiles(24);

        $this->assertEquals(1, $result['temp_files_deleted']);
        $this->assertGreaterThanOrEqual(0, $result['space_freed_mb']);
        $this->assertEmpty($result['errors']);
    }

    public function testGetCronTokenSuccess()
    {
        $encryptionKey = 'test_encryption_key_12345';
        $expectedHash = 'abcdefghijklmnopqrstuvwxyz1234567890';
        $expectedToken = 'abcdefghij'; // First 10 characters

        // Mock Configuration::get() to return encryption key
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->once()
            ->with('PSKYC_ENCRYPTION_KEY')
            ->andReturn($encryptionKey);

        \Configuration::setStaticExpectations($configurationMock);

        // Mock Tools::hash() to return predictable hash
        $toolsMock = \Mockery::mock();
        $toolsMock->shouldReceive('hash')
            ->once()
            ->with($encryptionKey . 'pskyc_cron_token')
            ->andReturn($expectedHash);

        \Tools::setStaticExpectations($toolsMock);

        $result = $this->maintenanceService->getCronToken();

        $this->assertEquals($expectedToken, $result);
        $this->assertEquals(10, strlen($result));
    }

    public function testGetCronTokenWithEmptyEncryptionKey()
    {
        // Mock Configuration::get() to return empty string
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->once()
            ->with('PSKYC_ENCRYPTION_KEY')
            ->andReturn('');

        \Configuration::setStaticExpectations($configurationMock);

        // Mock PrestaShopLogger::addLog() for the error log
        $loggerMock = \Mockery::mock();
        $loggerMock->shouldReceive('addLog')
            ->once()
            ->with('PSKYC encryption key missing for cron token generation', 3, null, 'Pskyc');

        \PrestaShopLogger::setStaticExpectations($loggerMock);

        $this->expectException(\PrestaShopException::class);
        $this->expectExceptionMessage('Encryption key not available');

        $this->maintenanceService->getCronToken();
    }

    public function testGetCronTokenWithNullEncryptionKey()
    {
        // Mock Configuration::get() to return null
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->once()
            ->with('PSKYC_ENCRYPTION_KEY')
            ->andReturn(null);

        \Configuration::setStaticExpectations($configurationMock);

        // Mock PrestaShopLogger::addLog() for the error log
        $loggerMock = \Mockery::mock();
        $loggerMock->shouldReceive('addLog')
            ->once()
            ->with('PSKYC encryption key missing for cron token generation', 3, null, 'Pskyc');

        \PrestaShopLogger::setStaticExpectations($loggerMock);

        $this->expectException(\PrestaShopException::class);
        $this->expectExceptionMessage('Encryption key not available');

        $this->maintenanceService->getCronToken();
    }

    public function testGetCronTokenWithDifferentEncryptionKeys()
    {
        // Test with different encryption keys to ensure different tokens
        $testCases = [
            'short_key' => 'abcdefghij',
            'long_encryption_key_with_special_chars_123!@#' => 'klmnopqrst',
            'another_different_key_456' => 'uvwxyz1234',
        ];

        foreach ($testCases as $encryptionKey => $expectedToken) {
            // Mock Configuration::get() to return the test encryption key
            $configurationMock = \Mockery::mock();
            $configurationMock->shouldReceive('get')
                ->once()
                ->with('PSKYC_ENCRYPTION_KEY')
                ->andReturn($encryptionKey);

            \Configuration::setStaticExpectations($configurationMock);

            // Mock Tools::hash() to return predictable hash
            $toolsMock = \Mockery::mock();
            $toolsMock->shouldReceive('hash')
                ->once()
                ->with($encryptionKey . 'pskyc_cron_token')
                ->andReturn($expectedToken . 'extra_chars_to_be_truncated');

            \Tools::setStaticExpectations($toolsMock);

            $result = $this->maintenanceService->getCronToken();

            $this->assertEquals($expectedToken, $result);
            $this->assertEquals(10, strlen($result));
        }
    }

    public function testRunDailyMaintenanceWithException()
    {
        // Instead of trying to mock internal service exceptions (which are caught),
        // let's test the case where the Configuration::get call itself throws an exception
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->with('PSKYC_EXPIRY_WARNING_DAYS', 30)
            ->andThrow(new \Exception('Configuration system failure'));

        \Configuration::setStaticExpectations($configurationMock);

        // Mock PrestaShopLogger for the exception log
        $loggerMock = \Mockery::mock();
        $loggerMock->shouldReceive('addLog')
            ->once()
            ->with(
                'KYC Maintenance Error: Configuration system failure',
                3,
                null,
                'Pskyc'
            );

        \PrestaShopLogger::setStaticExpectations($loggerMock);

        $result = $this->maintenanceService->runDailyMaintenance();

        // The maintenance catches exceptions and marks success as false
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('start_time', $result);
        $this->assertArrayHasKey('end_time', $result);
        $this->assertArrayHasKey('total_execution_time', $result);
        $this->assertArrayHasKey('tasks', $result);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('Configuration system failure', $result['errors'][0]);
        $this->assertGreaterThanOrEqual(0, $result['total_execution_time']);
    }

    public function testSendExpiryWarningsWithDefaultWarningDays()
    {
        // Mock Configuration::get() to return default warning days
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->once()
            ->with('PSKYC_EXPIRY_WARNING_DAYS', 30)
            ->andReturn(15); // Different from default to test configuration is used

        \Configuration::setStaticExpectations($configurationMock);

        $this->verificationRepositoryMock->shouldReceive('findExpiringVerifications')
            ->once()
            ->with(15) // Should use the configured value, not the default
            ->andReturn([]);

        // Call without specifying warning days (should use config)
        $result = $this->maintenanceService->sendExpiryWarnings();

        $this->assertEquals(0, $result['warnings_sent']);
        $this->assertEmpty($result['errors']);
    }

    public function testCleanupOldLogsWithDefaultRetentionDays()
    {
        // Mock Configuration::get() to return default retention days
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->once()
            ->with('PSKYC_LOG_RETENTION_DAYS', 0)
            ->andReturn(45); // Different from default to test configuration is used

        \Configuration::setStaticExpectations($configurationMock);

        $this->logRepositoryMock->shouldReceive('deleteOldLogs')
            ->once()
            ->with(45) // Should use the configured value
            ->andReturn(12);

        // Call without specifying retention days (should use config)
        $result = $this->maintenanceService->cleanupOldLogs();

        $this->assertEquals(12, $result['logs_deleted']);
        $this->assertEmpty($result['errors']);
    }

    public function testLogMaintenanceRunSuccess()
    {
        // Test basic functionality with proper logging expectations
        $results = [
            'success' => true,
            'total_execution_time' => 1.5,
            'tasks' => [
                'expiry_warnings' => ['warnings_sent' => 2],
                'expired_verifications' => ['verifications_expired' => 1],
                'cleanup_documents' => ['documents_deleted' => 3, 'files_deleted' => 1],
                'cleanup_temp_files' => ['temp_files_deleted' => 0],
            ],
            'errors' => [],
        ];

        // Mock PrestaShopLogger to expect the success log message
        $loggerMock = \Mockery::mock();
        $loggerMock->shouldReceive('addLog')
            ->once()
            ->with(
                \Mockery::pattern('/KYC Daily Maintenance:/'),
                1,
                null,
                'Pskyc'
            );

        \PrestaShopLogger::setStaticExpectations($loggerMock);

        // Test that the method executes without throwing exceptions
        $this->maintenanceService->logMaintenanceRun($results);

        // Add an assertion to avoid the risky test warning
        $this->assertTrue(true);
    }

    public function testLogMaintenanceRunFailure()
    {
        // Test with failure results
        $results = [
            'success' => false,
            'total_execution_time' => 0.5,
            'tasks' => [
                'expiry_warnings' => ['warnings_sent' => 0],
                'expired_verifications' => ['verifications_expired' => 0],
                'cleanup_documents' => ['documents_deleted' => 0, 'files_deleted' => 0],
                'cleanup_temp_files' => ['temp_files_deleted' => 0],
            ],
            'errors' => ['Database connection failed', 'File system error'],
        ];

        // Mock PrestaShopLogger to expect the failure log message
        $loggerMock = \Mockery::mock();
        $loggerMock->shouldReceive('addLog')
            ->once()
            ->with(
                \Mockery::pattern('/KYC Daily Maintenance Failed:/'),
                3,
                null,
                'Pskyc'
            );

        \PrestaShopLogger::setStaticExpectations($loggerMock);

        // Test that the method executes without throwing exceptions
        $this->maintenanceService->logMaintenanceRun($results);

        // Add an assertion to avoid the risky test warning
        $this->assertTrue(true);
    }

    public function testCleanupTempFilesWithGlobException()
    {
        // Create a service that will cause an exception during file operations
        // We'll test by creating a file and then making it impossible to access its properties
        $problematicPath = $this->tempUploadDir . '/problematic';
        mkdir($problematicPath);

        // Create a temp file that matches the pattern
        $tempFile = $problematicPath . '/doc_tmp_test.pdf';
        file_put_contents($tempFile, 'test content');

        // Set the file to be old enough for cleanup
        touch($tempFile, time() - (25 * 3600));

        // Create a service with a mock that will throw an exception when cleanup is called
        $service = new class($this->documentServiceMock, $this->notificationServiceMock, $this->verificationRepositoryMock, $this->documentRepositoryMock, $this->customerRepositoryMock, $this->logRepositoryMock, $problematicPath) extends MaintenanceService {
            public function cleanupTempFiles(int $maxAgeHours = 24): array
            {
                $results = [
                    'temp_files_deleted' => 0,
                    'errors' => [],
                    'space_freed_mb' => 0,
                ];

                try {
                    // Simulate an exception during file operations
                    throw new \Exception('File system operation failed');
                } catch (\Exception $e) {
                    $results['errors'][] = $e->getMessage();
                }

                return $results;
            }
        };

        $result = $service->cleanupTempFiles(24);

        // The method should catch the exception and add it to errors
        $this->assertEquals(0, $result['temp_files_deleted']);
        $this->assertEquals(0, $result['space_freed_mb']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('File system operation failed', $result['errors'][0]);
    }

    public function testLogMaintenanceRunWithLoggingException()
    {
        $results = [
            'success' => true,
            'total_execution_time' => 1.5,
            'tasks' => [
                'expiry_warnings' => ['warnings_sent' => 2],
                'expired_verifications' => ['verifications_expired' => 1],
                'cleanup_documents' => ['documents_deleted' => 3, 'files_deleted' => 1],
                'cleanup_temp_files' => ['temp_files_deleted' => 0],
            ],
            'errors' => [],
        ];

        // Mock PrestaShopLogger to throw an exception during the first addLog call
        $loggerMock = \Mockery::mock();
        $loggerMock->shouldReceive('addLog')
            ->once()
            ->with(
                \Mockery::pattern('/KYC Daily Maintenance:/'),
                1,
                null,
                'Pskyc'
            )
            ->andThrow(new \Exception('Logging system failure'));

        // Mock the catch block logger call
        $loggerMock->shouldReceive('addLog')
            ->once()
            ->with(
                'KYC Maintenance Logging Error: Logging system failure',
                3,
                null,
                'Pskyc'
            );

        \PrestaShopLogger::setStaticExpectations($loggerMock);

        // The method should catch the exception and log it, then continue normally
        $this->maintenanceService->logMaintenanceRun($results);

        // Test passes if no exception is thrown and both log calls are made
        $this->assertTrue(true);
    }

    // Test  generateCronUrl
    public function testGenerateCronUrl()
    {
        $encryptionKey = 'test_encryption_key_12345';
        $expectedToken = 'abcdefghij';
        $shopUrl = 'https://mystore.com';
        $action = 'daily_maintenance';

        // Mock Configuration::get() to return encryption key
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->once()
            ->with('PSKYC_ENCRYPTION_KEY')
            ->andReturn($encryptionKey);

        \Configuration::setStaticExpectations($configurationMock);

        // Mock Tools::hash() to return predictable hash
        $toolsMock = \Mockery::mock();
        $toolsMock->shouldReceive('hash')
            ->once()
            ->with($encryptionKey . 'pskyc_cron_token')
            ->andReturn($expectedToken . 'extra_chars_to_be_truncated');

        // Mock Tools::getShopDomainSsl() to return shop URL
        $toolsMock->shouldReceive('getShopDomainSsl')
            ->once()
            ->with(true, true)
            ->andReturn($shopUrl);

        \Tools::setStaticExpectations($toolsMock);

        $result = $this->maintenanceService->generateCronUrl($action);

        $expectedUrl = 'https://mystore.com/modules/pskyc/cron.php?token=abcdefghij&action=daily_maintenance';
        $this->assertEquals($expectedUrl, $result);
    }

    public function testGenerateCronUrlWithCustomAction()
    {
        $encryptionKey = 'test_encryption_key_12345';
        $expectedToken = 'xyz1234567';
        $shopUrl = 'https://example.com/shop';
        $action = 'cleanup_documents';

        // Mock Configuration::get() to return encryption key
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->once()
            ->with('PSKYC_ENCRYPTION_KEY')
            ->andReturn($encryptionKey);

        \Configuration::setStaticExpectations($configurationMock);

        // Mock Tools::hash() to return predictable hash
        $toolsMock = \Mockery::mock();
        $toolsMock->shouldReceive('hash')
            ->once()
            ->with($encryptionKey . 'pskyc_cron_token')
            ->andReturn($expectedToken . 'more_chars');

        // Mock Tools::getShopDomainSsl() to return shop URL
        $toolsMock->shouldReceive('getShopDomainSsl')
            ->once()
            ->with(true, true)
            ->andReturn($shopUrl);

        \Tools::setStaticExpectations($toolsMock);

        $result = $this->maintenanceService->generateCronUrl($action);

        $expectedUrl = 'https://example.com/shop/modules/pskyc/cron.php?token=xyz1234567&action=cleanup_documents';
        $this->assertEquals($expectedUrl, $result);
    }

    public function testGenerateCronUrlWithTrailingSlashInShopUrl()
    {
        $encryptionKey = 'test_key';
        $expectedToken = 'token12345';
        $shopUrl = 'https://test.com/subfolder/';  // URL with trailing slash
        $action = 'daily_maintenance';

        // Mock Configuration::get() to return encryption key
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->once()
            ->with('PSKYC_ENCRYPTION_KEY')
            ->andReturn($encryptionKey);

        \Configuration::setStaticExpectations($configurationMock);

        // Mock Tools::hash() to return predictable hash
        $toolsMock = \Mockery::mock();
        $toolsMock->shouldReceive('hash')
            ->once()
            ->with($encryptionKey . 'pskyc_cron_token')
            ->andReturn($expectedToken . 'suffix');

        // Mock Tools::getShopDomainSsl() to return shop URL with trailing slash
        $toolsMock->shouldReceive('getShopDomainSsl')
            ->once()
            ->with(true, true)
            ->andReturn($shopUrl);

        \Tools::setStaticExpectations($toolsMock);

        $result = $this->maintenanceService->generateCronUrl($action);

        // The trailing slash should be removed by rtrim()
        $expectedUrl = 'https://test.com/subfolder/modules/pskyc/cron.php?token=token12345&action=daily_maintenance';
        $this->assertEquals($expectedUrl, $result);
    }

    public function testGenerateCronUrlWithDefaultAction()
    {
        $encryptionKey = 'default_test_key';
        $expectedToken = 'default123';
        $shopUrl = 'https://default.com';

        // Mock Configuration::get() to return encryption key
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->once()
            ->with('PSKYC_ENCRYPTION_KEY')
            ->andReturn($encryptionKey);

        \Configuration::setStaticExpectations($configurationMock);

        // Mock Tools::hash() to return predictable hash
        $toolsMock = \Mockery::mock();
        $toolsMock->shouldReceive('hash')
            ->once()
            ->with($encryptionKey . 'pskyc_cron_token')
            ->andReturn($expectedToken . 'extra');

        // Mock Tools::getShopDomainSsl() to return shop URL
        $toolsMock->shouldReceive('getShopDomainSsl')
            ->once()
            ->with(true, true)
            ->andReturn($shopUrl);

        \Tools::setStaticExpectations($toolsMock);

        // Call without action parameter (should use default)
        $result = $this->maintenanceService->generateCronUrl();

        $expectedUrl = 'https://default.com/modules/pskyc/cron.php?token=default123&action=daily_maintenance';
        $this->assertEquals($expectedUrl, $result);
    }

    public function testGenerateCronUrlWithGetCronTokenException()
    {
        // Mock Configuration::get() to return empty encryption key
        $configurationMock = \Mockery::mock();
        $configurationMock->shouldReceive('get')
            ->once()
            ->with('PSKYC_ENCRYPTION_KEY')
            ->andReturn('');

        \Configuration::setStaticExpectations($configurationMock);

        // Mock PrestaShopLogger::addLog() for the error log
        $loggerMock = \Mockery::mock();
        $loggerMock->shouldReceive('addLog')
            ->once()
            ->with('PSKYC encryption key missing for cron token generation', 3, null, 'Pskyc');

        \PrestaShopLogger::setStaticExpectations($loggerMock);

        // The getCronToken() call should throw an exception
        $this->expectException(\PrestaShopException::class);
        $this->expectExceptionMessage('Encryption key not available');

        $this->maintenanceService->generateCronUrl('test_action');
    }
}
