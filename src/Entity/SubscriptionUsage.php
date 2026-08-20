<?php

namespace App\Entity;

use App\Repository\SubscriptionUsageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Registro de um uso de serviço dentro de uma ClientSubscription
 * (espelha a "Seção 4 — Controle de utilização" da ficha de papel).
 */
#[ORM\Entity(repositoryClass: SubscriptionUsageRepository::class)]
class SubscriptionUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['subscription_usage:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ClientSubscription $subscription = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['subscription_usage:read'])]
    private ?Service $service = null;

    /** Reservado para a Fase 2 (integração com o agendamento). Não usado ainda. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Appointment $appointment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['subscription_usage:read'])]
    private ?User $registeredBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['subscription_usage:read'])]
    private ?string $note = null;

    #[ORM\Column]
    #[Groups(['subscription_usage:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscription(): ?ClientSubscription
    {
        return $this->subscription;
    }

    public function setSubscription(?ClientSubscription $subscription): static
    {
        $this->subscription = $subscription;
        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function getAppointment(): ?Appointment
    {
        return $this->appointment;
    }

    public function setAppointment(?Appointment $appointment): static
    {
        $this->appointment = $appointment;
        return $this;
    }

    public function getRegisteredBy(): ?User
    {
        return $this->registeredBy;
    }

    public function setRegisteredBy(?User $registeredBy): static
    {
        $this->registeredBy = $registeredBy;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
