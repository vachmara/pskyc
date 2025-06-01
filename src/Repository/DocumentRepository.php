<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * @author Valentin Chmara
 * @copyright Valentin Chmara
 * @license MIT
 */

namespace PrestaShop\Module\Pskyc\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}
use Doctrine\DBAL\Connection;

/**
 * Class DocumentRepository
 *
 * Repository for managing KYC document records in the database
 * Provides methods for CRUD operations on document data
 */
class DocumentRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * DocumentRepository constructor
     *
     * @param Connection $connection Database connection instance
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Find document by ID
     *
     * Retrieves a single document record by its unique identifier
     *
     * @param int $id The document ID to search for
     *
     * @return array|null Document record array or null if not found
     */
    public function findById(int $id): ?array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('*')
            ->from(_DB_PREFIX_ . 'kyc_document')
            ->where('id_kyc_document = :id')
            ->setParameter('id', $id);

        $result = $query->execute();

        if ($result->rowCount() === 0) {
            return null;
        }

        return $result->fetchAssociative();
    }

    /**
     * Find documents by verification ID
     *
     * Retrieves all documents associated with a specific verification request
     *
     * @param int $verificationId The verification ID to search for
     *
     * @return array Array of document records
     */
    public function findByVerificationId(int $verificationId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('*')
            ->from(_DB_PREFIX_ . 'kyc_document')
            ->where('id_kyc_verification = :verification_id')
            ->setParameter('verification_id', $verificationId)
            ->orderBy('date_uploaded', 'ASC');

        $result = $query->execute();

        return $result->fetchAllAssociative();
    }

    /**
     * Create a new document record
     *
     * Inserts a new document record into the database
     *
     * @param array $data Document data array containing required fields
     *
     * @return int The ID of the newly created document
     */
    public function create(array $data): int
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->insert(_DB_PREFIX_ . 'kyc_document')
            ->values([
                'id_kyc_verification' => ':verification_id',
                'type' => ':type',
                'side' => ':side',
                'filename' => ':filename',
                'filesize' => ':filesize',
                'mime' => ':mime',
                'sha256' => ':sha256',
                'iv' => ':iv',
                'encrypted' => ':encrypted',
                'date_uploaded' => 'NOW()',
                'expires_at' => ':expires_at',
            ])
            ->setParameters($data);

        $qb->execute();

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update document status
     *
     * Updates document metadata such as status, review notes, and employee information
     *
     * @param int $documentId The document ID to update
     * @param string $status New status for the document
     * @param string|null $reviewNote Optional review note from admin
     * @param int|null $employeeId Optional employee ID who performed the review
     *
     * @return bool True if update was successful, false otherwise
     */
    public function updateStatus(int $documentId, string $status, ?string $reviewNote, ?int $employeeId): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->update(_DB_PREFIX_ . 'kyc_document')
            ->set('status', ':status')
            ->where('id_kyc_document = :document_id')
            ->setParameter('status', $status)
            ->setParameter('document_id', $documentId);

        if ($reviewNote !== null) {
            $qb->set('review_note', ':review_note')
               ->setParameter('review_note', $reviewNote);
        }

        if ($employeeId !== null) {
            $qb->set('reviewed_by', ':employee_id')
               ->setParameter('employee_id', $employeeId);
        }

        return (bool) $qb->execute();
    }

    /**
     * Delete document by ID
     *
     * Removes a document record from the database
     * Note: This does not delete the physical file, only the database record
     *
     * @param int $documentId The document ID to delete
     *
     * @return bool True if deletion was successful, false otherwise
     */
    public function delete(int $documentId): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->delete(_DB_PREFIX_ . 'kyc_document')
            ->where('id_kyc_document = :document_id')
            ->setParameter('document_id', $documentId);

        return (bool) $qb->execute();
    }

    /**
     * Delete all documents for a verification
     *
     * Removes all document records and files associated with a verification
     *
     * @param int $verificationId The verification ID
     *
     * @return bool True if deletion was successful
     */
    public function deleteByVerificationId(int $verificationId): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->delete(_DB_PREFIX_ . 'kyc_document')
            ->where('id_kyc_verification = :verification_id')
            ->setParameter('verification_id', $verificationId);

        $result = $qb->execute();

        return is_int($result) ? $result >= 0 : false;
    }

    /**
     * Find expired documents
     *
     * Retrieves all documents that have passed their expiration date
     *
     * @return array Array of expired document records
     */
    public function findExpiredDocuments(): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('*')
            ->from(_DB_PREFIX_ . 'kyc_document')
            ->where('expires_at IS NOT NULL')
            ->andWhere('expires_at < NOW()');

        $result = $query->execute();

        return $result->fetchAllAssociative();
    }

    /**
     * Count documents by verification ID
     *
     * Returns the number of documents associated with a verification request
     *
     * @param int $verificationId The verification ID to count documents for
     *
     * @return int Number of documents
     */
    public function countByVerificationId(int $verificationId): int
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('COUNT(*)')
            ->from(_DB_PREFIX_ . 'kyc_document')
            ->where('id_kyc_verification = :verification_id')
            ->setParameter('verification_id', $verificationId);

        $result = $query->execute();

        return (int) $result->fetchOne();
    }

    /**
     * Find documents by verification ID and type
     *
     * Retrieves all documents of a specific type for a verification request
     * Useful for checking if front/back sides are complete
     *
     * @param int $verificationId The verification ID to search for
     * @param string $documentType The document type to filter by
     *
     * @return array Array of document records
     */
    public function findByVerificationIdAndType(int $verificationId, string $documentType): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('*')
            ->from(_DB_PREFIX_ . 'kyc_document')
            ->where('id_kyc_verification = :verification_id')
            ->andWhere('type = :document_type')
            ->setParameter('verification_id', $verificationId)
            ->setParameter('document_type', $documentType)
            ->orderBy('side', 'ASC'); // front comes before back alphabetically

        $result = $query->execute();

        return $result->fetchAllAssociative();
    }

    /**
     * Update status and note for a document
     *
     * @param int $documentId The document ID to update
     * @param string $status New status for the document
     * @param string|null $note Optional note to attach to the document
     *
     * @return bool True if update was successful, false otherwise
     */
    public function updateStatusAndNote(int $documentId, string $status, ?string $note): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->update(_DB_PREFIX_ . 'kyc_document')
            ->set('status', ':status')
            ->where('id_kyc_document = :document_id')
            ->setParameter('status', $status)
            ->setParameter('document_id', $documentId);

        if ($note !== null) {
            $qb->set('admin_note', ':admin_note')
               ->setParameter('admin_note', $note);
        }

        return (bool) $qb->execute();
    }

    /**
     * Update multiple fields for a document
     *
     * @param int $documentId The document ID to update
     * @param array $fields Associative array of fields to update
     *
     * @return bool True if update was successful, false otherwise
     */
    public function updateDocumentFields(int $documentId, array $fields): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->update(_DB_PREFIX_ . 'kyc_document');
        foreach ($fields as $field => $value) {
            $qb->set($field, ':' . $field);
            $qb->setParameter($field, $value);
        }
        $qb->where('id_kyc_document = :document_id')
            ->setParameter('document_id', $documentId);

        return (bool) $qb->execute();
    }
}
