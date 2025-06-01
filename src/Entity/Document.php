<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * @author Valentin Chmara
 * @copyright Valentin Chmara
 * @license MIT
 */

namespace PrestaShop\Module\Pskyc\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}
use DateTime;
use Doctrine\ORM\Mapping as ORM;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class Document
 *
 * Entity representing an uploaded KYC document
 * Contains file metadata and encryption information
 *
 * @ORM\Table(name="kyc_document")
 *
 * @ORM\Entity()
 *
 * @ORM\HasLifecycleCallbacks()
 */
class Document
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_MORE_INFO = 'requested_more_info';

    /**
     * @var int|null
     *
     * @ORM\Id
     *
     * @ORM\Column(name="id_kyc_document", type="integer")
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @phpstan-ignore-next-line property.onlyRead
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="id_kyc_verification", type="integer")
     */
    private $kycVerificationId;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=64)
     */
    private $type;

    /**
     * @var string|null
     *
     * @ORM\Column(name="side", type="string", length=16, nullable=true)
     */
    private $side;

    /**
     * @var string
     *
     * @ORM\Column(name="filename", type="string", length=255)
     */
    private $filename;

    /**
     * @var int
     *
     * @ORM\Column(name="filesize", type="integer")
     */
    private $filesize;

    /**
     * @var string
     *
     * @ORM\Column(name="mime", type="string", length=128)
     */
    private $mime;

    /**
     * @var string
     *
     * @ORM\Column(name="sha256", type="string", length=64)
     */
    private $sha256;

    /**
     * @var bool
     *
     * @ORM\Column(name="encrypted", type="boolean")
     */
    private $encrypted = true;

    /**
     * @var string
     *
     * @ORM\Column(name="iv", type="string", length=32)
     */
    private $iv;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="date_uploaded", type="datetime")
     */
    private $dateUploaded;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="expires_at", type="datetime", nullable=true)
     */
    private $expiresAt;

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
     * Get document ID
     *
     * @return int|null The document ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get KYC verification ID
     *
     * @return int The verification ID this document belongs to
     */
    public function getKycVerificationId(): int
    {
        return $this->kycVerificationId;
    }

    /**
     * Set KYC verification ID
     *
     * @param int $id The verification ID to associate with this document
     *
     * @return self
     */
    public function setKycVerificationId(int $id): self
    {
        $this->kycVerificationId = $id;

        return $this;
    }

    /**
     * Get document type
     *
     * @return string The document type (e.g., 'passport', 'id_card', 'utility_bill')
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set document type
     *
     * @param string $type The document type to set
     *
     * @return self
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get document side
     *
     * @return string|null The document side ('front' or 'back'), or null if not applicable
     */
    public function getSide(): ?string
    {
        return $this->side;
    }

    /**
     * Set document side
     *
     * @param string|null $side The document side to set ('front' or 'back')
     *
     * @return self
     */
    public function setSide(?string $side = null): self
    {
        $this->side = $side;

        return $this;
    }

    /**
     * Get original filename
     *
     * @return string The original filename as uploaded by the user
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Set filename
     *
     * @param string $filename The filename to set
     *
     * @return self
     */
    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Get file size in bytes
     *
     * @return int The file size in bytes
     */
    public function getFilesize(): int
    {
        return $this->filesize;
    }

    /**
     * Set file size
     *
     * @param int $filesize The file size in bytes
     *
     * @return self
     */
    public function setFilesize(int $filesize): self
    {
        $this->filesize = $filesize;

        return $this;
    }

    /**
     * Get MIME type
     *
     * @return string The MIME type of the file
     */
    public function getMime(): string
    {
        return $this->mime;
    }

    /**
     * Set MIME type
     *
     * @param string $mime The MIME type to set
     *
     * @return self
     */
    public function setMime(string $mime): self
    {
        $this->mime = $mime;

        return $this;
    }

    /**
     * Get SHA256 hash
     *
     * @return string The SHA256 hash of the original file
     */
    public function getSha256(): string
    {
        return $this->sha256;
    }

    /**
     * Set SHA256 hash
     *
     * @param string $sha256 The SHA256 hash to set
     *
     * @return self
     */
    public function setSha256(string $sha256): self
    {
        $this->sha256 = $sha256;

        return $this;
    }

    /**
     * Check if file is encrypted
     *
     * @return bool True if file is encrypted, false otherwise
     */
    public function isEncrypted(): bool
    {
        return $this->encrypted;
    }

    /**
     * Set encryption status
     *
     * @param bool $encrypted Whether the file is encrypted
     *
     * @return self
     */
    public function setEncrypted(bool $encrypted): self
    {
        $this->encrypted = $encrypted;

        return $this;
    }

    /**
     * Get initialization vector for encryption
     *
     * @return string The IV used for encryption
     */
    public function getIv(): string
    {
        return $this->iv;
    }

    /**
     * Set initialization vector
     *
     * @param string $iv The IV to set for encryption
     *
     * @return self
     */
    public function setIv(string $iv): self
    {
        $this->iv = $iv;

        return $this;
    }

    /**
     * Get upload date
     *
     * @return \DateTime|null When the document was uploaded
     */
    public function getDateUploaded(): ?\DateTime
    {
        return $this->dateUploaded;
    }

    /**
     * Set upload date
     *
     * @param \DateTime $date The upload date to set
     *
     * @return self
     */
    public function setDateUploaded(\DateTime $date): self
    {
        $this->dateUploaded = $date;

        return $this;
    }

    /**
     * Get expiration date
     *
     * @return \DateTime|null When the document expires and should be deleted
     */
    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    /**
     * Set expiration date
     *
     * @param \DateTime|null $date The expiration date to set
     *
     * @return self
     */
    public function setExpiresAt(?\DateTime $date): self
    {
        $this->expiresAt = $date;

        return $this;
    }

    /**
     * Update timestamps on persist/update
     *
     * Lifecycle callback to automatically set upload date
     *
     * @ORM\PrePersist
     *
     * @ORM\PreUpdate
     *
     * @return void
     */
    public function updateTimestamps(): void
    {
        $now = new \DateTime('now');
        if ($this->getDateUploaded() === null) {
            $this->setDateUploaded($now);
        }
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
    public function setAdminNote(?string $note = null): self
    {
        $this->adminNote = $note;

        return $this;
    }
}
