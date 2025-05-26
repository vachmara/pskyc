<?php

namespace Tests\Unit\Repository;

use Tests\BaseTestCase;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use Mockery;

/**
 * Unit tests for VerificationRepository
 * 
 * @covers \PrestaShop\Module\Pskyc\Repository\VerificationRepository
 */
class VerificationRepositoryTest extends BaseTestCase
{
    private VerificationRepository $repository;
    private $mockDbConnection;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockDbConnection = Mockery::mock('Doctrine\DBAL\Connection');
        $this->repository = new VerificationRepository($this->mockDbConnection);
    }

    public function testFindById(): void
    {
        $verificationId = 1;
        $expectedData = $this->createMockVerification();
        
        $this->mockDbConnection
            ->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn($expectedData);
        
        $result = $this->repository->findById($verificationId);
        
        $this->assertEquals($expectedData, $result);
    }

    public function testFindByIdNotFound(): void
    {
        $verificationId = 999;
        
        $this->mockDbConnection
            ->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn(false);
        
        $result = $this->repository->findById($verificationId);
        
        $this->assertNull($result);
    }

    public function testCreate(): void
    {
        $data = [
            'id_customer' => 123,
            'status' => 'pending',
            'admin_note' => 'Test note'
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
        $verificationId = 1;
        $data = [
            'status' => 'approved',
            'date_validated' => '2024-01-01 10:00:00'
        ];
        
        $this->mockDbConnection
            ->shouldReceive('update')
            ->once()
            ->andReturn(1);
        
        $result = $this->repository->update($verificationId, $data);
        
        $this->assertTrue($result);
    }

    public function testFindByCustomerId(): void
    {
        $customerId = 123;
        $limit = 5;
        $expectedData = [
            $this->createMockVerification(),
            $this->createMockVerification(['id_kyc_verification' => 2])
        ];
        
        $this->mockDbConnection
            ->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedData);
        
        $result = $this->repository->findByCustomerId($customerId, $limit);
        
        $this->assertEquals($expectedData, $result);
    }

    public function testFindActiveByCustomerId(): void
    {
        $customerId = 123;
        $expectedData = $this->createMockVerification(['status' => 'pending']);
        
        $this->mockDbConnection
            ->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn($expectedData);
        
        $result = $this->repository->findActiveByCustomerId($customerId);
        
        $this->assertEquals($expectedData, $result);
    }

    public function testMarkExpiredVerifications(): void
    {
        $this->mockDbConnection
            ->shouldReceive('executeStatement')
            ->once()
            ->andReturn(3);
        
        $result = $this->repository->markExpiredVerifications();
        
        $this->assertEquals(3, $result);
    }

    public function testCleanupOldPendingVerifications(): void
    {
        $this->mockDbConnection
            ->shouldReceive('executeStatement')
            ->once()
            ->andReturn(2);
        
        $result = $this->repository->cleanupOldPendingVerifications();
        
        $this->assertEquals(2, $result);
    }

    public function testCheckOrderHistory(): void
    {
        $customerId = 123;
        
        $this->mockDbConnection
            ->shouldReceive('fetchOne')
            ->once()
            ->andReturn('1');
        
        $result = $this->repository->checkOrderHistory($customerId);
        
        $this->assertTrue($result);
    }

    public function testGetCustomerData(): void
    {
        $customerId = 123;
        $expectedData = $this->createMockCustomer();
        
        $this->mockDbConnection
            ->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn($expectedData);
        
        $result = $this->repository->getCustomerData($customerId);
        
        $this->assertEquals($expectedData, $result);
    }
}