<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use PrestaShop\Module\Pskyc\Service\VerificationService;
use PrestaShop\Module\Pskyc\Service\DocumentService;
use PrestaShop\Module\Pskyc\Service\NotificationService;
use PrestaShop\Module\Pskyc\Service\EncryptionService;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\LogRepository;
use Mockery;

/**
 * Integration tests for KYC workflow
 * 
 * Tests the complete workflow from verification creation to document upload and approval
 */
class KycWorkflowTest extends BaseTestCase
{
    private $mockDbConnection;
    private VerificationService $verificationService;
    private DocumentService $documentService;
    private NotificationService $notificationService;
    private EncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create real encryption service (no mocks needed)
        $this->encryptionService = new EncryptionService();
        
        // Mock database connection
        $this->mockDbConnection = Mockery::mock('Doctrine\DBAL\Connection');
        
        // Create repositories with mocked connection
        $verificationRepository = new VerificationRepository($this->mockDbConnection);
        $documentRepository = new DocumentRepository($this->mockDbConnection);
        $logRepository = new LogRepository($this->mockDbConnection);
        
        // Create notification service with mocked translator
        $mockTranslator = Mockery::mock('Symfony\Component\Translation\TranslatorInterface');
        $mockTranslator->shouldReceive('trans')->andReturn('Test Translation');
        $this->notificationService = new NotificationService($mockTranslator);
        
        // Create document service
        $this->documentService = new DocumentService(
            $documentRepository,
            $this->encryptionService,
            $verificationRepository
        );
        
        // Create verification service
        $this->verificationService = new VerificationService(
            $verificationRepository,
            $documentRepository,
            $logRepository,
            $this->documentService,
            $this->notificationService
        );
    }

    /**
     * Test complete KYC workflow from start to finish
     */
    public function testCompleteKycWorkflow(): void
    {
        $customerId = 123;
        
        // Step 1: Create verification
        $this->mockDbConnection
            ->shouldReceive('fetchAssociative')
            ->with(Mockery::pattern('/SELECT.*FROM.*ps_kyc_verification.*WHERE.*id_customer/'))
            ->andReturn(null); // No existing verification
        
        $this->mockDbConnection
            ->shouldReceive('insert')
            ->with('ps_kyc_verification', Mockery::type('array'))
            ->andReturn(1);
        
        $this->mockDbConnection
            ->shouldReceive('lastInsertId')
            ->andReturn('1');
        
        $this->mockDbConnection
            ->shouldReceive('insert')
            ->with('ps_kyc_log', Mockery::type('array'))
            ->andReturn(1);
        
        $verificationResult = $this->verificationService->createVerification($customerId);
        
        $this->assertTrue($verificationResult['success']);
        $verificationId = $verificationResult['verification_id'];
        
        // Step 2: Upload documents
        $this->mockDbConnection
            ->shouldReceive('fetchAssociative')
            ->with(Mockery::pattern('/SELECT.*FROM.*ps_kyc_verification.*WHERE.*id_kyc_verification/'))
            ->andReturn($this->createMockVerification(['id_kyc_verification' => $verificationId]));
        
        $this->mockDbConnection
            ->shouldReceive('insert')
            ->with('ps_kyc_document', Mockery::type('array'))
            ->andReturn(1);
        
        // Create test file
        $testFile = $this->createMockFileUpload('passport.pdf', 2048, 'application/pdf');
        
        $documentResult = $this->documentService->uploadDocument($verificationId, $testFile, 'passport');
        
        $this->assertTrue($documentResult['success']);
        $this->assertArrayHasKey('document_id', $documentResult);
        
        // Step 3: Update verification status
        $this->mockDbConnection
            ->shouldReceive('update')
            ->with('ps_kyc_verification', Mockery::type('array'), ['id_kyc_verification' => $verificationId])
            ->andReturn(1);
        
        $this->mockDbConnection
            ->shouldReceive('fetchAssociative')
            ->with(Mockery::pattern('/SELECT.*FROM.*customer/'))
            ->andReturn($this->createMockCustomer(['id_customer' => $customerId]));
        
        $statusResult = $this->verificationService->updateStatus($verificationId, 'approved', 1, 'Approved by admin');
        
        $this->assertTrue($statusResult['success']);
        
        // Cleanup test file
        if (file_exists($testFile['tmp_name'])) {
            unlink($testFile['tmp_name']);
        }
    }

    /**
     * Test document encryption and decryption integration
     */
    public function testDocumentEncryptionIntegration(): void
    {
        // Create test file with content
        $originalContent = 'This is a test document content for encryption testing';
        $testFile = tempnam(sys_get_temp_dir(), 'kyc_test_');
        file_put_contents($testFile, $originalContent);
        
        // Test encryption
        $encryptedFile = tempnam(sys_get_temp_dir(), 'kyc_encrypted_');
        $encryptionResult = $this->encryptionService->encryptFile($testFile, $encryptedFile);
        
        $this->assertArrayHasKey('iv', $encryptionResult);
        $this->assertArrayHasKey('sha256', $encryptionResult);
        $this->assertEquals(hash('sha256', $originalContent), $encryptionResult['sha256']);
        
        // Verify file is encrypted (different from original)
        $encryptedContent = file_get_contents($encryptedFile);
        $this->assertNotEquals($originalContent, $encryptedContent);
        
        // Test decryption
        $decryptedContent = $this->encryptionService->decryptFile($encryptedFile, $encryptionResult['iv']);
        $this->assertEquals($originalContent, $decryptedContent);
        
        // Test integrity verification
        $this->assertTrue($this->encryptionService->verifyIntegrity($decryptedContent, $encryptionResult['sha256']));
        
        // Cleanup
        unlink($testFile);
        unlink($encryptedFile);
    }

    /**
     * Test error handling in workflow
     */
    public function testWorkflowErrorHandling(): void
    {
        $customerId = 123;
        
        // Test creating verification when one already exists
        $this->mockDbConnection
            ->shouldReceive('fetchAssociative')
            ->andReturn($this->createMockVerification(['status' => 'pending']));
        
        $result = $this->verificationService->createVerification($customerId);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already has an active verification', $result['message']);
    }

    /**
     * Test notification system integration
     */
    public function testNotificationIntegration(): void
    {
        $verification = $this->createMockVerification(['status' => 'approved']);
        $customer = $this->createMockCustomer();
        
        // Test status change notification
        $result = $this->notificationService->sendStatusChangeNotification($verification, $customer, 'pending');
        $this->assertTrue($result);
        
        // Test document upload confirmation
        $documents = [$this->createMockDocument()];
        $result = $this->notificationService->sendDocumentUploadConfirmation($verification, $customer, $documents);
        $this->assertTrue($result);
        
        // Test admin notification
        $result = $this->notificationService->sendAdminNotification($verification, $customer);
        $this->assertTrue($result);
    }
}