<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

namespace PrestaShop\Module\Pskyc\Grid\Definition\Factory;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class VerificationGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    const GRID_ID = 'verification';

    /**
     * {@inheritdoc}
     */
    protected function getId()
    {
        return self::GRID_ID;
    }

    /**
     * {@inheritdoc}
     */
    protected function getName()
    {
        return $this->trans('KYC Verifications', [], 'Modules.Pskyc.Admin');
    }

    /**
     * {@inheritdoc}
     */
    protected function getColumns()
    {
        return (new ColumnCollection())
            ->add(
                (new DataColumn('id_kyc_verification'))
                    ->setName($this->trans('ID', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_kyc_verification',
                    ])
            )
            ->add(
                (new DataColumn('id_customer'))
                    ->setName($this->trans('Customer ID', [], 'Modules.Pskyc.Admin'))
                    ->setOptions([
                        'field' => 'id_customer',
                    ])
            )
            ->add(
                (new DataColumn('customer_email'))
                    ->setName($this->trans('Customer Email', [], 'Modules.Pskyc.Admin'))
                    ->setOptions([
                        'field' => 'customer_email',
                    ])
            )
            ->add(
                (new DataColumn('status'))
                    ->setName($this->trans('Status', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'status',
                    ])
            )
            ->add(
                (new DataColumn('date_submitted'))
                    ->setName($this->trans('Date Submitted', [], 'Modules.Pskyc.Admin'))
                    ->setOptions([
                        'field' => 'date_submitted',
                    ])
            )
            ->add(
                (new DataColumn('date_validated'))
                    ->setName($this->trans('Date Validated', [], 'Modules.Pskyc.Admin'))
                    ->setOptions([
                        'field' => 'date_validated',
                    ])
            )
            ->add(
                (new DataColumn('customer_name'))
                    ->setName($this->trans('Customer Name', [], 'Modules.Pskyc.Admin'))
                    ->setOptions([
                        'field' => 'customer_name',
                    ])
            )
            ->add(
                (new DataColumn('documents_count'))
                    ->setName($this->trans('Documents', [], 'Modules.Pskyc.Admin'))
                    ->setOptions([
                        'field' => 'documents_count',
                    ])
            )
            ->add(
                (new ActionColumn('actions'))
                    ->setName($this->trans('Actions', [], 'Admin.Actions'))
                    ->setOptions([
                        'actions' => $this->getRowActions(),
                    ])
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function getFilters()
    {
        return (new FilterCollection())
            ->add(
                (new Filter('id_kyc_verification', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->trans('ID', [], 'Admin.Global'),
                        ],
                    ])
                    ->setAssociatedColumn('id_kyc_verification')
            )
            ->add(
                (new Filter('id_customer', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->trans('Customer ID', [], 'Modules.Pskyc.Admin'),
                        ],
                    ])
                    ->setAssociatedColumn('id_customer')
            )
            ->add(
                (new Filter('customer_email', TextType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'attr' => [
                            'placeholder' => $this->trans('Customer Email', [], 'Modules.Pskyc.Admin'),
                        ],
                    ])
                    ->setAssociatedColumn('customer_email')
            )
            ->add(
                (new Filter('status', ChoiceType::class))
                    ->setTypeOptions([
                        'required' => false,
                        'choices' => [
                            $this->trans('Pending', [], 'Modules.Pskyc.Admin') => 'pending',
                            $this->trans('Under Review', [], 'Modules.Pskyc.Admin') => 'under_review',
                            $this->trans('Approved', [], 'Modules.Pskyc.Admin') => 'approved',
                            $this->trans('Rejected', [], 'Modules.Pskyc.Admin') => 'rejected',
                            $this->trans('Expired', [], 'Modules.Pskyc.Admin') => 'expired',
                            $this->trans('More Info Requested', [], 'Modules.Pskyc.Admin') => 'requested_more_info',
                        ],
                        'placeholder' => $this->trans('All statuses', [], 'Modules.Pskyc.Admin'),
                    ])
                    ->setAssociatedColumn('status')
            )
            ->add(
                (new Filter('actions', SearchAndResetType::class))
                    ->setTypeOptions([
                        'reset_route' => 'admin_common_reset_search_by_filter_id',
                        'reset_route_params' => [
                            'filterId' => self::GRID_ID,
                        ],
                        'redirect_route' => 'ps_pskyc_verification_index',
                    ])
                    ->setAssociatedColumn('actions')
            );
    }

    /**
     * Get row actions for the grid
     *
     * @return RowActionCollectionInterface
     */
    private function getRowActions(): RowActionCollectionInterface
    {
        return (new RowActionCollection())
            ->add(
                (new LinkRowAction('view'))
                    ->setName($this->trans('View', [], 'Admin.Actions'))
                    ->setIcon('zoom_in')
                    ->setOptions([
                        'route' => 'ps_pskyc_verification_view',
                        'route_param_name' => 'verificationId',
                        'route_param_field' => 'id_kyc_verification',
                        'clickable_row' => true,
                    ])
            );
    }
}
