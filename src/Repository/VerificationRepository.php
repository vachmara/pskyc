<?php
namespace PrestaShop\Module\Pskyc\Repository;

use Doctrine\DBAL\Connection;

/**
 * Class VerificationRepository
 * 
 * Repository for managing KYC verification records in the database
 * Provides methods for CRUD operations on verification data
 */
class VerificationRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * VerificationRepository constructor
     * 
     * @param Connection $connection Database connection instance
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Find verification by ID
     * 
     * Retrieves a single verification record by its unique identifier
     * 
     * @param int $id The verification ID to search for
     * @return array|null Verification record array or null if not found
     */
    public function findById(int $id): ?array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('*')
            ->from(_DB_PREFIX_ . 'kyc_verification')
            ->where('id_kyc_verification = :id')
            ->setParameter('id', $id);

        $result = $query->execute();

        if ($result->rowCount() === 0) {
            return null;
        }

        return $result->fetchAssociative();
    }

    /**
     * Find verification by customer ID
     * 
     * Retrieves the most recent verification record for a specific customer
     * 
     * @param int $customerId The customer ID to search for
     * @return array|null Most recent verification record or null if none found
     */
    public function findByCustomerId(int $customerId): ?array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('*')
            ->from(_DB_PREFIX_ . 'kyc_verification')
            ->where('id_customer = :customer_id')
            ->setParameter('customer_id', $customerId)
            ->orderBy('date_submitted', 'DESC')
            ->setMaxResults(1);

        $result = $query->execute();

        if ($result->rowCount() === 0) {
            return null;
        }

        return $result->fetchAssociative();
    }

    /**
     * Find all verifications by customer ID
     * 
     * Retrieves all verification records for a specific customer
     * 
     * @param int $customerId The customer ID to search for
     * @return array Array of verification records ordered by date descending
     */
    public function findAllByCustomerId(int $customerId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('*')
            ->from(_DB_PREFIX_ . 'kyc_verification')
            ->where('id_customer = :customer_id')
            ->setParameter('customer_id', $customerId)
            ->orderBy('date_submitted', 'DESC');

        $result = $query->execute();
        return $result->fetchAllAssociative();
    }

    /**
     * Create a new verification record
     * 
     * Inserts a new verification record into the database
     * 
     * @param int $customerId The customer ID
     * @param string $status Initial status (default: 'pending')
     * @param string|null $customerNote Optional customer note
     * @return int The ID of the newly created verification
     */
    public function create(int $customerId, string $status = 'pending', ?string $customerNote = null): int
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->insert(_DB_PREFIX_ . 'kyc_verification')
            ->values([
                'id_customer' => ':customer_id',
                'status' => ':status',
                'date_submitted' => 'NOW()',
                'customer_note' => ':customer_note'
            ])
            ->setParameters([
                'customer_id' => $customerId,
                'status' => $status,
                'customer_note' => $customerNote ?? null,
            ]);

        $qb->execute();
        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update verification status
     * 
     * Updates the status and related fields of a verification record
     * 
     * @param int $verificationId The verification ID to update
     * @param string $status New status for the verification
     * @param string|null $adminNote Optional admin note
     * @param \DateTime|null $dateValidated Optional validation date
     * @param \DateTime|null $dateExpiry Optional expiry date
     * @return bool True if update was successful, false otherwise
     */
    public function updateStatus(
        int $verificationId,
        string $status,
        ?string $adminNote = null,
        ?\DateTime $dateValidated = null,
        ?\DateTime $dateExpiry = null
    ): bool {
        $qb = $this->connection->createQueryBuilder();
        $qb->update(_DB_PREFIX_ . 'kyc_verification')
            ->set('status', ':status')
            ->where('id_kyc_verification = :verification_id')
            ->setParameter('status', $status)
            ->setParameter('verification_id', $verificationId);

        if ($adminNote !== null) {
            $qb->set('admin_note', ':admin_note')
               ->setParameter('admin_note', $adminNote);
        }

        if ($dateValidated !== null) {
            $qb->set('date_validated', ':date_validated')
               ->setParameter('date_validated', $dateValidated->format('Y-m-d H:i:s'));
        }

        if ($dateExpiry !== null) {
            $qb->set('date_expiry', ':date_expiry')
               ->setParameter('date_expiry', $dateExpiry->format('Y-m-d H:i:s'));
        }

        return (bool) $qb->execute();
    }

    /**
     * Find verifications by status
     * 
     * Retrieves all verification records with a specific status
     * 
     * @param string $status The status to filter by
     * @param int $limit Maximum number of records to return (default: 100)
     * @param int $offset Offset for pagination (default: 0)
     * @return array Array of verification records
     */
    public function findByStatus(string $status, int $limit = 100, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('*')
            ->from(_DB_PREFIX_ . 'kyc_verification')
            ->where('status = :status')
            ->setParameter('status', $status)
            ->orderBy('date_submitted', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $result = $query->execute();
        return $result->fetchAllAssociative();
    }

    /**
     * Find expired verifications
     * 
     * Retrieves all verification records that have passed their expiry date
     * 
     * @return array Array of expired verification records
     */
    public function findExpiredVerifications(): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('*')
            ->from(_DB_PREFIX_ . 'kyc_verification')
            ->where('date_expiry IS NOT NULL')
            ->andWhere('date_expiry < NOW()')
            ->andWhere('status != :expired_status')
            ->setParameter('expired_status', 'expired');

        $result = $query->execute();
        return $result->fetchAllAssociative();
    }

    /**
     * Count verifications by status
     * 
     * Returns the number of verification records for each status
     * 
     * @return array Array of status counts
     */
    public function countByStatus(): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('status, COUNT(*) as count')
            ->from(_DB_PREFIX_ . 'kyc_verification')
            ->groupBy('status')
            ->orderBy('count', 'DESC');

        $result = $query->execute();
        return $result->fetchAllAssociative();
    }

    /**
     * Delete verification by ID
     * 
     * Removes a verification record from the database
     * Note: This should also handle cascade deletion of related documents and logs
     * 
     * @param int $verificationId The verification ID to delete
     * @return bool True if deletion was successful, false otherwise
     */
    public function delete(int $verificationId): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->delete(_DB_PREFIX_ . 'kyc_verification')
            ->where('id_kyc_verification = :verification_id')
            ->setParameter('verification_id', $verificationId);

        return (bool) $qb->execute();
    }

    /**
     * Find active verifications for a customer
     * 
     * @param int $customerId The customer ID to search for
     * @return array Array of active verification records for the customer
     */
    public function findActiveByCustomerId(int $customerId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb->select('*')
            ->from(_DB_PREFIX_ . 'kyc_verification')
            ->where('id_customer = :customer_id')
            ->andWhere('status != :expired_status')
            ->setParameter('customer_id', $customerId)
            ->setParameter('expired_status', 'expired')
            ->orderBy('date_submitted', 'DESC');

        $result = $query->execute();
        return $result->fetchAllAssociative();
    }
}