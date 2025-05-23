<?php
namespace PrestaShop\Module\Pskyc\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @ORM\Table(name="kyc_document")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class Document
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(name="id_kyc_document", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     * @ORM\Column(name="id_kyc_verification", type="integer")
     */
    private $kycVerificationId;

    /**
     * @var string
     * @ORM\Column(name="type", type="string", length=64)
     */
    private $type;

    /**
     * @var string
     * @ORM\Column(name="filename", type="string", length=255)
     */
    private $filename;

    /**
     * @var int
     * @ORM\Column(name="filesize", type="integer")
     */
    private $filesize;

    /**
     * @var string
     * @ORM\Column(name="mime", type="string", length=128)
     */
    private $mime;

    /**
     * @var string
     * @ORM\Column(name="sha256", type="string", length=64)
     */
    private $sha256;

    /**
     * @var bool
     * @ORM\Column(name="encrypted", type="boolean")
     */
    private $encrypted = true;

    /**
     * @var string
     * @ORM\Column(name="iv", type="string", length=32)
     */
    private $iv;

    /**
     * @var DateTime
     * @ORM\Column(name="date_uploaded", type="datetime")
     */
    private $dateUploaded;

    /**
     * @var DateTime|null
     * @ORM\Column(name="expires_at", type="datetime", nullable=true)
     */
    private $expiresAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKycVerificationId(): int
    {
        return $this->kycVerificationId;
    }
    public function setKycVerificationId(int $id): self
    {
        $this->kycVerificationId = $id;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }
    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getFilesize(): int
    {
        return $this->filesize;
    }
    public function setFilesize(int $filesize): self
    {
        $this->filesize = $filesize;
        return $this;
    }

    public function getMime(): string
    {
        return $this->mime;
    }
    public function setMime(string $mime): self
    {
        $this->mime = $mime;
        return $this;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }
    public function setSha256(string $sha256): self
    {
        $this->sha256 = $sha256;
        return $this;
    }

    public function isEncrypted(): bool
    {
        return $this->encrypted;
    }
    public function setEncrypted(bool $encrypted): self
    {
        $this->encrypted = $encrypted;
        return $this;
    }

    public function getIv(): string
    {
        return $this->iv;
    }
    public function setIv(string $iv): self
    {
        $this->iv = $iv;
        return $this;
    }

    public function getDateUploaded(): DateTime
    {
        return $this->dateUploaded;
    }
    public function setDateUploaded(DateTime $date): self
    {
        $this->dateUploaded = $date;
        return $this;
    }

    public function getExpiresAt(): ?DateTime
    {
        return $this->expiresAt;
    }
    public function setExpiresAt(?DateTime $date): self
    {
        $this->expiresAt = $date;
        return $this;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateTimestamps(): void
    {
        $now = new DateTime('now');
        if ($this->getDateUploaded() === null) {
            $this->setDateUploaded($now);
        }
    }
}
