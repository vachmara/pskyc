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
     * Find all verifications with pagination and optional filters
     * 
     * @param array $filters Optional filters for status and customer ID
     * @param int $limit Number of records per page
     * @param int $offset Offset for pagination
     * @return array Array of verification records with pagination
     */
    public function findAll(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(_DB_PREFIX_ . 'kyc_verification')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        // Apply filters if provided
        if (!empty($filters['status'])) {
            $qb->andWhere('status = :status')
                ->setParameter('status', $filters['status']);
        }
        if (!empty($filters['customer_id'])) {
            $qb->andWhere('id_customer = :customer_id')
                ->setParameter('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['date_from'])) {
            $qb->andWhere('date_submitted >= :date_from')
                ->setParameter('date_from', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('date_submitted <= :date_to')
                ->setParameter('date_to', $filters['date_to']);
        }

        $query = $qb->execute();
        return $query->fetchAllAssociative();
    }

    /**
     * Get status counts 
     * 
     * @return array Associative array of status counts
     */
    public function getStatusCounts(): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('status, COUNT(*) as count')
            ->from(_DB_PREFIX_ . 'kyc_verification')
            ->groupBy('status');

        $result = $qb->execute();
        return $result->fetchAllKeyValue();
    }

    /**
     * Count total verifications with optional filters
     * 
     * @param array $filters Optional filters for status and customer ID
     * @return int Total count of verification records matching the filters
     */
    public function countAll(array $filters = []): int
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(*)')
            ->from(_DB_PREFIX_ . 'kyc_verification');

        // Apply filters if provided
        if (!empty($filters['status'])) {
            $qb->andWhere('status = :status')
                ->setParameter('status', $filters['status']);
        }
        if (!empty($filters['customer_id'])) {
            $qb->andWhere('id_customer = :customer_id')
                ->setParameter('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['date_from'])) {
            $qb->andWhere('date_submitted >= :date_from')
                ->setParameter('date_from', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('date_submitted <= :date_to')
                ->setParameter('date_to', $filters['date_to']);
        }

        return (int) $qb->execute()->fetchOne();
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

    /**
     * Find verification by ID (alias for Grid controller compatibility)
     * 
     * @param int $id The verification ID to search for
     * @return array|null Verification record array or null if not found
     */
    public function findOneById(int $id): ?array
    {
        return $this->findById($id);
    }

    /**
     * Update verification status
     * 
     * @param int $id The verification ID to update
     * @param string $status The new status
     * @return bool True if update was successful
     */
    public function updateStatus(int $id, string $status): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->update(_DB_PREFIX_ . 'kyc_verification')
            ->set('status', ':status')
            ->set('date_validated', 'NOW()')
            ->where('id_kyc_verification = :id')
            ->setParameters([
                'status' => $status,
                'id' => $id,
            ]);

        return $qb->execute() > 0;
    }

    /**
     * Update admin note for verification
     * 
     * @param int $id The verification ID to update
     * @param string|null $note The admin note
     * @return bool True if update was successful
     */
    public function updateAdminNote(int $id, ?string $note): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->update(_DB_PREFIX_ . 'kyc_verification')
            ->set('admin_note', ':note')
            ->where('id_kyc_verification = :id')
            ->setParameters([
                'note' => $note,
                'id' => $id,
            ]);

        return $qb->execute() > 0;
    }

    /**
     * Update expiration date for verification
     * 
     * @param int $id The verification ID to update
     * @param string $expirationDate The new expiration date in MySQL datetime format 
     * @return bool True if update was successful
     */
    public function updateExpirationDate(int $id, string $expirationDate): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->update(_DB_PREFIX_ . 'kyc_verification')
            ->set('date_expiry', ':expiration_date')
            ->set('date_validated', 'NOW()')
            ->where('id_kyc_verification = :id')
            ->setParameters([
                'expiration_date' => $expirationDate,
                'id' => $id,
            ]);

        return $qb->execute() > 0;
    }

    /**
     * Delete verification by ID
     * 
     * @param int $id The verification ID to delete
     * @return bool True if deletion was successful
     */
    public function delete(int $id): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->delete(_DB_PREFIX_ . 'kyc_verification')
            ->where('id_kyc_verification = :id')
            ->setParameter('id', $id);

        return $qb->execute() > 0;
    }
}