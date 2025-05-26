<?php
namespace PrestaShop\Module\Pskyc\Repository;

use Doctrine\DBAL\Connection;
use Prestashop\Module\Pskyc\Entity\Verification;

class VerificationRepository
{
  /**
   * @var Connection
   */
  private $connection;

  /**
   * VerificationRepository constructor.
   *
   * @param Connection $connection
   */
  public function __construct(Connection $connection)
  {
    $this->connection = $connection;
  }

  /**
   * Find verification by id
   *
   * @param int $id
   *
   * @return Verification|null
   */
  public function findVerificationById(int $id): ?Verification
  {
    $qb = $this->connection->createQueryBuilder();
    $query = $qb->select('*')
      ->from(_DB_PREFIX_ . 'kyc_verification', 'v')
      ->where('v.id_kyc_verification = :id')
      ->setParameter('id', $id)
    ;

    $result = $query->execute();

    if ($result->rowCount() === 0) {
      return null;
    }

    return $result->fetchObject(Verification::class);
  }

  /**
   * Update verification status
   * @param int $id
   * @param string $status
   * @param string|null $adminNote
   * @return bool
   */
  public function updateVerificationStatus(int $id, string $status, ?string $adminNote = null): bool
  {
    $qb = $this->connection->createQueryBuilder();
    $qb->update(_DB_PREFIX_ . 'kyc_verification')
      ->set('status', ':status')
      ->set('admin_note', ':admin_note')
      ->where('id_kyc_verification = :id')
      ->setParameter('status', $status)
      ->setParameter('admin_note', $adminNote)
      ->setParameter('id', $id);

    return (bool) $qb->execute();
  }

  /**
   * Get the latest verification for a customer
   * @param int $customerId
   * @return Verification|null
   */
  public function getLatestByCustomerId(int $customerId): ?Verification
  {
    $qb = $this->connection->createQueryBuilder();
    $query = $qb->select('*')
      ->from(_DB_PREFIX_ . 'kyc_verification', 'v')
      ->where('v.id_customer = :customerId')
      ->orderBy('v.date_add', 'DESC')
      ->setParameter('customerId', $customerId)
      ->setMaxResults(1)
    ;

    $result = $query->execute();

    if ($result->rowCount() === 0) {
      return null;
    }

    return $result->fetchObject(Verification::class);
  }

  /**
   * Delete verification by id
   * @param int $id
   * @return bool
   */
  public function delete(int $id): bool
  {
    $qb = $this->connection->createQueryBuilder();
    $qb->delete(_DB_PREFIX_ . 'kyc_verification')
      ->where('id_kyc_verification = :id')
      ->setParameter('id', $id);

    return (bool) $qb->execute();
  }

  /**
   * Find all with pagination
   * @param int $limit
   * @param int $offset
   * @param array $filters
   * @return array
   */
  public function findAllWithPagination(int $limit = 20, int $offset = 0, array $filters = []): array
  {
    $qb = $this->connection->createQueryBuilder();
    $qb->select('*')
      ->from(_DB_PREFIX_ . 'kyc_verification', 'v')
      ->setMaxResults($limit)
      ->setFirstResult($offset);

    // Apply filters if any
    foreach ($filters as $field => $value) {
      if ($value !== null) {
        $qb->andWhere("v.$field = :$field")
          ->setParameter($field, $value);
      }
    }

    $result = $qb->execute();
    return $result->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Create a new verification
   * @param int $customerId
   * @return int|null
   */
  public function createVerification(int $customerId): ?int
  {
    $qb = $this->connection->createQueryBuilder();
    $qb->insert(_DB_PREFIX_ . 'kyc_verification')
      ->setValue('id_customer', ':customerId')
      ->setValue('status', ':status')
      ->setValue('date_add', ':dateAdd')
      ->setParameter('customerId', $customerId)
      ->setParameter('status', 'pending')
      ->setParameter('dateAdd', date('Y-m-d H:i:s'));

    if ($qb->execute()) {
      return (int) $this->connection->lastInsertId();
    }

    return null;
  }
  
}