<?php

namespace Tests\Unit\Service;

use Tests\BaseTestCase;
use PrestaShop\Module\Pskyc\Service\DocumentService;
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use PrestaShop\Module\Pskyc\Service\EncryptionService;
use Mockery;

/**
 * Unit tests for DocumentService
 * 
 * @covers \PrestaShop\Module\Pskyc\Service\DocumentService
 */
class DocumentServiceTest extends BaseTestCase
{
    private DocumentService $documentService;
    private $mockDocumentRepository;
    private $mockEncryptionService;
    private $mockVerificationRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockDocumentRepository = Mockery::mock(DocumentRepository::class);
        $this->mockEncryptionService = Mockery::mock(EncryptionService::class);
        $this->mockVerificationRepository = Mockery::mock(VerificationRepository::class);
        
        $this->documentService = new DocumentService(
            $this->mockDocumentRepository,
            $this->mockEncryptionService,
            $this->mockVerificationRepository
        );
    }

    public function testUploadDocumentSuccess(): void
    {
        $verificationId = 1;
        $fileData = $this->createMockFileUpload();
        $documentType = 'passport';
        
        // Mock verification exists
        $this->mockVerificationRepository
            ->shouldReceive('findById')
            ->with($verificationId)
            ->andReturn($this->createMockVerification());
        
        // Mock encryption
        $this->mockEncryptionService
            ->shouldReceive('encryptFile')
            ->andReturn([
                'iv' => 'test_iv',
                'sha256' => 'test_hash'
            ]);
        
        // Mock document creation
        $this->mockDocumentRepository
            ->shouldReceive('create')
            ->andReturn(1);
        
        $result = $this->documentService->uploadDocument($verificationId, $fileData, $documentType);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['document_id']);
        $this->assertArrayHasKey('filename', $result);
    }

    public function testUploadDocumentInvalidVerification(): void
    {
        $verificationId = 999;
        $fileData = $this->createMockFileUpload();
        $documentType = 'passport';
        
        // Mock verification not found
        $this->mockVerificationRepository
            ->shouldReceive('findById')
            ->with($verificationId)
            ->andReturn(null);
        
        $result = $this->documentService->uploadDocument($verificationId, $fileData, $documentType);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid verification ID', $result['message']);
    }

    public function testUploadDocumentFileTooLarge(): void
    {
        $verificationId = 1;
        $fileData = $this->createMockFileUpload('large.pdf', 11 * 1024 * 1024); // 11MB
        $documentType = 'passport';
        
        // Mock verification exists
        $this->mockVerificationRepository
            ->shouldReceive('findById')
            ->with($verificationId)
            ->andReturn($this->createMockVerification());
        
        $result = $this->documentService->uploadDocument($verificationId, $fileData, $documentType);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('File size exceeds', $result['message']);
    }

    public function testUploadDocumentInvalidMimeType(): void
    {
        $verificationId = 1;
        $fileData = $this->createMockFileUpload('test.txt', 1024, 'text/plain');
        $documentType = 'passport';
        
        // Mock verification exists
        $this->mockVerificationRepository
            ->shouldReceive('findById')
            ->with($verificationId)
            ->andReturn($this->createMockVerification());
        
        $result = $this->documentService->uploadDocument($verificationId, $fileData, $documentType);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid file type', $result['message']);
    }

    public function testGetDocumentSuccess(): void
    {
        $documentId = 1;
        $mockDocument = $this->createMockDocument();
        $testContent = 'Test document content';
        
        // Mock document exists
        $this->mockDocumentRepository
            ->shouldReceive('findById')
            ->with($documentId)
            ->andReturn($mockDocument);
        
        // Mock decryption
        $this->mockEncryptionService
            ->shouldReceive('decryptFile')
            ->andReturn($testContent);
        
        // Mock integrity verification
        $this->mockEncryptionService
            ->shouldReceive('verifyIntegrity')
            ->with($testContent, $mockDocument['sha256'])
            ->andReturn(true);
        
        $result = $this->documentService->getDocument($documentId);
        
        $this->assertTrue($result['success']);
        $this->assertEquals($testContent, $result['data']);
        $this->assertEquals($mockDocument['filename'], $result['filename']);
        $this->assertEquals($mockDocument['mime'], $result['mime']);
    }

    public function testGetDocumentNotFound(): void
    {
        $documentId = 999;
        
        // Mock document not found
        $this->mockDocumentRepository
            ->shouldReceive('findById')
            ->with($documentId)
            ->andReturn(null);
        
        $result = $this->documentService->getDocument($documentId);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Document not found', $result['message']);
    }

    public function testGetDocumentIntegrityFailure(): void
    {
        $documentId = 1;
        $mockDocument = $this->createMockDocument();
        $testContent = 'Test document content';
        
        // Mock document exists
        $this->mockDocumentRepository
            ->shouldReceive('findById')
            ->with($documentId)
            ->andReturn($mockDocument);
        
        // Mock decryption
        $this->mockEncryptionService
            ->shouldReceive('decryptFile')
            ->andReturn($testContent);
        
        // Mock integrity verification failure
        $this->mockEncryptionService
            ->shouldReceive('verifyIntegrity')
            ->with($testContent, $mockDocument['sha256'])
            ->andReturn(false);
        
        $result = $this->documentService->getDocument($documentId);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Document integrity verification failed', $result['message']);
    }

    public function testDeleteDocumentSuccess(): void
    {
        $documentId = 1;
        $mockDocument = $this->createMockDocument();
        
        // Mock document exists
        $this->mockDocumentRepository
            ->shouldReceive('findById')
            ->with($documentId)
            ->andReturn($mockDocument);
        
        // Mock database deletion
        $this->mockDocumentRepository
            ->shouldReceive('delete')
            ->with($documentId)
            ->andReturn(true);
        
        // Mock secure file deletion
        $this->mockEncryptionService
            ->shouldReceive('secureDelete')
            ->andReturn(true);
        
        $result = $this->documentService->deleteDocument($documentId);
        
        $this->assertTrue($result);
    }

    public function testDeleteDocumentNotFound(): void
    {
        $documentId = 999;
        
        // Mock document not found
        $this->mockDocumentRepository
            ->shouldReceive('findById')
            ->with($documentId)
            ->andReturn(null);
        
        $result = $this->documentService->deleteDocument($documentId);
        
        $this->assertFalse($result);
    }

    public function testRequiresBothSides(): void
    {
        $this->assertTrue($this->documentService->requiresBothSides('id_card'));
        $this->assertTrue($this->documentService->requiresBothSides('driving_license'));
        $this->assertFalse($this->documentService->requiresBothSides('passport'));
        $this->assertFalse($this->documentService->requiresBothSides('utility_bill'));
    }

    public function testGetRequiredSides(): void
    {
        $twoSidedSides = $this->documentService->getRequiredSides('id_card');
        $this->assertEquals(['front', 'back'], $twoSidedSides);
        
        $singleSidedSides = $this->documentService->getRequiredSides('passport');
        $this->assertEquals([], $singleSidedSides);
    }

    public function testCheckDocumentCompleteness(): void
    {
        $verificationId = 1;
        $documents = [
            $this->createMockDocument(['type' => 'passport']),
            $this->createMockDocument(['type' => 'utility_bill', 'id_kyc_document' => 2])
        ];
        
        // Mock documents retrieval
        $this->mockDocumentRepository
            ->shouldReceive('findByVerificationId')
            ->with($verificationId)
            ->andReturn($documents);
        
        $result = $this->documentService->checkDocumentCompleteness($verificationId);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('complete', $result);
        $this->assertArrayHasKey('missing', $result);
    }

    public function testCleanupExpiredDocuments(): void
    {
        $expiredDocuments = [
            $this->createMockDocument(['id_kyc_document' => 1]),
            $this->createMockDocument(['id_kyc_document' => 2])
        ];
        
        // Mock expired documents retrieval
        $this->mockDocumentRepository
            ->shouldReceive('findExpiredDocuments')
            ->andReturn($expiredDocuments);
        
        // Mock document deletions
        $this->mockDocumentRepository
            ->shouldReceive('findById')
            ->andReturn($this->createMockDocument());
        
        $this->mockDocumentRepository
            ->shouldReceive('delete')
            ->andReturn(true);
        
        $this->mockEncryptionService
            ->shouldReceive('secureDelete')
            ->andReturn(true);
        
        $result = $this->documentService->cleanupExpiredDocuments();
        
        $this->assertEquals(2, $result);
    }
}