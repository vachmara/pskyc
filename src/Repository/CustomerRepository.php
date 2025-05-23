<?php 
namespace PrestaShop\Module\Pskyc\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Query\Expr;
use PrestaShop\PrestaShop\Core\Domain\Customer\ValueObject\CustomerId;

class CustomerRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * CustomerRepository constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Find customer name by customer id
     *
     * @param CustomerId $customerId
     *
     * @return string
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