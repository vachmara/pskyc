<?php
namespace PrestaShop\Module\Pskyc\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @ORM\Table(name="kyc_log")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class PskycLog
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(name="id_kyc_log", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     * @ORM\Column(name="id_kyc_verification", type="integer")
     */
    private $kycVerificationId;

    /**
     * @var int|null
     * @ORM\Column(name="id_employee", type="integer", nullable=true)
     */
    private $employeeId;

    /**
     * @var int|null
     * @ORM\Column(name="id_customer", type="integer", nullable=true)
     */
    private $customerId;

    /**
     * @var string
     * @ORM\Column(name="action", type="string", length=32)
     */
    private $action;

    /**
     * @var string
     * @ORM\Column(name="message", type="text")
     */
    private $message;

    /**
     * @var string
     * @ORM\Column(name="ip_address", type="string", length=39)
     */
    private $ipAddress;

    /**
     * @var string
     * @ORM\Column(name="user_agent", type="string", length=255)
     */
    private $userAgent;

    /**
     * @var DateTime
     * @ORM\Column(name="date_add", type="datetime")
     */
    private $createdAt;

    /**
     * @var DateTime
     * @ORM\Column(name="date_upd", type="datetime")
     */
    private $updatedAt;

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

    public function getEmployeeId(): ?int
    {
        return $this->employeeId;
    }
    public function setEmployeeId(?int $id): self
    {
        $this->employeeId = $id;
        return $this;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }
    public function setCustomerId(?int $id): self
    {
        $this->customerId = $id;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }
    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }
    public function setIpAddress(string $ip): self
    {
        $this->ipAddress = $ip;
        return $this;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }
    public function setUserAgent(string $ua): self
    {
        $this->userAgent = $ua;
        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
    public function setCreatedAt(DateTime $date): self
    {
        $this->createdAt = $date;
        return $this;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
    public function setUpdatedAt(DateTime $date): self
    {
        $this->updatedAt = $date;
        return $this;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateTimestamps(): void
    {
        $now = new DateTime('now');
        if ($this->getCreatedAt() === null) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);
    }
}
