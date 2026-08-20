<?php

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Plano de assinatura vendido pela barbearia aos próprios clientes
 * (ex: "4 cortes por mês"). Não confundir com a assinatura da barbearia
 * na plataforma (ver Shop::$subscriptionPlan).
 */
#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['plan:read', 'client_subscription:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'plans')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['plan:read'])]
    private ?Shop $shop = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['plan:read', 'plan:write', 'client_subscription:read'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    #[Groups(['plan:read', 'plan:write', 'client_subscription:read'])]
    private ?string $price = null;

    /** Duração do ciclo/validade da assinatura, em dias, a partir do início/renovação. */
    #[ORM\Column(options: ['default' => 30])]
    #[Assert\Positive]
    #[Groups(['plan:read', 'plan:write', 'client_subscription:read'])]
    private int $cycleDays = 30;

    /** Regras/observações livres (ex: "válido seg-qui, taxa extra sex/sáb"). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['plan:read', 'plan:write'])]
    private ?string $notes = null;

    #[ORM\Column]
    #[Groups(['plan:read', 'plan:write'])]
    private bool $active = true;

    /**
     * @var Collection<int, PlanService>
     */
    #[ORM\OneToMany(targetEntity: PlanService::class, mappedBy: 'plan', cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['plan:read'])]
    private Collection $items;

    #[ORM\Column]
    #[Groups(['plan:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['plan:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

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

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): static
    {
        $this->shop = $shop;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getCycleDays(): int
    {
        return $this->cycleDays;
    }

    public function setCycleDays(int $cycleDays): static
    {
        $this->cycleDays = $cycleDays;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    /**
     * @return Collection<int, PlanService>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(PlanService $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setPlan($this);
        }
        return $this;
    }

    public function removeItem(PlanService $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getPlan() === $this) {
                $item->setPlan(null);
            }
        }
        return $this;
    }

    public function clearItems(): static
    {
        $this->items->clear();
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
