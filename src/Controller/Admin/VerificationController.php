<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

declare(strict_types=1);

namespace PrestaShop\Module\Pskyc\Controller\Admin;

use PrestaShop\Module\Pskyc\Grid\Definition\Factory\VerificationGridDefinitionFactory;
use PrestaShop\Module\Pskyc\Grid\Filters\VerificationFilters;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Service\Grid\ResponseBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * KYC Verification Admin Controller
 *
 * Manages KYC verification records in the PrestaShop back office.
 * Provides grid listing, search, view, and status management functionality.
 */
class VerificationController extends FrameworkBundleAdminController
{
    /**
     * Display KYC verifications listing page with grid
     *
     * @Route(
     *     "/pskyc/verification",
     *     name="ps_pskyc_verification_index",
     *     methods={"GET", "POST"}
     * )
     *
     * @param VerificationFilters $filters Grid filters for pagination and search
     *
     * @return Response Rendered verification grid page
     */
    public function indexAction(
        VerificationFilters $filters,
    ): Response {
        /** @var GridFactoryInterface $verificationGridFactory */
        $verificationGridFactory = $this->get('prestashop.module.pskyc.grid.factory.verifications');

        return $this->render(
            '@Modules/pskyc/views/templates/admin/verification/index.html.twig',
            [
                'enableSidebar' => true,
                'layoutTitle' => $this->trans('KYC Verifications', 'Modules.Pskyc.Admin'),
                'layoutHeaderToolbarBtn' => $this->getToolbarButtons(),
                'verificationGrid' => $this->presentGrid($verificationGridFactory->getGrid($filters)),
            ]
        );
    }

    /**
     * Handle grid search form submission
     *
     * @Route(
     *     "/pskyc/verification/search",
     *     name="ps_pskyc_verification_search",
     *     methods={"POST"}
     * )
     *
     * @param Request $request HTTP request containing search filters
     *
     * @return RedirectResponse Redirect to index with applied filters
     */
    public function searchAction(Request $request): RedirectResponse
    {
        /** @var VerificationGridDefinitionFactory $verificationGridDefinitionFactory */
        $verificationGridDefinitionFactory = $this->get('prestashop.module.pskyc.grid.definition.factory.verifications');

        /** @var ResponseBuilder $responseBuilder */
        $responseBuilder = $this->get('prestashop.bundle.grid.response_builder');

        return $responseBuilder->buildSearchResponse(
            $verificationGridDefinitionFactory,
            $request,
            VerificationGridDefinitionFactory::GRID_ID,
            'ps_pskyc_verification_index'
        );
    }

    /**
     * View detailed verification information
     *
     * @Route(
     *     "/pskyc/verification/{verificationId}/view",
     *     name="ps_pskyc_verification_view",
     *     methods={"GET"},
     *     requirements={"verificationId"="\d+"}
     * )
     *
     * @param int $verificationId The verification ID to view
     *
     * @return Response Rendered verification detail page
     */
    public function viewAction(
        int $verificationId,
    ): Response {
        /** @var VerificationRepository $verificationRepository */
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');

        try {
            $verification = $verificationRepository->findOneById($verificationId);

            // Fetch customer info using CustomerRepository
            $customerRepository = $this->get('PrestaShop\Module\Pskyc\Repository\CustomerRepository');
            $customer = $customerRepository->getCustomerData($verification['id_customer']);
            $verification['customer'] = $customer;

            $documentRepository = $this->get('PrestaShop\Module\Pskyc\Repository\DocumentRepository');
            $documents = $documentRepository->findByVerificationId($verificationId);
            $verification['documents'] = $documents;
        } catch (\Exception $e) {
            $this->addFlash(
                'error',
                $this->trans(
                    'Cannot find verification %verification%',
                    'Modules.Pskyc.Admin',
                    ['%verification%' => $verificationId]
                ),
            );

            return $this->redirectToRoute('ps_pskyc_verification_index');
        }

        return $this->render(
            '@Modules/pskyc/views/templates/admin/verification/view.html.twig',
            [
                'enableSidebar' => true,
                'layoutTitle' => $this->trans('View KYC Verification', 'Modules.Pskyc.Admin'),
                'layoutHeaderToolbarBtn' => $this->getToolbarButtons(),
                'verification' => $verification,
            ]
        );
    }

    /**
     * Update admin note for a verification
     *
     * @Route(
     *     "/pskyc/verification/{verificationId}/update-note",
     *     name="ps_pskyc_verification_update_note",
     *     methods={"POST"},
     *     requirements={"verificationId"="\d+"}
     * )
     *
     * @param int $verificationId The verification ID to update note for
     * @param Request $request HTTP request containing admin note
     *
     * @return Response Redirect response with success message
     */
    public function updateNoteAction(int $verificationId, Request $request): Response
    {
        $note = $request->request->get('admin_note');
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
        $verificationRepository->updateAdminNote($verificationId, $note);

        $this->addFlash('success', $this->trans('Note updated.', 'Modules.Pskyc.Admin'));

        return $this->redirectToRoute('ps_pskyc_verification_view', ['verificationId' => $verificationId]);
    }

    /**
     * Export verification logs as CSV
     *
     * @Route(
     *     "/pskyc/verification/export-logs",
     *     name="ps_pskyc_verification_export_logs",
     *     methods={"GET"}
     * )
     *
     * @return Response Streamed CSV file response
     */
    public function exportLogsAction(): Response
    {
        /** @var VerificationRepository $verificationRepository */
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');

        try {
            $verifications = $verificationRepository->findAllForExport();
        } catch (\Exception $e) {
            $this->addFlash('error', $this->trans('Error retrieving verification logs.', 'Modules.Pskyc.Admin'));

            return $this->redirectToRoute('ps_pskyc_verification_index');
        }

        $response = new StreamedResponse();
        $response->setCallback(function () use ($verifications) {
            $handle = fopen('php://output', 'w+');

            // Add CSV headers
            fputcsv($handle, [
                'Log ID',
                'Verification ID',
                'Action',
                'Message',
                'Customer ID',
                'Customer Email',
                'Employee Name',
                'Verification Status',
                'IP Address',
                'User Agent',
                'Date Added',
                'Date Updated',
            ]);

            // Add data rows
            foreach ($verifications as $log) {
                $employeeName = '';
                if (!empty($log['employee_firstname']) || !empty($log['employee_lastname'])) {
                    $employeeName = trim($log['employee_firstname'] . ' ' . $log['employee_lastname']);
                }

                fputcsv($handle, [
                    $log['log_id'],
                    $log['verification_id'],
                    $log['action'],
                    $log['message'] ?? '',
                    $log['id_customer'],
                    $log['customer_email'] ?? 'N/A',
                    $employeeName ?: 'System',
                    $log['verification_status'] ?? '',
                    $log['ip_address'] ?? '',
                    $log['user_agent'] ?? '',
                    $log['date_add'],
                    $log['date_upd'],
                ]);
            }

            fclose($handle);
        });

        $filename = sprintf('kyc_verification_logs_%s.csv', date('Y-m-d_H-i-s'));

        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    /**
     * Get toolbar buttons for the verification pages
     *
     * @return array Array of toolbar button configurations
     */
    private function getToolbarButtons(): array
    {
        return [
            'export_logs' => [
                'href' => $this->generateUrl('ps_pskyc_verification_export_logs'),
                'desc' => $this->trans('Export Verification Logs', 'Modules.Pskyc.Admin'),
                'icon' => 'cloud_download',
                'class' => 'btn btn-outline-secondary',
            ],
        ];
    }
}
