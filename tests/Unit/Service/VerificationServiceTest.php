<?php

namespace Tests\Unit\Service;

use Tests\BaseTestCase;
use PrestaShop\Module\Pskyc\Service\VerificationService;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\LogRepository;
use PrestaShop\Module\Pskyc\Service\DocumentService;
use PrestaShop\Module\Pskyc\Service\NotificationService;
use Mockery;

/**
 * Unit tests for VerificationService
 * 
 * @covers \PrestaShop\Module\Pskyc\Service\VerificationService
 */
class VerificationServiceTest extends BaseTestCase
{
    private VerificationService $verificationService;
    private $mockVerificationRepository;
    private $mockDocumentRepository;
    private $mockLogRepository;
    private $mockDocumentService;
    private $mockNotificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockVerificationRepository = Mockery::mock(VerificationRepository::class);
        $this->mockDocumentRepository = Mockery::mock(DocumentRepository::class);
        $this->mockLogRepository = Mockery::mock(LogRepository::class);
        $this->mockDocumentService = Mockery::mock(DocumentService::class);
        $this->mockNotificationService = Mockery::mock(NotificationService::class);
        
        $this->verificationService = new VerificationService(
            $this->mockVerificationRepository,
            $this->mockDocumentRepository,
            $this->mockLogRepository,
            $this->mockDocumentService,
            $this->mockNotificationService
        );
    }

    public function testCreateVerificationSuccess(): void
    {
        $customerId = 123;
        $options = ['admin_note' => 'Test note'];
        
        // Mock no existing active verification
        $this->mockVerificationRepository
            ->shouldReceive('findActiveByCustomerId')
            ->with($customerId)
            ->andReturn(null);
        
        // Mock verification creation
        $this->mockVerificationRepository
            ->shouldReceive('create')
            ->andReturn(1);
        
        // Mock log creation
        $this->mockLogRepository
            ->shouldReceive('create')
            ->andReturn(true);
        
        $result = $this->verificationService->createVerification($customerId, $options);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['verification_id']);
    }

    public function testCreateVerificationWithExistingActive(): void
    {
        $customerId = 123;
        $existingVerification = $this->createMockVerification(['status' => 'pending']);
        
        // Mock existing active verification
        $this->mockVerificationRepository
            ->shouldReceive('findActiveByCustomerId')
            ->with($customerId)
            ->andReturn($existingVerification);
        
        $result = $this->verificationService->createVerification($customerId);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already has an active verification', $result['message']);
        $this->assertEquals($existingVerification['id_kyc_verification'], $result['verification_id']);
    }

    public function testUpdateStatusSuccess(): void
    {
        $verificationId = 1;
        $newStatus = 'approved';
        $employeeId = 5;
        $adminNote = 'Approved after review';
        
        $verification = $this->createMockVerification(['status' => 'pending']);
        $customer = $this->createMockCustomer();
        
        // Mock verification exists
        $this->mockVerificationRepository
            ->shouldReceive('findById')
            ->with($verificationId)
            ->andReturn($verification);
        
        // Mock update
        $this->mockVerificationRepository
            ->shouldReceive('update')
            ->andReturn(true);
        
        // Mock log creation
        $this->mockLogRepository
            ->shouldReceive('create')
            ->andReturn(true);
        
        // Mock customer data retrieval
        $this->mockVerificationRepository
            ->shouldReceive('getCustomerData')
            ->andReturn($customer);
        
        // Mock notification
        $this->mockNotificationService
            ->shouldReceive('sendStatusChangeNotification')
            ->andReturn(true);
        
        $result = $this->verificationService->updateStatus($verificationId, $newStatus, $employeeId, $adminNote);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Verification status updated successfully', $result['message']);
    }

    public function testUpdateStatusInvalidTransition(): void
    {
        $verificationId = 1;
        $newStatus = 'pending';
        $verification = $this->createMockVerification(['status' => 'approved']);
        
        // Mock verification exists
        $this->mockVerificationRepository
            ->shouldReceive('findById')
            ->with($verificationId)
            ->andReturn($verification);
        
        $result = $this->verificationService->updateStatus($verificationId, $newStatus);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid status transition', $result['message']);
    }

    public function testUpdateStatusVerificationNotFound(): void
    {
        $verificationId = 999;
        $newStatus = 'approved';
        
        // Mock verification not found
        $this->mockVerificationRepository
            ->shouldReceive('findById')
            ->with($verificationId)
            ->andReturn(null);
        
        $result = $this->verificationService->updateStatus($verificationId, $newStatus);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Verification not found', $result['message']);
    }

    public function testGetVerificationWithDocuments(): void
    {
        $verificationId = 1;
        $verification = $this->createMockVerification();
        $documents = [
            $this->createMockDocument(),
            $this->createMockDocument(['id_kyc_document' => 2])
        ];
        
        // Mock verification retrieval
        $this->mockVerificationRepository
            ->shouldReceive('findById')
            ->with($verificationId)
            ->andReturn($verification);
        
        // Mock documents retrieval
        $this->mockDocumentRepository
            ->shouldReceive('findByVerificationId')
            ->with($verificationId)
            ->andReturn($documents);
        
        $result = $this->verificationService->getVerificationWithDocuments($verificationId);
        
        $this->assertIsArray($result);
        $this->assertEquals($verification['id_kyc_verification'], $result['id_kyc_verification']);
        $this->assertArrayHasKey('documents', $result);
        $this->assertCount(2, $result['documents']);
    }

    public function testGetVerificationWithDocumentsNotFound(): void
    {
        $verificationId = 999;
        
        // Mock verification not found
        $this->mockVerificationRepository
            ->shouldReceive('findById')
            ->with($verificationId)
            ->andReturn(null);
        
        $result = $this->verificationService->getVerificationWithDocuments($verificationId);
        
        $this->assertNull($result);
    }

    public function testGetCustomerVerifications(): void
    {
        $customerId = 123;
        $limit = 5;
        $verifications = [
            $this->createMockVerification(),
            $this->createMockVerification(['id_kyc_verification' => 2, 'status' => 'approved'])
        ];
        
        // Mock verifications retrieval
        $this->mockVerificationRepository
            ->shouldReceive('findByCustomerId')
            ->with($customerId, $limit)
            ->andReturn($verifications);
        
        $result = $this->verificationService->getCustomerVerifications($customerId, $limit);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testCheckKycRequirement(): void
    {
        $customerId = 123;
        
        // Mock no active verification
        $this->mockVerificationRepository
            ->shouldReceive('findActiveByCustomerId')
            ->with($customerId)
            ->andReturn(null);
        
        // Mock order history check
        $this->mockVerificationRepository
            ->shouldReceive('checkOrderHistory')
            ->with($customerId)
            ->andReturn(true);
        
        $result = $this->verificationService->checkKycRequirement($customerId);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['required']);
        $this->assertStringContainsString('products that require KYC', $result['reason']);
    }

    public function testCheckKycRequirementWithValidVerification(): void
    {
        $customerId = 123;
        $verification = $this->createMockVerification([
            'status' => 'approved',
            'date_expiry' => '2025-12-31 23:59:59' // Future date
        ]);
        
        // Mock active approved verification
        $this->mockVerificationRepository
            ->shouldReceive('findActiveByCustomerId')
            ->with($customerId)
            ->andReturn($verification);
        
        $result = $this->verificationService->checkKycRequirement($customerId);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['required']);
        $this->assertStringContainsString('valid KYC verification', $result['reason']);
    }

    public function testProcessCleanup(): void
    {
        // Mock expired verifications cleanup
        $this->mockVerificationRepository
            ->shouldReceive('markExpiredVerifications')
            ->andReturn(3);
        
        // Mock old pending cleanup
        $this->mockVerificationRepository
            ->shouldReceive('cleanupOldPendingVerifications')
            ->andReturn(2);
        
        // Mock document cleanup
        $this->mockDocumentService
            ->shouldReceive('cleanupExpiredDocuments')
            ->andReturn(5);
        
        $result = $this->verificationService->processCleanup();
        
        $this->assertIsArray($result);
        $this->assertEquals(3, $result['expired_verifications']);
        $this->assertEquals(2, $result['old_pending_verifications']);
        $this->assertEquals(5, $result['deleted_documents']);
    }

    /**
     * Test valid status transitions
     */
    public function testValidStatusTransitions(): void
    {
        $reflection = new \ReflectionClass($this->verificationService);
        $method = $reflection->getMethod('isValidStatusTransition');
        $method->setAccessible(true);
        
        // Valid transitions
        $this->assertTrue($method->invoke($this->verificationService, 'pending', 'under_review'));
        $this->assertTrue($method->invoke($this->verificationService, 'under_review', 'approved'));
        $this->assertTrue($method->invoke($this->verificationService, 'under_review', 'rejected'));
        $this->assertTrue($method->invoke($this->verificationService, 'approved', 'expired'));
        
        // Invalid transitions
        $this->assertFalse($method->invoke($this->verificationService, 'approved', 'pending'));
        $this->assertFalse($method->invoke($this->verificationService, 'rejected', 'approved'));
        $this->assertFalse($method->invoke($this->verificationService, 'expired', 'under_review'));
    }

    /**
     * Test expiry date calculation
     */
    public function testCalculateExpiryDate(): void
    {
        $reflection = new \ReflectionClass($this->verificationService);
        $method = $reflection->getMethod('calculateExpiryDate');
        $method->setAccessible(true);
        
        $expiryDate = $method->invoke($this->verificationService);
        
        $this->assertIsString($expiryDate);
        $this->assertGreaterThan(time(), strtotime($expiryDate));
    }
}