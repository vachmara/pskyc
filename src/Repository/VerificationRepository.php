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
}