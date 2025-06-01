<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * @author Valentin Chmara
 * @copyright Valentin Chmara
 * @license MIT
 */

namespace Tests\PsKyc\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;

class VerificationRepositoryTest extends MockeryTestCase
{
    /** @var Connection */
    private $connectionMock;

    /** @var QueryBuilder */
    private $queryBuilderMock;

    /** @var VerificationRepository */
    private $repository;

    /** @var Result */
    private $resultMock;

    protected function setUp(): void
    {
        $this->connectionMock = \Mockery::mock(Connection::class);
        $this->queryBuilderMock = \Mockery::mock(QueryBuilder::class);
        $this->resultMock = \Mockery::mock(Result::class);

        $this->repository = new VerificationRepository($this->connectionMock);
    }

    public function testFindByIdReturnsVerificationWhenFound()
    {
        $verificationId = 1;
        $expectedVerification = [
            'id_kyc_verification' => $verificationId,
            'id_customer' => 1,
            'status' => 'pending',
            'date_submitted' => '2025-01-01 10:00:00',
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_verification')
            ->mockQueryBuilderWhere('id_kyc_verification = :id')
            ->mockQueryBuilderSetParameter('id', $verificationId);

        $this->resultMock->shouldReceive('rowCount')
            ->once()
            ->andReturn(1);

        $this->resultMock->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn($expectedVerification);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findById($verificationId);

        $this->assertEquals($expectedVerification, $result);
    }

    public function testFindByIdReturnsNullWhenNotFound()
    {
        $verificationId = 999;

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_verification')
            ->mockQueryBuilderWhere('id_kyc_verification = :id')
            ->mockQueryBuilderSetParameter('id', $verificationId);

        $this->resultMock->shouldReceive('rowCount')
            ->once()
            ->andReturn(0);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findById($verificationId);

        $this->assertNull($result);
    }

    public function testFindAllWithNoFilters()
    {
        $expectedVerifications = [
            [
                'id_kyc_verification' => 1,
                'id_customer' => 1,
                'status' => 'pending',
            ],
            [
                'id_kyc_verification' => 2,
                'id_customer' => 2,
                'status' => 'approved',
            ],
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_verification');

        $this->queryBuilderMock->shouldReceive('setMaxResults')
            ->once()
            ->with(20)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setFirstResult')
            ->once()
            ->with(0)
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedVerifications);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findAll();

        $this->assertEquals($expectedVerifications, $result);
    }

    public function testFindAllWithAllFilters()
    {
        $filters = [
            'status' => 'approved',
            'customer_id' => 1,
            'date_from' => '2025-01-01',
            'date_to' => '2025-01-31',
        ];
        $limit = 10;
        $offset = 5;

        $expectedVerifications = [
            [
                'id_kyc_verification' => 1,
                'id_customer' => 1,
                'status' => 'approved',
            ],
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_verification');

        $this->queryBuilderMock->shouldReceive('setMaxResults')
            ->once()
            ->with($limit)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setFirstResult')
            ->once()
            ->with($offset)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('status = :status')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('id_customer = :customer_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('date_submitted >= :date_from')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('date_submitted <= :date_to')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('status', 'approved')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('customer_id', 1)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('date_from', '2025-01-01')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('date_to', '2025-01-31')
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedVerifications);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findAll($filters, $limit, $offset);

        $this->assertEquals($expectedVerifications, $result);
    }

    public function testGetStatusCountsReturnsStatusCounts()
    {
        $expectedCounts = [
            'pending' => 5,
            'approved' => 3,
            'rejected' => 2,
        ];

        $this->mockQueryBuilderSelect('status, COUNT(*) as count')
            ->mockQueryBuilderFrom('PS_kyc_verification');

        $this->queryBuilderMock->shouldReceive('groupBy')
            ->once()
            ->with('status')
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchAllKeyValue')
            ->once()
            ->andReturn($expectedCounts);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->getStatusCounts();

        $this->assertEquals($expectedCounts, $result);
    }

    public function testCountAllWithNoFilters()
    {
        $expectedCount = 10;

        $this->mockQueryBuilderSelect('COUNT(*)')
            ->mockQueryBuilderFrom('PS_kyc_verification');

        $this->resultMock->shouldReceive('fetchOne')
            ->once()
            ->andReturn($expectedCount);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->countAll();

        $this->assertEquals($expectedCount, $result);
    }

    public function testCountAllWithFilters()
    {
        $filters = [
            'status' => 'approved',
            'customer_id' => 1,
            'date_from' => '2025-01-01',
            'date_to' => '2025-01-31',
        ];
        $expectedCount = 3;

        $this->mockQueryBuilderSelect('COUNT(*)')
            ->mockQueryBuilderFrom('PS_kyc_verification');

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->times(4)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->times(4)
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchOne')
            ->once()
            ->andReturn($expectedCount);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->countAll($filters);

        $this->assertEquals($expectedCount, $result);
    }

    public function testFindByCustomerIdReturnsLatestVerification()
    {
        $customerId = 1;
        $expectedVerification = [
            'id_kyc_verification' => 2,
            'id_customer' => $customerId,
            'status' => 'pending',
            'date_submitted' => '2025-01-02 10:00:00',
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_verification')
            ->mockQueryBuilderWhere('id_customer = :customer_id')
            ->mockQueryBuilderSetParameter('customer_id', $customerId);

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('date_submitted', 'DESC')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setMaxResults')
            ->once()
            ->with(1)
            ->andReturnSelf();

        $this->resultMock->shouldReceive('rowCount')
            ->once()
            ->andReturn(1);

        $this->resultMock->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn($expectedVerification);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findByCustomerId($customerId);

        $this->assertEquals($expectedVerification, $result);
    }

    public function testFindByCustomerIdReturnsNullWhenNotFound()
    {
        $customerId = 999;

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_verification')
            ->mockQueryBuilderWhere('id_customer = :customer_id')
            ->mockQueryBuilderSetParameter('customer_id', $customerId);

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('date_submitted', 'DESC')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setMaxResults')
            ->once()
            ->with(1)
            ->andReturnSelf();

        $this->resultMock->shouldReceive('rowCount')
            ->once()
            ->andReturn(0);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findByCustomerId($customerId);

        $this->assertNull($result);
    }

    public function testFindAllByCustomerIdReturnsAllVerifications()
    {
        $customerId = 1;
        $expectedVerifications = [
            [
                'id_kyc_verification' => 2,
                'id_customer' => $customerId,
                'status' => 'pending',
            ],
            [
                'id_kyc_verification' => 1,
                'id_customer' => $customerId,
                'status' => 'approved',
            ],
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_verification')
            ->mockQueryBuilderWhere('id_customer = :customer_id')
            ->mockQueryBuilderSetParameter('customer_id', $customerId);

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('date_submitted', 'DESC')
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedVerifications);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findAllByCustomerId($customerId);

        $this->assertEquals($expectedVerifications, $result);
    }

    public function testCreateReturnsNewVerificationId()
    {
        $customerId = 1;
        $status = 'pending';
        $customerNote = 'Initial submission';
        $expectedId = 42;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('insert')
            ->once()
            ->with('PS_kyc_verification')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('values')
            ->once()
            ->with([
                'id_customer' => ':customer_id',
                'status' => ':status',
                'date_submitted' => 'NOW()',
                'customer_note' => ':customer_note',
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->with([
                'customer_id' => $customerId,
                'status' => $status,
                'customer_note' => $customerNote,
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once();

        $this->connectionMock->shouldReceive('lastInsertId')
            ->once()
            ->andReturn($expectedId);

        $result = $this->repository->create($customerId, $status, $customerNote);

        $this->assertEquals($expectedId, $result);
    }

    public function testCreateWithDefaultValues()
    {
        $customerId = 1;
        $expectedId = 42;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('insert')
            ->once()
            ->with('PS_kyc_verification')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('values')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->with([
                'customer_id' => $customerId,
                'status' => 'pending',
                'customer_note' => null,
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once();

        $this->connectionMock->shouldReceive('lastInsertId')
            ->once()
            ->andReturn($expectedId);

        $result = $this->repository->create($customerId);

        $this->assertEquals($expectedId, $result);
    }

    public function testFindActiveByCustomerIdReturnsActiveVerifications()
    {
        $customerId = 1;
        $expectedVerifications = [
            [
                'id_kyc_verification' => 1,
                'id_customer' => $customerId,
                'status' => 'pending',
            ],
            [
                'id_kyc_verification' => 2,
                'id_customer' => $customerId,
                'status' => 'approved',
            ],
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_verification')
            ->mockQueryBuilderWhere('id_customer = :customer_id');

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('status != :expired_status')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('customer_id', $customerId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('expired_status', 'expired')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('date_submitted', 'DESC')
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedVerifications);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findActiveByCustomerId($customerId);

        $this->assertEquals($expectedVerifications, $result);
    }

    public function testFindOneByIdReturnsVerification()
    {
        $verificationId = 1;
        $expectedVerification = [
            'id_kyc_verification' => $verificationId,
            'status' => 'pending',
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_verification')
            ->mockQueryBuilderWhere('id_kyc_verification = :id')
            ->mockQueryBuilderSetParameter('id', $verificationId);

        $this->resultMock->shouldReceive('rowCount')
            ->once()
            ->andReturn(1);

        $this->resultMock->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn($expectedVerification);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findOneById($verificationId);

        $this->assertEquals($expectedVerification, $result);
    }

    public function testUpdateStatusWithoutNote()
    {
        $verificationId = 1;
        $status = 'approved';

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('update')
            ->once()
            ->with('PS_kyc_verification')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('status', ':status')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('date_validated', 'NOW()')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_verification = :id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->with([
                'status' => $status,
                'id' => $verificationId,
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->updateStatus($verificationId, $status);

        $this->assertTrue($result);
    }

    public function testUpdateStatusWithNote()
    {
        $verificationId = 1;
        $status = 'approved';
        $note = 'All documents verified';

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('update')
            ->once()
            ->with('PS_kyc_verification')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('status', ':status')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('date_validated', 'NOW()')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('admin_note', ':note')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_verification = :id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->with([
                'status' => $status,
                'id' => $verificationId,
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('note', $note)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->updateStatus($verificationId, $status, $note);

        $this->assertTrue($result);
    }

    public function testUpdateStatusReturnsFalseWhenNoRowsAffected()
    {
        $verificationId = 999;
        $status = 'approved';

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('update')
            ->once()
            ->with('PS_kyc_verification')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->times(2)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(0);

        $result = $this->repository->updateStatus($verificationId, $status);

        $this->assertFalse($result);
    }

    public function testUpdateAdminNoteReturnsTrue()
    {
        $verificationId = 1;
        $note = 'Additional verification required';

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('update')
            ->once()
            ->with('PS_kyc_verification')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('admin_note', ':note')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_verification = :id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->with([
                'note' => $note,
                'id' => $verificationId,
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->updateAdminNote($verificationId, $note);

        $this->assertTrue($result);
    }

    public function testUpdateExpiryDateReturnsTrue()
    {
        $verificationId = 1;
        $expirationDate = '2025-12-31 23:59:59';

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('update')
            ->once()
            ->with('PS_kyc_verification')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('date_expiry', ':expiration_date')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('date_validated', 'NOW()')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_verification = :id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->with([
                'expiration_date' => $expirationDate,
                'id' => $verificationId,
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->updateExpiryDate($verificationId, $expirationDate);

        $this->assertTrue($result);
    }

    public function testUpdateExpiryDateWithNullDate()
    {
        $verificationId = 1;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('update')
            ->once()
            ->with('PS_kyc_verification')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->times(2)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->with([
                'expiration_date' => null,
                'id' => $verificationId,
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->updateExpiryDate($verificationId, null);

        $this->assertTrue($result);
    }

    public function testDeleteReturnsTrue()
    {
        $verificationId = 1;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('delete')
            ->once()
            ->with('PS_kyc_verification')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_verification = :id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('id', $verificationId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->delete($verificationId);

        $this->assertTrue($result);
    }

    public function testDeleteReturnsFalseWhenNoRowsAffected()
    {
        $verificationId = 999;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('delete')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(0);

        $result = $this->repository->delete($verificationId);

        $this->assertFalse($result);
    }

    public function testFindAllForExportReturnsCompleteData()
    {
        $expectedData = [
            [
                'log_id' => 1,
                'verification_id' => 1,
                'action' => 'created',
                'customer_email' => 'test@example.com',
                'employee_firstname' => 'John',
                'employee_lastname' => 'Doe',
            ],
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with([
                'l.id_kyc_log as log_id',
                'l.id_kyc_verification as verification_id',
                'l.action',
                'l.message',
                'l.ip_address',
                'l.user_agent',
                'l.date_add',
                'l.date_upd',
                'v.id_customer',
                'v.status as verification_status',
                'c.email as customer_email',
                'e.firstname as employee_firstname',
                'e.lastname as employee_lastname',
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_kyc_log', 'l')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('leftJoin')
            ->once()
            ->with('l', 'PS_kyc_verification', 'v', 'l.id_kyc_verification = v.id_kyc_verification')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('leftJoin')
            ->once()
            ->with('l', 'PS_customer', 'c', 'l.id_customer = c.id_customer')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('leftJoin')
            ->once()
            ->with('l', 'PS_employee', 'e', 'l.id_employee = e.id_employee')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('l.date_add', 'DESC')
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedData);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findAllForExport();

        $this->assertEquals($expectedData, $result);
    }

    public function testFindExpiringVerificationsReturnsExpiringData()
    {
        $days = 7;
        $expectedVerifications = [
            [
                'id_kyc_verification' => 1,
                'status' => 'approved',
                'date_expiry' => '2025-06-07 23:59:59',
            ],
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_verification')
            ->mockQueryBuilderWhere('status = :status');

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('date_expiry IS NOT NULL')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('date_expiry <= :future_date')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('date_expiry > NOW()')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('status', 'approved')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('date_expiry', 'ASC')
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedVerifications);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findExpiringVerifications($days);

        $this->assertEquals($expectedVerifications, $result);
    }

    public function testFindExpiredVerificationsReturnsExpiredData()
    {
        $expectedVerifications = [
            [
                'id_kyc_verification' => 1,
                'status' => 'approved',
                'date_expiry' => '2024-12-31 23:59:59',
            ],
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_verification')
            ->mockQueryBuilderWhere('status = :status');

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('date_expiry IS NOT NULL')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('date_expiry < NOW()')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('status', 'approved')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('date_expiry', 'ASC')
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedVerifications);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findExpiredVerifications();

        $this->assertEquals($expectedVerifications, $result);
    }

    public function testUpdateNoteCallsUpdateAdminNote()
    {
        $verificationId = 1;
        $note = 'Test note';

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('update')
            ->once()
            ->with('PS_kyc_verification')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('admin_note', ':note')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_verification = :id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->with([
                'note' => $note,
                'id' => $verificationId,
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->updateNote($verificationId, $note);

        $this->assertTrue($result);
    }

    /**
     * Helper method to mock QueryBuilder select
     */
    private function mockQueryBuilderSelect($select = '*')
    {
        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with($select)
            ->andReturnSelf();

        return $this;
    }

    /**
     * Helper method to mock QueryBuilder from
     */
    private function mockQueryBuilderFrom($table)
    {
        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with($table)
            ->andReturnSelf();

        return $this;
    }

    /**
     * Helper method to mock QueryBuilder where
     */
    private function mockQueryBuilderWhere($condition)
    {
        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with($condition)
            ->andReturnSelf();

        return $this;
    }

    /**
     * Helper method to mock QueryBuilder setParameter
     */
    private function mockQueryBuilderSetParameter($parameter, $value)
    {
        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with($parameter, $value)
            ->andReturnSelf();

        return $this;
    }
}
