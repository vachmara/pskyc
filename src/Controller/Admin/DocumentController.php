<?php

namespace PrestaShop\Module\Pskyc\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

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
}