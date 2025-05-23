<?php
namespace PrestaShop\Module\Pskyc\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @ORM\Table(name="kyc_verification")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class Verification
{
    public const STATUS_PENDING        = 'pending';
    public const STATUS_UNDER_REVIEW   = 'under_review';
    public const STATUS_APPROVED       = 'approved';
    public const STATUS_REJECTED       = 'rejected';
    public const STATUS_EXPIRED        = 'expired';
    public const STATUS_MORE_INFO      = 'requested_more_info';

    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(name="id_kyc_verification", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     * @ORM\Column(name="id_customer", type="integer")
     */
    private $customerId;

    /**
     * @var string
     * @ORM\Column(name="status", type="string", length=32)
     */
    private $status = self::STATUS_PENDING;

    /**
     * @var string|null
     * @ORM\Column(name="admin_note", type="text", nullable=true)
     */
    private $adminNote;

    /**
     * @var DateTime
     * @ORM\Column(name="date_submitted", type="datetime")
     */
    private $dateSubmitted;

    /**
     * @var DateTime|null
     * @ORM\Column(name="date_validated", type="datetime", nullable=true)
     */
    private $dateValidated;

    /**
     * @var DateTime|null
     * @ORM\Column(name="date_expiry", type="datetime", nullable=true)
     */
    private $dateExpiry;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }
    public function setCustomerId(int $id): self
    {
        $this->customerId = $id;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }
    public function setAdminNote(?string $note): self
    {
        $this->adminNote = $note;
        return $this;
    }

    public function getDateSubmitted(): DateTime
    {
        return $this->dateSubmitted;
    }
    public function setDateSubmitted(DateTime $date): self
    {
        $this->dateSubmitted = $date;
        return $this;
    }

    public function getDateValidated(): ?DateTime
    {
        return $this->dateValidated;
    }
    public function setDateValidated(?DateTime $date): self
    {
        $this->dateValidated = $date;
        return $this;
    }

    public function getDateExpiry(): ?DateTime
    {
        return $this->dateExpiry;
    }
    public function setDateExpiry(?DateTime $date): self
    {
        $this->dateExpiry = $date;
        return $this;
    }

    /**
     * @ORM\PrePersist
     */
    public function setDefaultDateSubmitted(): void
    {
        if ($this->getDateSubmitted() === null) {
            $this->setDateSubmitted(new DateTime('now'));
        }
    }
}
