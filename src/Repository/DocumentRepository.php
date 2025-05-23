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
   * Find documents by verification id and type
   *
   * @param int $verificationId
   * @param string $type
   *
   * @return Document[]
   */
  public function findDocumentsByVerificationIdAndType(int $verificationId, string $type): array
  {
    $qb = $this->connection->createQueryBuilder();
    $query = $qb->select('*')
      ->from(_DB_PREFIX_ . 'kyc_document', 'd')
      ->where('d.id_kyc_verification = :verificationId')
      ->andWhere('d.type = :type')
      ->setParameter('verificationId', $verificationId)
      ->setParameter('type', $type)
    ;

    $result = $query->execute();

    return $result->fetchAllAssociative();
  }

}