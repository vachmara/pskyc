<?php

namespace Tests\Unit\Repository;

use Tests\BaseTestCase;
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use Mockery;

/**
 * Unit tests for DocumentRepository
 * 
 * @covers \PrestaShop\Module\Pskyc\Repository\DocumentRepository
 */
class DocumentRepositoryTest extends BaseTestCase
{
    private DocumentRepository $repository;
    private $mockDbConnection;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockDbConnection = Mockery::mock('Doctrine\DBAL\Connection');
        $this->repository = new DocumentRepository($this->mockDbConnection);
    }

    public function testFindById(): void
    {
        $documentId = 1;
        $expectedData = $this->createMockDocument();
        
        $this->mockDbConnection
            ->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn($expectedData);
        
        $result = $this->repository->findById($documentId);
        
        $this->assertEquals($expectedData, $result);
    }

    public function testFindByIdNotFound(): void
    {
        $documentId = 999;
        
        $this->mockDbConnection
            ->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn(false);
        
        $result = $this->repository->findById($documentId);
        
        $this->assertNull($result);
    }

    public function testCreate(): void
    {
        $data = [
            'verification_id' => 1,
            'type' => 'passport',
            'filename' => 'test.pdf',
            'filesize' => 1024,
            'mime' => 'application/pdf'
        ];
        
        $this->mockDbConnection
            ->shouldReceive('insert')
            ->once()
            ->andReturn(1);
        
        $this->mockDbConnection
            ->shouldReceive('lastInsertId')
            ->once()
            ->andReturn('1');
        
        $result = $this->repository->create($data);
        
        $this->assertEquals(1, $result);
    }

    public function testUpdate(): void
    {
        $documentId = 1;
        $data = [
            'type' => 'updated_passport',
            'filename' => 'updated.pdf'
        ];
        
        $this->mockDbConnection
            ->shouldReceive('update')
            ->once()
            ->andReturn(1);
        
        $result = $this->repository->update($documentId, $data);
        
        $this->assertTrue($result);
    }

    public function testDelete(): void
    {
        $documentId = 1;
        
        $this->mockDbConnection
            ->shouldReceive('delete')
            ->once()
            ->andReturn(1);
        
        $result = $this->repository->delete($documentId);
        
        $this->assertTrue($result);
    }

    public function testFindByVerificationId(): void
    {
        $verificationId = 1;
        $expectedData = [
            $this->createMockDocument(),
            $this->createMockDocument(['id_kyc_document' => 2])
        ];
        
        $this->mockDbConnection
            ->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedData);
        
        $result = $this->repository->findByVerificationId($verificationId);
        
        $this->assertEquals($expectedData, $result);
    }

    public function testFindByVerificationIdAndType(): void
    {
        $verificationId = 1;
        $documentType = 'passport';
        $expectedData = [
            $this->createMockDocument(['type' => 'passport'])
        ];
        
        $this->mockDbConnection
            ->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedData);
        
        $result = $this->repository->findByVerificationIdAndType($verificationId, $documentType);
        
        $this->assertEquals($expectedData, $result);
    }

    public function testFindExpiredDocuments(): void
    {
        $expectedData = [
            $this->createMockDocument(['expires_at' => '2023-01-01 00:00:00']),
            $this->createMockDocument(['id_kyc_document' => 2, 'expires_at' => '2023-06-01 00:00:00'])
        ];
        
        $this->mockDbConnection
            ->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedData);
        
        $result = $this->repository->findExpiredDocuments();
        
        $this->assertEquals($expectedData, $result);
    }

    public function testCountByVerificationId(): void
    {
        $verificationId = 1;
        
        $this->mockDbConnection
            ->shouldReceive('fetchOne')
            ->once()
            ->andReturn('3');
        
        $result = $this->repository->countByVerificationId($verificationId);
        
        $this->assertEquals(3, $result);
    }
}