<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

namespace PrestaShop\Module\Pskyc\Repository;

use Doctrine\DBAL\Connection;
use PrestaShop\Module\Pskyc\Entity\Log;

/**
 * Class LogRepository
 *
 * Repository for managing KYC log records in the database
 * Provides methods for creating and retrieving audit trail entries
 */
class LogRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * LogRepository constructor
     *
     * @param Connection $connection Database connection instance
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create a new log entry
     *
     * Records an action performed by a customer or employee for audit purposes
     *
     * @param int $kycVerificationId The verification ID this log relates to
     * @param int|null $employeeId The employee who performed the action (null for customer actions)
     * @param int|null $customerId The customer who performed the action (null for admin actions)
     * @param string $action The action performed (e.g., 'documents_uploaded', 'status_changed')
     * @param string $message Descriptive message about the action
     * @param string $ipAddress IP address of the user who performed the action
     * @param string $userAgent User agent string of the user's browser
     *
     * @return int|null The ID of the created log entry or null on failure
     */
    public function createLog(
        int $kycVerificationId,
        ?int $employeeId,
        ?int $customerId,
        string $action,
        string $message,
        string $ipAddress,
        string $userAgent,
    ): ?int {
        $qb = $this->connection->createQueryBuilder();
        $qb->insert(_DB_PREFIX_ . 'kyc_log')
            ->values([
                'id_kyc_verification' => ':verification_id',
                'id_employee' => ':employee_id',
                'id_customer' => ':customer_id',
                'action' => ':action',
                'message' => ':message',
                'ip_address' => ':ip_address',
                'user_agent' => ':user_agent',
                'date_add' => 'NOW()',
            ])
            ->setParameters([
                'verification_id' => $kycVerificationId,
                'employee_id' => $employeeId,
                'customer_id' => $customerId,
                'action' => $action,
                'message' => $message,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

        if ($qb->execute()) {
            return (int) $this->connection->lastInsertId();
        }

        return null;
    }

    /**
     * Create a new log entry (simplified interface)
     *
     * @param array $data Log data array
     *
     * @return int|null The ID of the created log entry or null on failure
     */
    public function create(array $data): ?int
    {
        return $this->createLog(
            $data['verification_id'],
            $data['employee_id'] ?? null,
            $data['customer_id'] ?? null,
            $data['action'],
            $data['details'] ?? $data['message'] ?? '',
            $data['ip_address'] ?? '',
            $data['user_agent'] ?? ''
        );
    }

    /**
     * Find log entries by verification ID
     *
     * Retrieves all log entries for a specific verification request
     *
     * @param int $verificationId The verification ID to search for
     * @param int $limit Maximum number of entries to return (default: 100)
     * @param int $offset Offset for pagination (default: 0)
     *
     * @return array Array of log entries ordered by date descending
     */
    public function findByVerificationId(int $verificationId, int $limit = 100, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('l.*, INET6_NTOA(l.ip_address) as ip_address_readable')
            ->from(_DB_PREFIX_ . 'kyc_log', 'l')
            ->where('l.id_kyc_verification = :verification_id')
            ->setParameter('verification_id', $verificationId)
            ->orderBy('l.date_add', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $result = $query->execute();

        return $result->fetchAllAssociative();
    }

    /**
     * Find log entries by customer ID
     *
     * Retrieves all log entries for actions performed by a specific customer
     *
     * @param int $customerId The customer ID to search for
     * @param int $limit Maximum number of entries to return (default: 50)
     *
     * @return array Array of log entries ordered by date descending
     */
    public function findByCustomerId(int $customerId, int $limit = 50): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('l.*, INET6_NTOA(l.ip_address) as ip_address_readable')
            ->from(_DB_PREFIX_ . 'kyc_log', 'l')
            ->where('l.id_customer = :customer_id')
            ->setParameter('customer_id', $customerId)
            ->orderBy('l.date_add', 'DESC')
            ->setMaxResults($limit);

        $result = $query->execute();

        return $result->fetchAllAssociative();
    }

    /**
     * Find log entries by employee ID
     *
     * Retrieves all log entries for actions performed by a specific employee
     *
     * @param int $employeeId The employee ID to search for
     * @param int $limit Maximum number of entries to return (default: 50)
     *
     * @return array Array of log entries ordered by date descending
     */
    public function findByEmployeeId(int $employeeId, int $limit = 50): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('l.*, INET6_NTOA(l.ip_address) as ip_address_readable')
            ->from(_DB_PREFIX_ . 'kyc_log', 'l')
            ->where('l.id_employee = :employee_id')
            ->setParameter('employee_id', $employeeId)
            ->orderBy('l.date_add', 'DESC')
            ->setMaxResults($limit);

        $result = $query->execute();

        return $result->fetchAllAssociative();
    }

    /**
     * Get log statistics
     *
     * Returns count of log entries grouped by action type
     *
     * @param int|null $verificationId Optional verification ID to filter by
     *
     * @return array Array of action counts
     */
    public function getLogStats(?int $verificationId = null): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('action, COUNT(*) as count')
            ->from(_DB_PREFIX_ . 'kyc_log')
            ->groupBy('action')
            ->orderBy('count', 'DESC');

        if ($verificationId !== null) {
            $query->where('id_kyc_verification = :verification_id')
                  ->setParameter('verification_id', $verificationId);
        }

        $result = $query->execute();

        return $result->fetchAllAssociative();
    }

    /**
     * Delete old log entries
     *
     * Removes log entries older than the specified number of days
     *
     * @param int $days Number of days to retain logs
     *
     * @return int Number of deleted entries
     */
    public function deleteOldLogs(int $days): int
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->delete(_DB_PREFIX_ . 'kyc_log')
            ->where('date_add < DATE_SUB(NOW(), INTERVAL :days DAY)')
            ->setParameter('days', $days);

        return (int) $qb->execute();
    }
}
