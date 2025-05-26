<?php
namespace PrestaShop\Module\Pskyc\Repository;

use Doctrine\DBAL\Connection;
use Prestashop\Module\Pskyc\Entity\Document;

class DocumentRepository
{
  /**
   * @var Connection
   */
  private $connection;

  /**
   * DocumentRepository constructor.
   *
   * @param Connection $connection
   */
  public function __construct(Connection $connection)
  {
    $this->connection = $connection;
  }

  /**
   * Find document by id
   *
   * @param int $id
   *
   * @return Document|null
   */
  public function findDocumentById(int $id): ?Document
  {
    $qb = $this->connection->createQueryBuilder();
    $query = $qb->select('*')
      ->from(_DB_PREFIX_ . 'kyc_document', 'd')
      ->where('d.id_kyc_document = :id')
      ->setParameter('id', $id)
    ;

    $result = $query->execute();

    if ($result->rowCount() === 0) {
      return null;
    }

    return $result->fetchObject(Document::class);
  }

  /**
   * Find documents by verification id
   *
   * @param int $verificationId
   *
   * @return Document[]
   */
  public function findDocumentsByVerificationId(int $verificationId): array
  {
    $qb = $this->connection->createQueryBuilder();
    $query = $qb->select('*')
      ->from(_DB_PREFIX_ . 'kyc_document', 'd')
      ->where('d.id_kyc_verification = :verificationId')
      ->setParameter('verificationId', $verificationId)
    ;

    $result = $query->execute();

    return $result->fetchAllAssociative();
  }

  /**
   * Create a new document record
   *
   * @param array $data
   *
   * @return int The new document ID
   */
  public function create(array $data): int
  {
    $qb = $this->connection->createQueryBuilder();
    $qb->insert(_DB_PREFIX_ . 'kyc_document')
      ->values([
        'id_kyc_verification' => ':id_kyc_verification',
        'document_type' => ':document_type',
        'category' => ':category',
        'filename' => ':filename',
        'original_name' => ':original_name',
        'filesize' => ':filesize',
        'mime_type' => ':mime_type',
        'sha256_hash' => ':sha256_hash',
        'encryption_key' => ':encryption_key',
        'iv' => ':iv',
        'status' => ':status',
        'date_uploaded' => ':date_uploaded'
      ])
      ->setParameters($data);

    $qb->execute();

    return (int) $this->connection->lastInsertId();
  }

  /**
   * Update document status
   *
   * @param int $documentId
   * @param string $status
   * @param string|null $reviewNote
   * @param int|null $employeeId
   *
   * @return bool
   */
  public function updateStatus(int $documentId, string $status, ?string $reviewNote = null, ?int $employeeId = null): bool
  {
    $qb = $this->connection->createQueryBuilder();
    $qb->update(_DB_PREFIX_ . 'kyc_document')
      ->set('status', ':status')
      ->set('review_note', ':review_note')
      ->set('id_employee_reviewed', ':employee_id')
      ->where('id_kyc_document = :document_id')
      ->setParameters([
        'status' => $status,
        'review_note' => $reviewNote,
        'employee_id' => $employeeId,
        'document_id' => $documentId
      ]);

    return (bool) $qb->execute();
  }

  /**
   * Delete document by ID
   *
   * @param int $documentId
   *
   * @return bool
   */
  public function delete(int $documentId): bool
  {
    $qb = $this->connection->createQueryBuilder();
    $qb->delete(_DB_PREFIX_ . 'kyc_document')
      ->where('id_kyc_document = :document_id')
      ->setParameter('document_id', $documentId);

    return (bool) $qb->execute();
  }
  
  

}