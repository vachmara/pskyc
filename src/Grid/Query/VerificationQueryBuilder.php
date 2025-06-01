<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

declare(strict_types=1);

namespace PrestaShop\Module\Pskyc\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Query\Filter\DoctrineFilterApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Query\Filter\SqlFilters;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Query builder for KYC verification grid
 *
 * Builds SQL queries to retrieve verification data for the admin grid
 */
class VerificationQueryBuilder extends AbstractDoctrineQueryBuilder
{
    /**
     * @var DoctrineSearchCriteriaApplicatorInterface
     */
    private $searchCriteriaApplicator;

    /**
     * @var DoctrineFilterApplicatorInterface|null
     */
    private $filterApplicator;

    public function __construct(
        Connection $connection,
        string $dbPrefix,
        DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator,
        ?DoctrineFilterApplicatorInterface $filterApplicator = null,
    ) {
        parent::__construct($connection, $dbPrefix);
        $this->searchCriteriaApplicator = $searchCriteriaApplicator;
        $this->filterApplicator = $filterApplicator;
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getQueryBuilder($searchCriteria->getFilters());

        // Debug logging
        error_log('KYC Grid Filters: ' . json_encode($searchCriteria->getFilters()));

        $qb
            ->select('v.`id_kyc_verification`, v.`id_customer`, v.`status`')
            ->addSelect('v.`date_submitted`, v.`date_validated`, v.`admin_note`')
            ->addSelect('c.`email` AS `customer_email`')
            ->addSelect('CONCAT(c.`firstname`, " ", c.`lastname`) AS `customer_name`')
            ->addSelect('(SELECT COUNT(d.id_kyc_document) FROM ' . $this->dbPrefix . 'kyc_document d WHERE d.id_kyc_verification = v.id_kyc_verification) AS documents_count')
        ;

        $this->searchCriteriaApplicator
            ->applyPagination($searchCriteria, $qb)
            ->applySorting($searchCriteria, $qb)
        ;

        // Debug the final SQL
        error_log('KYC Grid SQL: ' . $qb->getSQL());
        error_log('KYC Grid Params: ' . json_encode($qb->getParameters()));

        return $qb;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getQueryBuilder($searchCriteria->getFilters());
        $qb->select('COUNT(v.`id_kyc_verification`)');

        return $qb;
    }

    /**
     * Gets query builder.
     *
     * @param array $filterValues
     *
     * @return QueryBuilder
     */
    private function getQueryBuilder(array $filterValues): QueryBuilder
    {
        $qb = $this->connection
            ->createQueryBuilder()
            ->from($this->dbPrefix . 'kyc_verification', 'v')
            ->leftJoin(
                'v',
                $this->dbPrefix . 'customer',
                'c',
                'c.`id_customer` = v.`id_customer`'
            )
        ;

        $sqlFilters = new SqlFilters();
        $sqlFilters
            ->addFilter(
                'id_kyc_verification',
                'v.`id_kyc_verification`',
                SqlFilters::WHERE_STRICT
            )
            ->addFilter(
                'id_customer',
                'v.`id_customer`',
                SqlFilters::WHERE_STRICT
            )
            ->addFilter(
                'customer_email',
                'c.`email`',
                SqlFilters::WHERE_LIKE
            )
            ->addFilter(
                'status',
                'v.`status`',
                SqlFilters::WHERE_STRICT
            )
        ;

        // Apply filters even if empty to ensure proper form initialization
        if ($this->filterApplicator) {
            $this->filterApplicator->apply($qb, $sqlFilters, $filterValues);
        }

        return $qb;
    }
}
