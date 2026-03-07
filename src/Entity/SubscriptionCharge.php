<?php

namespace App\Entity;

use App\Repository\SubscriptionChargeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionChargeRepository::class)]
#[ORM\Table(name: 'subscription_charge')]
class SubscriptionCharge
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    public const GATEWAY_MERCADOPAGO = 'mercadopago';
    public const GATEWAY_ASAAS = 'asaas';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shop $shop = null;

    /** Plano: mensal, semestral, anual */
    #[ORM\Column(length: 20)]
    private ?string $plan = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(length: 30)]
    private ?string $gateway = null;

    /** ID do pagamento no gateway (ex: Mercado Pago) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $gatewayPaymentId = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    /** Resposta completa do gateway (json) – preenchido quando o pagamento é confirmado (ex.: webhook) */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $paymentData = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct()
    {
        $this->status = self::STATUS_PENDING;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): static
    {
        $this->shop = $shop;
        return $this;
    }

    public function getPlan(): ?string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getGateway(): ?string
    {
        return $this->gateway;
    }

    public function setGateway(string $gateway): static
    {
        $this->gateway = $gateway;
        return $this;
    }

    public function getGatewayPaymentId(): ?string
    {
        return $this->gatewayPaymentId;
    }

    public function setGatewayPaymentId(?string $gatewayPaymentId): static
    {
        $this->gatewayPaymentId = $gatewayPaymentId;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPaymentData(): ?array
    {
        return $this->paymentData;
    }

    public function setPaymentData(?array $paymentData): static
    {
        $this->paymentData = $paymentData;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }
}
