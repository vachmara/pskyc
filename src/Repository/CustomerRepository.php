<?php 
namespace PrestaShop\Module\Pskyc\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Query\Expr;
use PrestaShop\PrestaShop\Core\Domain\Customer\ValueObject\CustomerId;

/**
 * Class CustomerRepository
 * 
 * Repository for managing customer data access
 * Provides methods for retrieving customer information needed for KYC operations
 */
class CustomerRepository
{
    /**
     * @var Connection Database connection instance
     */
    private $connection;

    /**
     * CustomerRepository constructor
     *
     * @param Connection $connection Database connection instance
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get customer data
     *
     * Retrieves
     *
     * @param int $customerId Customer ID
     * @return array Customer data
     */
    public function getCustomerData(int $customerId): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('c.id_customer', 'c.firstname', 'c.lastname', 'c.email', 'c.date_add')
            ->from('customer', 'c')
            ->where($queryBuilder->expr()->eq('c.id_customer', ':customerId'))
            ->setParameter(':customerId', $customerId);

        return $queryBuilder->executeQuery()->fetchAssociative() ?: [];
    }
}