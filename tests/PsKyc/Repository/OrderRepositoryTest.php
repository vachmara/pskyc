<?php

namespace Tests\PsKyc\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\Pskyc\Repository\OrderRepository;

class OrderRepositoryTest extends MockeryTestCase
{
    /** @var Connection */
    private $connectionMock;

    /** @var QueryBuilder */
    private $queryBuilderMock;

    /** @var OrderRepository */
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

        $this->repository = new OrderRepository($this->connectionMock);
    }

    public function testConstructorSetsConnection()
    {
        $connection = \Mockery::mock(Connection::class);
        $repository = new OrderRepository($connection);

        // Test that the repository was created successfully
        $this->assertInstanceOf(OrderRepository::class, $repository);
    }

    public function testFindProductsCartsCategoriesByCustomerIdReturnsCategories()
    {
        $customerId = 1;
        $expectedCategories = [
            ['id_category_default' => '3'],
            ['id_category_default' => '5'],
            ['id_category_default' => '8'],
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('DISTINCT cp.id_category_default')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_cart_product', 'cp')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('innerJoin')
            ->once()
            ->with('cp', 'PS_cart', 'c', 'c.id_cart = cp.id_cart')
            ->andReturnSelf();

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

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedCategories);

        $result = $this->repository->findProductsCartsCategoriesByCustomerId($customerId);

        $this->assertEquals($expectedCategories, $result);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function testFindProductsCartsCategoriesByCustomerIdReturnsEmptyArrayWhenNoCategories()
    {
        $customerId = 999;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('DISTINCT cp.id_category_default')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_cart_product', 'cp')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('innerJoin')
            ->once()
            ->with('cp', 'PS_cart', 'c', 'c.id_cart = cp.id_cart')
            ->andReturnSelf();

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

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn([]);

        $result = $this->repository->findProductsCartsCategoriesByCustomerId($customerId);

        $this->assertEquals([], $result);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindProductsCartsCategoriesByCustomerIdHandlesException()
    {
        $customerId = 1;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $result = $this->repository->findProductsCartsCategoriesByCustomerId($customerId);

        $this->assertEquals([], $result);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindProductsCartsCategoriesByCustomerIdWithValidIntegerId()
    {
        $customerId = 42;
        $expectedCategories = [
            ['id_category_default' => '1'],
            ['id_category_default' => '7'],
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('DISTINCT cp.id_category_default')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_cart_product', 'cp')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('innerJoin')
            ->once()
            ->with('cp', 'PS_cart', 'c', 'c.id_cart = cp.id_cart')
            ->andReturnSelf();

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

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedCategories);

        $result = $this->repository->findProductsCartsCategoriesByCustomerId($customerId);

        $this->assertEquals($expectedCategories, $result);
        $this->assertCount(2, $result);
        foreach ($result as $category) {
            $this->assertArrayHasKey('id_category_default', $category);
        }
    }

    public function testFindProductsCartsCategoriesByCustomerIdHandlesQueryBuilderFailure()
    {
        $customerId = 1;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with('DISTINCT cp.id_category_default')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with('PS_cart_product', 'cp')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('innerJoin')
            ->once()
            ->with('cp', 'PS_cart', 'c', 'c.id_cart = cp.id_cart')
            ->andReturnSelf();

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
            ->andThrow(new \Exception('Execute failed'));

        $result = $this->repository->findProductsCartsCategoriesByCustomerId($customerId);

        $this->assertEquals([], $result);
    }
}
