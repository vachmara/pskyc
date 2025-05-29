<?php

namespace PrestaShop\Module\Pskyc\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class DocumentController extends FrameworkBundleAdminController
{
    public function downloadAction(int $documentId, int $preview = 0, Request $request)
    {
        $documentRepository = $this->get('PrestaShop\Module\Pskyc\Repository\DocumentRepository');
        $document = $documentRepository->findById($documentId);

        if (!$document) {
            return new JsonResponse([
                'error' => 'Document not found',
                'documentId' => $documentId
            ], 404);
        }

        // Generate the stored filename (must match upload logic)
        $storedFilename = 'doc_' . $document['id_kyc_document'] . '_' . hash('md5', $document['filename']);
        $filePath = _PS_MODULE_DIR_ . 'pskyc/secure_upload/' . $storedFilename;

        if (!file_exists($filePath)) {
            return new JsonResponse([
                'error' => 'File not found',
                'filename' => $storedFilename
            ], 404);
        }

        // Decrypt file content
        $encryptionService = $this->get('PrestaShop\Module\Pskyc\Service\EncryptionService');
        $encryptedContent = file_get_contents($filePath);

        $decryptedContent = $encryptionService->decrypt(
            $encryptedContent,
            $document['iv'] ?? null,
            $document['sha256'] ?? null
        );

        $response = new Response($decryptedContent);
        $response->headers->set('Content-Type', $document['mime']);
        $disposition = $preview && strpos($document['mime'], 'image/') === 0
            ? 'inline'
            : 'attachment';
        $response->headers->set(
            'Content-Disposition',
            $disposition . '; filename="' . $document['filename'] . '"'
        );

        return $response;
    }

    /**
     * @Route(
     *     "/pskyc/verification/{verificationId}/documents/update-status",
     *     name="ps_pskyc_document_update_status",
     *     methods={"POST"}
     * )
     */
    public function updateStatusAction(int $verificationId, Request $request, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $documentsData = $request->request->get('documents', []);
        $token = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('pskyc_document_update', $token))) {
            $this->addFlash('error', $this->trans('Invalid CSRF token.', 'Modules.Pskyc.Admin'));
            return $this->redirectToRoute('ps_pskyc_verification_view', ['verificationId' => $verificationId]);
        }

        $documentRepository = $this->get('PrestaShop\Module\Pskyc\Repository\DocumentRepository');
        foreach ($documentsData as $docId => $data) {
            $status = $data['status'] ?? 'pending';
            $note = $data['admin_note'] ?? null;
            $documentRepository->updateStatusAndNote((int)$docId, $status, $note);
        }

        // Recalculate verification status
        $verificationService = $this->get('PrestaShop\Module\Pskyc\Service\VerificationService');
        $this->recalculateVerificationStatus($verificationService, $verificationId);

        $this->addFlash('success', $this->trans('Document statuses and notes updated.', 'Modules.Pskyc.Admin'));
        return $this->redirectToRoute('ps_pskyc_verification_view', ['verificationId' => $verificationId]);
    }

    private function recalculateVerificationStatus($verificationService, int $verificationId): void
    {
        $documents = $verificationService->getVerificationWithDocuments($verificationId)['documents'] ?? [];
        $statuses = array_column($documents, 'status');
        if (in_array('request_change', $statuses, true)) {
            $newStatus = 'requested_more_info';
        } elseif (in_array('rejected', $statuses, true)) {
            $newStatus = 'rejected';
        } elseif (count($statuses) && count(array_unique($statuses)) === 1 && $statuses[0] === 'valid') {
            $newStatus = 'approved';
        } elseif (in_array('pending', $statuses, true)) {
            $newStatus = 'under_review';
        } else {
            $newStatus = 'pending';
        }
        $verificationService->updateStatus($verificationId, $newStatus);
    }
}