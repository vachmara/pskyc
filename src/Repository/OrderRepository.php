<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

namespace PrestaShop\Module\Pskyc\Repository;
if (!defined('_PS_VERSION_')) { exit; }
use Doctrine\DBAL\Connection;
use Exception;

/**
 * Class OrderRepository
 *
 * Repository for managing order-related data access
 * Provides methods for retrieving order and cart information needed for KYC risk assessment
 */
class OrderRepository
{
    /**
     * @var Connection Database connection instance
     */
    private $connection;

    /**
     * OrderRepository constructor
     *
     * @param Connection $connection Database connection instance
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the categories ID of products in the customer's cart
     *
     * Retrieves all distinct default category IDs for products currently in the customer's cart.
     * This information can be used for KYC risk assessment based on product categories.
     *
     * @param int $customerId Customer ID to filter the cart products
     *
     * @return array Array of category IDs from the customer's cart products
     */
    public function findProductsCartsCategoriesByCustomerId(int $customerId): array
    {
        try {
            $qb = $this->connection->createQueryBuilder();

            $query = $qb->select('DISTINCT cp.id_category_default')
                ->from(_DB_PREFIX_ . 'cart_product', 'cp')
                ->innerJoin('cp', _DB_PREFIX_ . 'cart', 'c', 'c.id_cart = cp.id_cart')
                ->where('c.id_customer = :customerId')
                ->setParameter('customerId', (int) $customerId);

            $result = $query->execute();

            return $result->fetchAllAssociative();
        } catch (\Exception $e) {
            // Handle exception - log error and return empty array
            return [];
        }
    }
}
