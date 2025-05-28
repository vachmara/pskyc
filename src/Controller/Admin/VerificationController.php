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

    public function approveAction(
        int $verificationId
    ): RedirectResponse {
        /** @var VerificationRepository $verificationRepository */
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
        
        try {
            $verification = $verificationRepository->findOneById($verificationId);
            
            if (null !== $verification) {
                $verificationRepository->updateStatus($verificationId, 'approved');
                
                $this->addFlash(
                    'success',
                    $this->trans('Verification successfully approved.', 'Modules.Pskyc.Admin')
                );
            } else {
                $this->addFlash(
                    'error',
                    $this->trans(
                        'Cannot find verification %verification%',
                        'Modules.Pskyc.Admin',
                        ['%verification%' => $verificationId]
                    ),
                );
            }
        } catch (\Exception $e) {
            $this->addFlash(
                'error',
                $this->trans('An error occurred while approving the verification.', 'Modules.Pskyc.Admin')
            );
        }

        return $this->redirectToRoute('ps_pskyc_verification_index');
    }

    public function rejectAction(
        int $verificationId
    ): RedirectResponse {
        /** @var VerificationRepository $verificationRepository */
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
        
        try {
            $verification = $verificationRepository->findOneById($verificationId);
            
            if (null !== $verification) {
                $verificationRepository->updateStatus($verificationId, 'rejected');
                
                $this->addFlash(
                    'success',
                    $this->trans('Verification successfully rejected.', 'Modules.Pskyc.Admin')
                );
            } else {
                $this->addFlash(
                    'error',
                    $this->trans(
                        'Cannot find verification %verification%',
                        'Modules.Pskyc.Admin',
                        ['%verification%' => $verificationId]
                    ),
                );
            }
        } catch (\Exception $e) {
            $this->addFlash(
                'error',
                $this->trans('An error occurred while rejecting the verification.', 'Modules.Pskyc.Admin')
            );
        }

        return $this->redirectToRoute('ps_pskyc_verification_index');
    }

    public function deleteAction(
        int $verificationId
    ): RedirectResponse {
        /** @var VerificationRepository $verificationRepository */
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
        
        try {
            $verification = $verificationRepository->findOneById($verificationId);
            
            if (null !== $verification) {
                $verificationRepository->delete($verificationId);
                
                $this->addFlash(
                    'success',
                    $this->trans('Successful deletion.', 'Admin.Notifications.Success'),
                );
            } else {
                $this->addFlash(
                    'error',
                    $this->trans(
                        'Cannot find verification %verification%',
                        'Modules.Pskyc.Admin',
                        ['%verification%' => $verificationId]
                    ),
                );
            }
        } catch (\Exception $e) {
            $this->addFlash(
                'error',
                $this->trans('An error occurred while deleting the verification.', 'Modules.Pskyc.Admin')
            );
        }

        return $this->redirectToRoute('ps_pskyc_verification_index');
    }

    public function deleteBulkAction(
        Request $request
    ): RedirectResponse {
        /** @var VerificationRepository $verificationRepository */
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
        
        $verificationIds = $request->request->all('verification_bulk');
        
        if (!empty($verificationIds)) {
            try {
                $deletedCount = 0;
                foreach ($verificationIds as $verificationId) {
                    $verification = $verificationRepository->findOneById((int) $verificationId);
                    if (null !== $verification) {
                        $verificationRepository->delete((int) $verificationId);
                        $deletedCount++;
                    }
                }

                if ($deletedCount > 0) {
                    $this->addFlash(
                        'success',
                        $this->trans('The selection has been successfully deleted.', 'Admin.Notifications.Success')
                    );
                } else {
                    $this->addFlash(
                        'warning',
                        $this->trans('No verifications were deleted.', 'Modules.Pskyc.Admin')
                    );
                }
            } catch (\Exception $e) {
                $this->addFlash(
                    'error',
                    $this->trans('An error occurred while deleting verifications.', 'Modules.Pskyc.Admin')
                );
            }
        }

        return $this->redirectToRoute('ps_pskyc_verification_index');
    }

    public function approveBulkAction(
        Request $request
    ): RedirectResponse {
        /** @var VerificationRepository $verificationRepository */
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
        
        $verificationIds = $request->request->all('verification_bulk');
        
        if (!empty($verificationIds)) {
            try {
                $approvedCount = 0;
                foreach ($verificationIds as $verificationId) {
                    $verification = $verificationRepository->findOneById((int) $verificationId);
                    if (null !== $verification) {
                        $verificationRepository->updateStatus((int) $verificationId, 'approved');
                        $approvedCount++;
                    }
                }

                if ($approvedCount > 0) {
                    $this->addFlash(
                        'success',
                        $this->trans('The selection has been successfully approved.', 'Modules.Pskyc.Admin')
                    );
                } else {
                    $this->addFlash(
                        'warning',
                        $this->trans('No verifications were approved.', 'Modules.Pskyc.Admin')
                    );
                }
            } catch (\Exception $e) {
                $this->addFlash(
                    'error',
                    $this->trans('An error occurred while approving verifications.', 'Modules.Pskyc.Admin')
                );
            }
        }

        return $this->redirectToRoute('ps_pskyc_verification_index');
    }

    public function rejectBulkAction(
        Request $request
    ): RedirectResponse {
        /** @var VerificationRepository $verificationRepository */
        $verificationRepository = $this->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
        
        $verificationIds = $request->request->all('verification_bulk');
        
        if (!empty($verificationIds)) {
            try {
                $rejectedCount = 0;
                foreach ($verificationIds as $verificationId) {
                    $verification = $verificationRepository->findOneById((int) $verificationId);
                    if (null !== $verification) {
                        $verificationRepository->updateStatus((int) $verificationId, 'rejected');
                        $rejectedCount++;
                    }
                }

                if ($rejectedCount > 0) {
                    $this->addFlash(
                        'success',
                        $this->trans('The selection has been successfully rejected.', 'Modules.Pskyc.Admin')
                    );
                } else {
                    $this->addFlash(
                        'warning',
                        $this->trans('No verifications were rejected.', 'Modules.Pskyc.Admin')
                    );
                }
            } catch (\Exception $e) {
                $this->addFlash(
                    'error',
                    $this->trans('An error occurred while rejecting verifications.', 'Modules.Pskyc.Admin')
                );
            }
        }

        return $this->redirectToRoute('ps_pskyc_verification_index');
    }

    private function getToolbarButtons(): array
    {
        return [
            'export' => [
                'desc' => $this->trans('Export verifications', 'Modules.Pskyc.Admin'),
                'icon' => 'cloud_download',
                'href' => $this->generateUrl('ps_pskyc_verification_export'),
            ],
        ];
    }
}