<?php

namespace Tests\PsKyc\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\Pskyc\Repository\CustomerRepository;

class CustomerRepositoryTest extends MockeryTestCase
{
    /** @var Connection */
    private $connectionMock;

    /** @var QueryBuilder */
    private $queryBuilderMock;

    /** @var CustomerRepository */
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

        $this->repository = new CustomerRepository($this->connectionMock);
    }

    public function testGetCustomerDataReturnsCustomerWhenFound()
    {
        $customerId = 1;
        $expectedCustomer = [
            'id_customer' => $customerId,
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'date_add' => '2025-01-01 10:00:00',
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('c.id_customer', 'c.firstname', 'c.lastname', 'c.email', 'c.date_add')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_customer', 'c')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('expr')
            ->once()
            ->andReturn($this->expressionBuilderMock);

        $this->expressionBuilderMock->shouldReceive('eq')
            ->once()
            ->with('c.id_customer', ':customerId')
            ->andReturn('c.id_customer = :customerId');

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('c.id_customer = :customerId')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('customerId', $customerId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn($expectedCustomer);

        $result = $this->repository->getCustomerData($customerId);

        $this->assertEquals($expectedCustomer, $result);
    }

    public function testGetCustomerDataReturnsEmptyArrayWhenNotFound()
    {
        $customerId = 999;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('c.id_customer', 'c.firstname', 'c.lastname', 'c.email', 'c.date_add')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_customer', 'c')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('expr')
            ->once()
            ->andReturn($this->expressionBuilderMock);

        $this->expressionBuilderMock->shouldReceive('eq')
            ->once()
            ->with('c.id_customer', ':customerId')
            ->andReturn('c.id_customer = :customerId');

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('c.id_customer = :customerId')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('customerId', $customerId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn(false);

        $result = $this->repository->getCustomerData($customerId);

        $this->assertEquals([], $result);
    }

    public function testGetCustomerDataWithValidIntegerId()
    {
        $customerId = 42;
        $expectedCustomer = [
            'id_customer' => $customerId,
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'email' => 'jane.smith@example.com',
            'date_add' => '2025-01-15 14:30:00',
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('c.id_customer', 'c.firstname', 'c.lastname', 'c.email', 'c.date_add')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_customer', 'c')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('expr')
            ->once()
            ->andReturn($this->expressionBuilderMock);

        $this->expressionBuilderMock->shouldReceive('eq')
            ->once()
            ->with('c.id_customer', ':customerId')
            ->andReturn('c.id_customer = :customerId');

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('c.id_customer = :customerId')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('customerId', $customerId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn($expectedCustomer);

        $result = $this->repository->getCustomerData($customerId);

        $this->assertEquals($expectedCustomer, $result);
        $this->assertArrayHasKey('id_customer', $result);
        $this->assertArrayHasKey('firstname', $result);
        $this->assertArrayHasKey('lastname', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('date_add', $result);
    }

    public function testConstructorSetsConnection()
    {
        $connection = \Mockery::mock(Connection::class);
        $repository = new CustomerRepository($connection);

        // Test that the repository was created successfully
        $this->assertInstanceOf(CustomerRepository::class, $repository);
    }

    public function testGetCustomerDataHandlesNullResult()
    {
        $customerId = 1;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('c.id_customer', 'c.firstname', 'c.lastname', 'c.email', 'c.date_add')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_customer', 'c')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('expr')
            ->once()
            ->andReturn($this->expressionBuilderMock);

        $this->expressionBuilderMock->shouldReceive('eq')
            ->once()
            ->with('c.id_customer', ':customerId')
            ->andReturn('c.id_customer = :customerId');

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('c.id_customer = :customerId')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('customerId', $customerId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $this->resultMock->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn(null);

        $result = $this->repository->getCustomerData($customerId);

        $this->assertEquals([], $result);
    }
}
