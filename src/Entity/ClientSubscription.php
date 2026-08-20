<?php

namespace App\Entity;

use App\Repository\ClientSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Assinatura de um Client a um Plan da barbearia.
 */
#[ORM\Entity(repositoryClass: ClientSubscriptionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ClientSubscription
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['client_subscription:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['client_subscription:read'])]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['client_subscription:read'])]
    private ?Plan $plan = null;

    #[ORM\Column(length: 20)]
    #[Groups(['client_subscription:read'])]
    private ?string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    #[Groups(['client_subscription:read'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column]
    #[Groups(['client_subscription:read'])]
    private ?\DateTimeImmutable $currentCycleStart = null;

    #[ORM\Column]
    #[Groups(['client_subscription:read'])]
    private ?\DateTimeImmutable $currentCycleEnd = null;

    /** Forma de pagamento informada manualmente (ex: Pix, Dinheiro, Cartão). */
    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['client_subscription:read'])]
    private ?string $paymentMethod = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['client_subscription:read'])]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column]
    #[Groups(['client_subscription:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid status');
        }
        $this->status = $status;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCurrentCycleStart(): ?\DateTimeImmutable
    {
        return $this->currentCycleStart;
    }

    public function setCurrentCycleStart(\DateTimeImmutable $currentCycleStart): static
    {
        $this->currentCycleStart = $currentCycleStart;
        return $this;
    }

    public function getCurrentCycleEnd(): ?\DateTimeImmutable
    {
        return $this->currentCycleEnd;
    }

    public function setCurrentCycleEnd(\DateTimeImmutable $currentCycleEnd): static
    {
        $this->currentCycleEnd = $currentCycleEnd;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
