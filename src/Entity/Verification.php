<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

namespace PrestaShop\Module\Pskyc\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class Verification
 *
 * Entity representing a KYC verification request from a customer
 * Contains verification status and timeline information
 *
 * @ORM\Table(name="kyc_verification")
 *
 * @ORM\Entity()
 *
 * @ORM\HasLifecycleCallbacks()
 */
class Verification
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_MORE_INFO = 'requested_more_info';

    /**
     * @var int
     *
     * @ORM\Id
     *
     * @ORM\Column(name="id_kyc_verification", type="integer")
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="id_customer", type="integer")
     */
    private $customerId;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=32)
     */
    private $status = self::STATUS_PENDING;

    /**
     * @var string|null
     *
     * @ORM\Column(name="admin_note", type="text", nullable=true)
     */
    private $adminNote;

    /**
     * @var string|null
     *
     * @ORM\Column(name="customer_note", type="text", nullable=true)
     */
    private $customerNote;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_submitted", type="datetime")
     */
    private $dateSubmitted;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="date_validated", type="datetime", nullable=true)
     */
    private $dateValidated;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="date_expiry", type="datetime", nullable=true)
     */
    private $dateExpiry;

    /**
     * Get verification ID
     *
     * @return int|null The verification ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get customer ID
     *
     * @return int The customer ID associated with this verification
     */
    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    /**
     * Set customer ID
     *
     * @param int $id The customer ID to set
     *
     * @return self
     */
    public function setCustomerId(int $id): self
    {
        $this->customerId = $id;

        return $this;
    }

    /**
     * Get verification status
     *
     * @return string Current verification status
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set verification status
     *
     * @param string $status The status to set (use STATUS_* constants)
     *
     * @return self
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get admin note
     *
     * @return string|null Admin note/comment about this verification
     */
    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }

    /**
     * Set admin note
     *
     * @param string|null $note Admin note/comment to set
     *
     * @return self
     */
    public function setAdminNote(?string $note): self
    {
        $this->adminNote = $note;

        return $this;
    }

    /**
     * Get customer note
     *
     * @return string|null Customer note/comment about this verification
     */
    public function getCustomerNote(): ?string
    {
        return $this->customerNote;
    }

    /**
     * Set customer note
     *
     * @param string|null $note Customer note/comment to set
     *
     * @return self
     */
    public function setCustomerNote(?string $note): self
    {
        $this->customerNote = $note;

        return $this;
    }

    /**
     * Get submission date
     *
     * @return \DateTime When the verification was submitted
     */
    public function getDateSubmitted(): \DateTime
    {
        return $this->dateSubmitted;
    }

    /**
     * Set submission date
     *
     * @param \DateTime $date The submission date to set
     *
     * @return self
     */
    public function setDateSubmitted(\DateTime $date): self
    {
        $this->dateSubmitted = $date;

        return $this;
    }

    /**
     * Get validation date
     *
     * @return \DateTime|null When the verification was validated (approved/rejected)
     */
    public function getDateValidated(): ?\DateTime
    {
        return $this->dateValidated;
    }

    /**
     * Set validation date
     *
     * @param \DateTime|null $date The validation date to set
     *
     * @return self
     */
    public function setDateValidated(?\DateTime $date): self
    {
        $this->dateValidated = $date;

        return $this;
    }

    /**
     * Get expiry date
     *
     * @return \DateTime|null When the verification expires
     */
    public function getDateExpiry(): ?\DateTime
    {
        return $this->dateExpiry;
    }

    /**
     * Set expiry date
     *
     * @param \DateTime|null $date The expiry date to set
     *
     * @return self
     */
    public function setDateExpiry(?\DateTime $date): self
    {
        $this->dateExpiry = $date;

        return $this;
    }

    /**
     * Set default submission date to current time
     *
     * Lifecycle callback to automatically set submission date
     *
     * @return void
     */
    public function setDefaultDateSubmitted(): void
    {
        if ($this->dateSubmitted === null) {
            $this->dateSubmitted = new \DateTime('now');
        }
    }
}
