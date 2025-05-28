<?php
/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

declare(strict_types=1);

namespace PrestaShop\Module\Pskyc\Grid\Filters;

use PrestaShop\Module\Pskyc\Grid\Definition\Factory\VerificationGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Search\Filters;

/**
 * Filters for KYC verification grid
 * 
 * Handles search criteria and pagination for verification listings
 */
class VerificationFilters extends Filters
{
    /** @var string */
    protected $filterId = VerificationGridDefinitionFactory::GRID_ID;

    /**
     * {@inheritdoc}
     */
    public static function getDefaults(): array
    {
        return [
            'limit' => 10,
            'offset' => 0,
            'orderBy' => 'id_kyc_verification',
            'sortOrder' => 'desc',
            'filters' => [],
        ];
    }
}