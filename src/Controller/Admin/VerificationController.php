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
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\GridDefinitionFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends FrameworkBundleAdminController
{
    public function indexAction(
        VerificationFilters $filters
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

    public function searchAction(
        Request $request
    ): RedirectResponse {
        /** @var GridDefinitionFactoryInterface $verificationGridDefinitionFactory */
        $verificationGridDefinitionFactory = $this->get('prestashop.module.pskyc.grid.definition.factory.verifications');

        return $this->buildSearchResponse(
            $verificationGridDefinitionFactory,
            $request,
            VerificationGridDefinitionFactory::GRID_ID,
            'ps_pskyc_verification_index'
        );
    }

    public function viewAction(
        int $verificationId
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

    public function approveAction(int $verificationId, Request $request): Response
    {
        $note = $request->request->get('admin_note');
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
        $verificationRepository->updateStatus($verificationId, 'approved', $note);

        $this->addFlash('success', $this->trans('Verification approved.', 'Modules.Pskyc.Admin'));
        return $this->redirectToRoute('ps_pskyc_verification_view', ['verificationId' => $verificationId]);
    }

    public function rejectAction(int $verificationId, Request $request): Response
    {
        $note = $request->request->get('admin_note');
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
        $verificationRepository->updateStatus($verificationId, 'rejected', $note);

        $this->addFlash('success', $this->trans('Verification rejected.', 'Modules.Pskyc.Admin'));
        return $this->redirectToRoute('ps_pskyc_verification_view', ['verificationId' => $verificationId]);
    }

    public function requestInfoAction(int $verificationId, Request $request): Response
    {
        $note = $request->request->get('admin_note');
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
        $verificationRepository->updateStatus($verificationId, 'requested_more_info', $note);

        $this->addFlash('success', $this->trans('Requested more information from customer.', 'Modules.Pskyc.Admin'));
        return $this->redirectToRoute('ps_pskyc_verification_view', ['verificationId' => $verificationId]);
    }

    public function updateNoteAction(int $verificationId, Request $request): Response
    {
        $note = $request->request->get('admin_note');
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
        $verificationRepository->updateAdminNote($verificationId, $note);

        $this->addFlash('success', $this->trans('Note updated.', 'Modules.Pskyc.Admin'));
        return $this->redirectToRoute('ps_pskyc_verification_view', ['verificationId' => $verificationId]);
    }

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
                'Date Updated'
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
                    $log['date_upd']
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

    private function getToolbarButtons(): array
    {
        return [
            'export_logs' => [
                'href' => $this->generateUrl('ps_pskyc_verification_export_logs'),
                'desc' => $this->trans('Export Verification Logs', 'Modules.Pskyc.Admin'),
                'icon' => 'cloud_download',
                'class' => 'btn btn-outline-secondary'
            ]
        ];
    }
}