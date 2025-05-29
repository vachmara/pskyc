<?php

namespace PrestaShop\Module\Pskyc\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

class DocumentController extends FrameworkBundleAdminController
{
    public function downloadAction(int $documentId, int $preview = 0, Request $request)
    {
        $documentRepository = $this->get('PrestaShop\Module\Pskyc\Repository\DocumentRepository');
        $document = $documentRepository->findById($documentId);

        if (!$document) {
            throw $this->createNotFoundException('Document not found');
        }

        $filePath = _PS_MODULE_DIR_ . 'pskyc/secure_upload/' . $document['filename'];

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File not found');
        }

        // Decrypt file content
        $encryptionService = $this->get('PrestaShop\Module\Pskyc\Service\EncryptionService');
        $encryptedContent = file_get_contents($filePath);

        // You may need to pass IV and other params depending on your encryption implementation
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