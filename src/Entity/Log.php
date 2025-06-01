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
 * Class Log
 *
 * Entity representing a KYC audit log entry
 * Tracks all actions performed on KYC verifications for security and compliance
 *
 * @ORM\Table(name="kyc_log")
 *
 * @ORM\Entity()
 *
 * @ORM\HasLifecycleCallbacks()
 */
class Log
{
    /**
     * @var int|null
     *
     * @ORM\Id
     *
     * @ORM\Column(name="id_kyc_log", type="integer")
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
     * @var int|null
     *
     * @ORM\Column(name="id_employee", type="integer", nullable=true)
     */
    private $employeeId;

    /**
     * @var int|null
     *
     * @ORM\Column(name="id_customer", type="integer", nullable=true)
     */
    private $customerId;

    /**
     * @var string
     *
     * @ORM\Column(name="action", type="string", length=32)
     */
    private $action;

    /**
     * @var string
     *
     * @ORM\Column(name="message", type="text")
     */
    private $message;

    /**
     * @var string
     *
     * @ORM\Column(name="ip_address", type="string", length=39)
     */
    private $ipAddress;

    /**
     * @var string
     *
     * @ORM\Column(name="user_agent", type="string", length=255)
     */
    private $userAgent;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="date_add", type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="date_upd", type="datetime")
     */
    private $updatedAt;

    /**
     * Get log entry ID
     *
     * @return int|null The log entry ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get KYC verification ID
     *
     * @return int The verification ID this log entry is associated with
     */
    public function getKycVerificationId(): int
    {
        return $this->kycVerificationId;
    }

    /**
     * Set KYC verification ID
     *
     * @param int $id The verification ID to associate with this log entry
     *
     * @return self
     */
    public function setKycVerificationId(int $id): self
    {
        $this->kycVerificationId = $id;

        return $this;
    }

    /**
     * Get employee ID
     *
     * @return int|null The ID of the employee who performed this action, if any
     */
    public function getEmployeeId(): ?int
    {
        return $this->employeeId;
    }

    /**
     * Set employee ID
     *
     * @param int|null $id The employee ID to set
     *
     * @return self
     */
    public function setEmployeeId(?int $id): self
    {
        $this->employeeId = $id;

        return $this;
    }

    /**
     * Get customer ID
     *
     * @return int|null The ID of the customer associated with this action, if any
     */
    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    /**
     * Set customer ID
     *
     * @param int|null $id The customer ID to set
     *
     * @return self
     */
    public function setCustomerId(?int $id): self
    {
        $this->customerId = $id;

        return $this;
    }

    /**
     * Get action type
     *
     * @return string The type of action that was logged
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Set action type
     *
     * @param string $action The type of action to log
     *
     * @return self
     */
    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get log message
     *
     * @return string The detailed message describing the action
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Set log message
     *
     * @param string $message The message to set
     *
     * @return self
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Get IP address
     *
     * @return string|null The IP address from which the action was performed
     */
    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * Set IP address
     *
     * @param string|null $ip The IP address to log
     *
     * @return self
     */
    public function setIpAddress(?string $ip): self
    {
        $this->ipAddress = $ip;

        return $this;
    }

    /**
     * Get user agent
     *
     * @return string The user agent string from the request
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Set user agent
     *
     * @param string $ua The user agent string to set
     *
     * @return self
     */
    public function setUserAgent(string $ua): self
    {
        $this->userAgent = $ua;

        return $this;
    }

    /**
     * Get creation date
     *
     * @return \DateTime When this log entry was created
     */
    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    /**
     * Set creation date
     *
     * @param \DateTime $date The creation date to set
     *
     * @return self
     */
    public function setCreatedAt(\DateTime $date): self
    {
        $this->createdAt = $date;

        return $this;
    }

    /**
     * Get last update date
     *
     * @return \DateTime When this log entry was last updated
     */
    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Set last update date
     *
     * @param \DateTime $date The update date to set
     *
     * @return self
     */
    public function setUpdatedAt(\DateTime $date): self
    {
        $this->updatedAt = $date;

        return $this;
    }

    /**
     * Update timestamps automatically
     *
     * Doctrine lifecycle callback that sets creation and update timestamps
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
        if ($this->getCreatedAt() === null) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);
    }
}
