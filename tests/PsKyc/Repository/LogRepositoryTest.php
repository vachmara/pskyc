<?php

namespace Tests\PsKyc\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\Pskyc\Repository\LogRepository;

class LogRepositoryTest extends MockeryTestCase
{
    /** @var Connection */
    private $connectionMock;

    /** @var QueryBuilder */
    private $queryBuilderMock;

    /** @var LogRepository */
    private $repository;

    /** @var Result */
    private $resultMock;

    /** @var ExpressionBuilder */
    private $expressionBuilderMock;

    protected function setUp(): void
    {
        $this->connectionMock = \Mockery::mock(Connection::class);
        $this->queryBuilderMock = \Mockery::mock(QueryBuilder::class);
        $this->resultMock = \Mockery::mock(Result::class);
        $this->expressionBuilderMock = \Mockery::mock(ExpressionBuilder::class);

        $this->repository = new LogRepository($this->connectionMock);
    }

    public function testConstructorSetsConnection()
    {
        $connection = \Mockery::mock(Connection::class);
        $repository = new LogRepository($connection);

        $this->assertInstanceOf(LogRepository::class, $repository);
    }

    public function testCreateLogReturnsLogIdOnSuccess()
    {
        $logId = 123;
        $kycVerificationId = 1;
        $employeeId = 2;
        $customerId = null;
        $action = 'status_changed';
        $message = 'Status changed to approved';
        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0';

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('insert')
            ->once()
            ->with('PS_kyc_log')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('values')
            ->once()
            ->with([
                'id_kyc_verification' => ':verification_id',
                'id_employee' => ':employee_id',
                'id_customer' => ':customer_id',
                'action' => ':action',
                'message' => ':message',
                'ip_address' => ':ip_address',
                'user_agent' => ':user_agent',
                'date_add' => 'NOW()',
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->with([
                'verification_id' => $kycVerificationId,
                'employee_id' => $employeeId,
                'customer_id' => $customerId,
                'action' => $action,
                'message' => $message,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ])
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(true);

        $this->connectionMock->shouldReceive('lastInsertId')
            ->once()
            ->andReturn($logId);

        $result = $this->repository->createLog(
            $kycVerificationId,
            $employeeId,
            $customerId,
            $action,
            $message,
            $ipAddress,
            $userAgent
        );

        $this->assertEquals($logId, $result);
    }

    public function testCreateLogReturnsNullOnFailure()
    {
        $kycVerificationId = 1;
        $employeeId = null;
        $customerId = 2;
        $action = 'documents_uploaded';
        $message = 'Documents uploaded successfully';
        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0';

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('insert')
            ->once()
            ->with('PS_kyc_log')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('values')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(false);

        $result = $this->repository->createLog(
            $kycVerificationId,
            $employeeId,
            $customerId,
            $action,
            $message,
            $ipAddress,
            $userAgent
        );

        $this->assertNull($result);
    }

    public function testCreateReturnsLogIdOnSuccess()
    {
        $logId = 456;
        $data = [
            'verification_id' => 1,
            'employee_id' => 2,
            'customer_id' => null,
            'action' => 'verification_approved',
            'message' => 'Verification approved by admin',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Chrome/96.0',
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('insert')
            ->once()
            ->with('PS_kyc_log')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('values')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(true);

        $this->connectionMock->shouldReceive('lastInsertId')
            ->once()
            ->andReturn($logId);

        $result = $this->repository->create($data);

        $this->assertEquals($logId, $result);
    }

    public function testCreateHandlesDataWithDetails()
    {
        $logId = 789;
        $data = [
            'verification_id' => 1,
            'customer_id' => 3,
            'action' => 'document_upload',
            'details' => 'ID document uploaded',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Safari/14.1',
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('insert')
            ->once()
            ->with('PS_kyc_log')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('values')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(true);

        $this->connectionMock->shouldReceive('lastInsertId')
            ->once()
            ->andReturn($logId);

        $result = $this->repository->create($data);

        $this->assertEquals($logId, $result);
    }

    public function testFindByVerificationIdReturnsLogEntries()
    {
        $verificationId = 1;
        $expectedLogs = [
            [
                'id_kyc_log' => 1,
                'id_kyc_verification' => 1,
                'action' => 'documents_uploaded',
                'message' => 'Documents uploaded',
                'date_add' => '2025-01-01 10:00:00',
                'ip_address_readable' => '192.168.1.1',
            ],
            [
                'id_kyc_log' => 2,
                'id_kyc_verification' => 1,
                'action' => 'status_changed',
                'message' => 'Status changed to pending',
                'date_add' => '2025-01-01 11:00:00',
                'ip_address_readable' => '192.168.1.2',
            ],
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('l.*, INET6_NTOA(l.ip_address) as ip_address_readable')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_kyc_log', 'l')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('l.id_kyc_verification = :verification_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('verification_id', $verificationId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('l.date_add', 'DESC')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setFirstResult')
            ->once()
            ->with(0)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setMaxResults')
            ->once()
            ->with(100)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedLogs);

        $result = $this->repository->findByVerificationId($verificationId);

        $this->assertEquals($expectedLogs, $result);
        $this->assertCount(2, $result);
    }

    public function testFindByVerificationIdWithCustomLimitAndOffset()
    {
        $verificationId = 1;
        $limit = 50;
        $offset = 10;
        $expectedLogs = [];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('l.*, INET6_NTOA(l.ip_address) as ip_address_readable')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_kyc_log', 'l')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('l.id_kyc_verification = :verification_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('verification_id', $verificationId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('l.date_add', 'DESC')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setFirstResult')
            ->once()
            ->with($offset)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setMaxResults')
            ->once()
            ->with($limit)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedLogs);

        $result = $this->repository->findByVerificationId($verificationId, $limit, $offset);

        $this->assertEquals($expectedLogs, $result);
    }

    public function testFindByCustomerIdReturnsLogEntries()
    {
        $customerId = 2;
        $expectedLogs = [
            [
                'id_kyc_log' => 3,
                'id_customer' => 2,
                'action' => 'profile_updated',
                'message' => 'Profile information updated',
                'date_add' => '2025-01-02 14:00:00',
                'ip_address_readable' => '192.168.1.3',
            ],
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('l.*, INET6_NTOA(l.ip_address) as ip_address_readable')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_kyc_log', 'l')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('l.id_customer = :customer_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('customer_id', $customerId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('l.date_add', 'DESC')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setMaxResults')
            ->once()
            ->with(50)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedLogs);

        $result = $this->repository->findByCustomerId($customerId);

        $this->assertEquals($expectedLogs, $result);
        $this->assertCount(1, $result);
    }

    public function testFindByEmployeeIdReturnsLogEntries()
    {
        $employeeId = 3;
        $expectedLogs = [
            [
                'id_kyc_log' => 4,
                'id_employee' => 3,
                'action' => 'verification_reviewed',
                'message' => 'Verification reviewed and approved',
                'date_add' => '2025-01-03 09:00:00',
                'ip_address_readable' => '10.0.0.5',
            ],
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('l.*, INET6_NTOA(l.ip_address) as ip_address_readable')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_kyc_log', 'l')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('l.id_employee = :employee_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('employee_id', $employeeId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('l.date_add', 'DESC')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setMaxResults')
            ->once()
            ->with(50)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedLogs);

        $result = $this->repository->findByEmployeeId($employeeId);

        $this->assertEquals($expectedLogs, $result);
        $this->assertCount(1, $result);
    }

    public function testGetLogStatsReturnsActionCounts()
    {
        $expectedStats = [
            ['action' => 'documents_uploaded', 'count' => '15'],
            ['action' => 'status_changed', 'count' => '10'],
            ['action' => 'verification_approved', 'count' => '5'],
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('action, COUNT(*) as count')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_kyc_log')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('groupBy')
            ->once()
            ->with('action')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('count', 'DESC')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedStats);

        $result = $this->repository->getLogStats();

        $this->assertEquals($expectedStats, $result);
        $this->assertCount(3, $result);
    }

    public function testGetLogStatsWithVerificationIdFilter()
    {
        $verificationId = 1;
        $expectedStats = [
            ['action' => 'documents_uploaded', 'count' => '3'],
            ['action' => 'status_changed', 'count' => '2'],
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('action, COUNT(*) as count')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_kyc_log')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('groupBy')
            ->once()
            ->with('action')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('count', 'DESC')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_verification = :verification_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('verification_id', $verificationId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedStats);

        $result = $this->repository->getLogStats($verificationId);

        $this->assertEquals($expectedStats, $result);
        $this->assertCount(2, $result);
    }

    public function testDeleteOldLogsReturnsDeletedCount()
    {
        $days = 30;
        $deletedCount = 150;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('delete')
            ->once()
            ->with('PS_kyc_log')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('date_add < DATE_SUB(NOW(), INTERVAL :days DAY)')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('days', $days)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($deletedCount);

        $result = $this->repository->deleteOldLogs($days);

        $this->assertEquals($deletedCount, $result);
    }

    public function testDeleteOldLogsReturnsZeroWhenNoLogsDeleted()
    {
        $days = 365;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('delete')
            ->once()
            ->with('PS_kyc_log')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('date_add < DATE_SUB(NOW(), INTERVAL :days DAY)')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('days', $days)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(0);

        $result = $this->repository->deleteOldLogs($days);

        $this->assertEquals(0, $result);
    }

    public function testFindByCustomerIdWithCustomLimit()
    {
        $customerId = 5;
        $limit = 25;
        $expectedLogs = [];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('l.*, INET6_NTOA(l.ip_address) as ip_address_readable')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_kyc_log', 'l')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('l.id_customer = :customer_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('customer_id', $customerId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('l.date_add', 'DESC')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setMaxResults')
            ->once()
            ->with($limit)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedLogs);

        $result = $this->repository->findByCustomerId($customerId, $limit);

        $this->assertEquals($expectedLogs, $result);
    }

    public function testFindByEmployeeIdWithCustomLimit()
    {
        $employeeId = 7;
        $limit = 20;
        $expectedLogs = [];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('l.*, INET6_NTOA(l.ip_address) as ip_address_readable')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_kyc_log', 'l')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('l.id_employee = :employee_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('employee_id', $employeeId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('l.date_add', 'DESC')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setMaxResults')
            ->once()
            ->with($limit)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedLogs);

        $result = $this->repository->findByEmployeeId($employeeId, $limit);

        $this->assertEquals($expectedLogs, $result);
    }
}
