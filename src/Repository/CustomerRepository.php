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
     * Find customer name by customer ID
     *
     * Retrieves the full name (firstname + lastname) of a customer by their ID
     *
     * @param CustomerId $customerId The customer ID to search for
     * @return string The customer's full name (firstname lastname)
     */
    public function findCustomerNameByCustomerId(CustomerId $customerId): string
    {
        $qb = $this->connection->createQueryBuilder();
        $expression = new Expr();
        $concat = $expression->concat('firstname', '" "', 'lastname');

        $query = $qb->select($concat . ' as name')
            ->from(_DB_PREFIX_ . 'customer', 'customer')
            ->where('customer.id_customer = :id_customer')
            ->setParameter('id_customer', $customerId->getValue())
        ;

        $result = $query->execute();

        return $result->fetchOne();
    }
}