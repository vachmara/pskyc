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

    private function getToolbarButtons(): array
    {
        return [
        ];
    }
}