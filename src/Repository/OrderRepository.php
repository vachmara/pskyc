<?php 
namespace PrestaShop\Module\Pskyc\Repository;

use Doctrine\DBAL\Connection;
use Exception;
use PrestaShop\PrestaShop\Core\Domain\Customer\ValueObject\CustomerId;

class OrderRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * OrderRepository constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the categories ID of the customer cart
     * 
     * @param CustomerId $customerId
     * 
     * @return array
     */
    public function findProductsCartsCategoriesByCustomerId(CustomerId $customerId): array
    {
        try {
          $qb = $this->connection->createQueryBuilder();

          $query = $qb->select('DISTINCT cp.id_category_default')
              ->from(_DB_PREFIX_ . 'cart_product', 'cp')
              ->innerJoin('cp', _DB_PREFIX_ . 'cart', 'c', 'c.id_cart = cp.id_cart') 
              ->where('c.id_customer = :customerId')
              ->setParameter('customerId', (int) $customerId->getValue());

          $result = $query->execute();

          return $result->fetchAllAssociative();
        }
        catch (Exception $e) {
            // Handle exception
            return [];
        }
    }
    
}