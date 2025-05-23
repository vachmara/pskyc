<?php
namespace PrestaShop\Module\Pskyc\Repository;

use Doctrine\DBAL\Connection;
use Prestashop\Module\Pskyc\Entity\Log;

class LogRepository
{
  /**
   * @var Connection
   */
  private $connection;

  /**
   * LogRepository constructor.
   *
   * @param Connection $connection
   */
  public function __construct(Connection $connection)
  {
    $this->connection = $connection;
  }

  /**
   * Find log by id
   *
   * @param int $id
   *
   * @return Log|null
   */
  public function findLogById(int $id): ?Log
  {
    $qb = $this->connection->createQueryBuilder();
    $query = $qb->select('*')
      ->from(_DB_PREFIX_ . 'kyc_log', 'l')
      ->where('l.id_kyc_log = :id')
      ->setParameter('id', $id)
    ;

    $result = $query->execute();

    if ($result->rowCount() === 0) {
      return null;
    }

    return $result->fetchObject(Log::class);
  }

}