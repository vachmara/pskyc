<?php
/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

namespace PrestaShop\Module\Pskyc\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use PrestaShop\Module\Pskyc\Service\VerificationService;
use PrestaShop\Module\Pskyc\Service\DocumentService;
use PrestaShop\Module\Pskyc\Service\NotificationService;
use PrestaShopLogger;

/**
 * Admin controller for KYC verification management
 * 
 * Handles listing, viewing, and managing customer KYC verifications
 * 
 * @Route("/pskyc/verification", name="admin_pskyc_verification_")
 */
class VerificationController extends FrameworkBundleAdminController
{
  /**
   * List all KYC verifications
   *
   * @Route("/", name="list", methods={"GET"})
   */
  public function listAction(Request $request): Response
  {
    try {
      /** @var VerificationService $verificationService */
      $verificationService = $this->get('PrestaShop\Module\Pskyc\Service\VerificationService');

      // Get filters from request
      $filters = [
        'status' => $request->query->get('status'),
        'customer_id' => $request->query->get('customer_id'),
        'date_from' => $request->query->get('date_from'),
        'date_to' => $request->query->get('date_to'),
      ];

      // Remove empty filters
      $filters = array_filter($filters, function ($value) {
        return !empty($value);
      });

      // Pagination
      $page = max(1, (int) $request->query->get('page', 1));
      $limit = 25;
      $offset = ($page - 1) * $limit;

      // Get verifications with pagination
      [
        'verifications' => $verifications,
        'total_count' => $totalCount,
      ] = $verificationService->getVerifications($filters, $limit, $offset);

      $totalPages = ceil($totalCount / $limit);

      // Get status counts for filters
      $statusCounts = $verificationService->getStatusCounts();

      if (empty($statusCounts)) {
        $statusCounts = [
          'pending' => 0,
          'under_review' => 0,
          'approved' => 0,
          'rejected' => 0,
          'expired' => 0,
          'requested_more_info' => 0,
        ];
      }

      return $this->render('@Modules/pskyc/views/templates/admin/verification/list.html.twig', [
        'verifications' => $verifications,
        'filters' => $filters ?? [],
        'pagination' => [
          'current_page' => $page,
          'total_pages' => $totalPages,
          'total_count' => $totalCount,
          'limit' => $limit,
        ],
        'status_counts' => $statusCounts,
        'layoutTitle' => $this->trans('KYC Verifications', 'Modules.Pskyc.Admin'),
        'requireBulkActions' => true,
        'showContentHeader' => true,
        'enableSidebar' => true,
        'help_link' => false,
      ]);

    } catch (\Exception $e) {
      PrestaShopLogger::addLog('KYC Verification List Error: ' . $e->getMessage(), 3, null, 'Pskyc');

      return $this->render('@Modules/pskyc/views/templates/admin/verification/error.html.twig', [
        'layoutTitle' => $this->trans('KYC Verifications', 'Modules.Pskyc.Admin'),
      ]);
    }
  }

  /**
   * View specific verification details
   *
   * @Route("/{id}", name="view", methods={"GET"}, requirements={"id"="\d+"})
   */
  public function viewAction(Request $request, int $id): Response
  {
    try {
      /** @var VerificationService $verificationService */
      $verificationService = $this->get('PrestaShop\Module\Pskyc\Service\VerificationService');

      $verification = $verificationService->getVerificationWithDocuments($id);

      if (!$verification) {
        $this->addFlash('error', $this->trans(
          'Verification not found.',
          'Modules.Pskyc.Admin'
        ));

        return $this->redirectToRoute('admin_pskyc_verification_list');
      }

      // Get customer information
      $customer = $verificationService->getCustomerInfo($verification['id_customer']);

      return $this->render('@Modules/pskyc/views/templates/admin/verification/view.html.twig', [
        'verification' => $verification,
        'customer' => $customer,
        'documents' => $verification['documents'] ?? [],
        'layoutTitle' => $this->trans(
          'Verification #%id%',
          ['%id%' => $id],
          'Modules.Pskyc.Admin'
        ),
        'showContentHeader' => true,
        'enableSidebar' => true,
        'help_link' => false,
      ]);

    } catch (\Exception $e) {
      PrestaShopLogger::addLog('KYC Verification View Error: ' . $e->getMessage(), 3, null, 'Pskyc');

      $this->addFlash('error', $this->trans(
        'An error occurred while loading the verification.',
        'Modules.Pskyc.Admin'
      ));

      return $this->redirectToRoute('admin_pskyc_verification_list');
    }
  }

  /**
   * Update verification status
   *
   * @Route("/{id}/status", name="update_status", methods={"POST"}, requirements={"id"="\d+"})
   */
  public function updateStatusAction(Request $request, int $id): Response
  {
    try {
      /** @var VerificationService $verificationService */
      $verificationService = $this->get('PrestaShop\Module\Pskyc\Service\VerificationService');

      /** @var NotificationService $notificationService */
      $notificationService = $this->get('PrestaShop\Module\Pskyc\Service\NotificationService');

      $verification = $verificationService->getVerification($id);

      if (!$verification) {
        $this->addFlash('error', $this->trans(
          'Verification not found.',
          'Modules.Pskyc.Admin'
        ));

        return $this->redirectToRoute('admin_pskyc_verification_list');
      }

      $newStatus = $request->request->get('status');
      $adminNote = $request->request->get('admin_note', '');

      // Validate status
      $allowedStatuses = ['pending', 'under_review', 'approved', 'rejected', 'requested_more_info'];
      if (!in_array($newStatus, $allowedStatuses)) {
        $this->addFlash('error', $this->trans(
          'Invalid status provided.',
          'Modules.Pskyc.Admin'
        ));

        return $this->redirectToRoute('admin_pskyc_verification_view', ['id' => $id]);
      }

      // Update verification
      $updateData = [
        'status' => $newStatus,
        'admin_note' => $adminNote,
        'date_reviewed' => date('Y-m-d H:i:s'),
        'reviewed_by' => $this->getUser()->getId(),
      ];

      $result = $verificationService->updateVerification($id, $updateData);

      if ($result) {
        // Send notification to customer
        $notificationService->sendStatusChangeNotification($verification, $newStatus, $adminNote);

        $this->addFlash('success', $this->trans(
          'Verification status updated successfully.',
          'Modules.Pskyc.Admin'
        ));

        // Log the action
        PrestaShopLogger::addLog(
          sprintf('KYC Verification #%d status changed to %s by admin %d', $id, $newStatus, $this->getUser()->getId()),
          1,
          null,
          'Pskyc'
        );
      } else {
        $this->addFlash('error', $this->trans(
          'Failed to update verification status.',
          'Modules.Pskyc.Admin'
        ));
      }

      return $this->redirectToRoute('admin_pskyc_verification_view', ['id' => $id]);

    } catch (\Exception $e) {
      PrestaShopLogger::addLog('KYC Status Update Error: ' . $e->getMessage(), 3, null, 'Pskyc');

      $this->addFlash('error', $this->trans(
        'An error occurred while updating the verification.',
        'Modules.Pskyc.Admin'
      ));

      return $this->redirectToRoute('admin_pskyc_verification_view', ['id' => $id]);
    }
  }

  /**
   * Download document
   *
   * @Route("/{id}/document/{documentId}/download", name="download_document", methods={"GET"}, requirements={"id"="\d+", "documentId"="\d+"})
   */
  public function downloadDocumentAction(Request $request, int $id, int $documentId): Response
  {
    try {
      /** @var DocumentService $documentService */
      $documentService = $this->get('PrestaShop\Module\Pskyc\Service\DocumentService');

      /** @var VerificationService $verificationService */
      $verificationService = $this->get('PrestaShop\Module\Pskyc\Service\VerificationService');

      // Verify verification exists and belongs to the document
      $verification = $verificationService->getVerification($id);
      if (!$verification) {
        throw new \Exception('Verification not found');
      }

      // Get and decrypt document
      $documentPath = $documentService->getDecryptedDocumentPath($documentId, $id);

      if (!$documentPath || !file_exists($documentPath)) {
        $this->addFlash('error', $this->trans(
          'Document not found or could not be decrypted.',
          'Modules.Pskyc.Admin'
        ));

        return $this->redirectToRoute('admin_pskyc_verification_view', ['id' => $id]);
      }

      // Get document info
      $document = $documentService->getDocumentInfo($documentId);

      // Log download
      PrestaShopLogger::addLog(
        sprintf('KYC Document #%d downloaded by admin %d', $documentId, $this->getUser()->getId()),
        1,
        null,
        'Pskyc'
      );

      // Return file response
      $response = new Response();
      $response->headers->set('Content-Type', $document['mime_type'] ?? 'application/octet-stream');
      $response->headers->set('Content-Disposition', 'attachment; filename="' . ($document['original_filename'] ?? 'document') . '"');
      $response->headers->set('Content-Length', filesize($documentPath));
      $response->setContent(file_get_contents($documentPath));

      // Clean up temporary decrypted file
      if (strpos($documentPath, 'temp_') !== false) {
        @unlink($documentPath);
      }

      return $response;

    } catch (\Exception $e) {
      PrestaShopLogger::addLog('KYC Document Download Error: ' . $e->getMessage(), 3, null, 'Pskyc');

      $this->addFlash('error', $this->trans(
        'An error occurred while downloading the document.',
        'Modules.Pskyc.Admin'
      ));

      return $this->redirectToRoute('admin_pskyc_verification_view', ['id' => $id]);
    }
  }

  /**
   * Bulk actions for verifications
   *
   * @Route("/bulk", name="bulk_action", methods={"POST"})
   */
  public function bulkActionAction(Request $request): JsonResponse
  {
    try {
      $action = $request->request->get('action');
      $verificationIds = $request->request->get('verification_ids', []);

      if (empty($verificationIds) || !is_array($verificationIds)) {
        return new JsonResponse([
          'success' => false,
          'message' => $this->trans('No verifications selected.', 'Modules.Pskyc.Admin')
        ]);
      }

      /** @var VerificationService $verificationService */
      $verificationService = $this->get('PrestaShop\Module\Pskyc\Service\VerificationService');

      $successCount = 0;
      $totalCount = count($verificationIds);

      switch ($action) {
        case 'mark_reviewed':
          foreach ($verificationIds as $verificationId) {
            if (
              $verificationService->updateVerification($verificationId, [
                'status' => 'under_review',
                'date_reviewed' => date('Y-m-d H:i:s'),
                'reviewed_by' => $this->getUser()->getId()
              ])
            ) {
              $successCount++;
            }
          }
          break;

        case 'delete':
          foreach ($verificationIds as $verificationId) {
            if ($verificationService->deleteVerification($verificationId)) {
              $successCount++;
            }
          }
          break;

        default:
          return new JsonResponse([
            'success' => false,
            'message' => $this->trans('Invalid action.', 'Modules.Pskyc.Admin')
          ]);
      }

      return new JsonResponse([
        'success' => true,
        'message' => $this->trans(
          '%success% out of %total% verifications processed successfully.',
          ['%success%' => $successCount, '%total%' => $totalCount],
          'Modules.Pskyc.Admin'
        )
      ]);

    } catch (\Exception $e) {
      PrestaShopLogger::addLog('KYC Bulk Action Error: ' . $e->getMessage(), 3, null, 'Pskyc');

      return new JsonResponse([
        'success' => false,
        'message' => $this->trans(
          'An error occurred while processing the bulk action.',
          'Modules.Pskyc.Admin'
        )
      ]);
    }
  }

  /**
   * Get verification statistics for dashboard
   *
   * @Route("/stats", name="stats", methods={"GET"})
   */
  public function statsAction(Request $request): JsonResponse
  {
    try {
      /** @var VerificationService $verificationService */
      $verificationService = $this->get('PrestaShop\Module\Pskyc\Service\VerificationService');

      $stats = $verificationService->getVerificationStats();

      return new JsonResponse([
        'success' => true,
        'stats' => $stats
      ]);

    } catch (\Exception $e) {
      PrestaShopLogger::addLog('KYC Stats Error: ' . $e->getMessage(), 3, null, 'Pskyc');

      return new JsonResponse([
        'success' => false,
        'message' => $this->trans(
          'An error occurred while loading statistics.',
          'Modules.Pskyc.Admin'
        )
      ]);
    }
  }
}