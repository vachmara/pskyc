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
   * Create a new log entry
   *
   * @param int $kycVerificationId
   * @param int|null $employeeId
   * @param int|null $customerId
   * @param string $action
   * @param string $message
   * @param string $ipAddress
   * @param string $userAgent
   * @return int|null
   */
  public function createLog(
    int $kycVerificationId,
    ?int $employeeId,
    ?int $customerId,
    string $action,
    string $message,
    string $ipAddress,
    string $userAgent
  ): ?int {
    $log = new Log();
    $log->setKycVerificationId($kycVerificationId);
    $log->setEmployeeId($employeeId);
    $log->setCustomerId($customerId);
    $log->setAction($action);
    $log->setMessage($message);
    $log->setIpAddress($ipAddress);
    $log->setUserAgent($userAgent);

    // Persist the log entity
    $this->connection->insert('kyc_log', [
      'id_kyc_verification' => $log->getKycVerificationId(),
      'id_employee' => $log->getEmployeeId(),
      'id_customer' => $log->getCustomerId(),
      'action' => $log->getAction(),
      'message' => $log->getMessage(),
      'ip_address' => $log->getIpAddress(),
      'user_agent' => $log->getUserAgent(),
      'date_add' => (new \DateTime())->format('Y-m-d H:i:s'),
    ]);

    return (int) $this->connection->lastInsertId();
  }

}