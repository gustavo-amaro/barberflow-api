<?php

namespace App\Entity;

use App\Repository\ShopRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShopRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Shop
{
    /** Dias de acesso no plano gratuito (trial), a partir de createdAt. */
    private const TRIAL_PERIOD_DAYS = 7;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['shop:read', 'barber:read', 'service:read', 'product:read', 'client:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['shop:read', 'shop:write', 'barber:read', 'service:read', 'product:read', 'client:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Slug deve conter apenas letras minúsculas, números e hífens')]
    #[Groups(['shop:read', 'shop:write'])]
    private ?string $slug = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['shop:read', 'shop:write'])]
    private ?string $logo = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['shop:read', 'shop:write'])]
    private ?string $phone = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['shop:read', 'shop:write'])]
    private ?string $instagram = null;

    /** Se true, todo agendamento novo é criado já como confirmado. */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['shop:read', 'shop:write'])]
    private bool $autoConfirmAppointments = false;

    /**
     * Datas em que a barbearia não atende (feriados/folgas), formato Y-m-d.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['shop:read'])]
    private ?array $closedDates = null;

    /** Nome da instância na Evolution API (uma por barbearia). */
    #[ORM\Column(length: 80, nullable: true)]
    #[Groups(['shop:read', 'shop:write'])]
    private ?string $evolutionInstanceName = null;

    /** API Key da instância retornada pela Evolution ao criar. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $evolutionInstanceApiKey = null;

    #[ORM\OneToOne(inversedBy: 'ownedShop', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['shop:read'])]
    private ?User $owner = null;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'shop')]
    private Collection $members;

    /**
     * @var Collection<int, Barber>
     */
    #[ORM\OneToMany(targetEntity: Barber::class, mappedBy: 'shop', orphanRemoval: true)]
    private Collection $barbers;

    /**
     * @var Collection<int, Service>
     */
    #[ORM\OneToMany(targetEntity: Service::class, mappedBy: 'shop', orphanRemoval: true)]
    private Collection $services;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'shop', orphanRemoval: true)]
    private Collection $products;

    /**
     * @var Collection<int, Client>
     */
    #[ORM\OneToMany(targetEntity: Client::class, mappedBy: 'shop', orphanRemoval: true)]
    private Collection $clients;

    /**
     * @var Collection<int, ShopSchedule>
     */
    #[ORM\OneToMany(targetEntity: ShopSchedule::class, mappedBy: 'shop', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $schedules;

    #[ORM\Column]
    #[Groups(['shop:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['shop:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /** Plano ativo: trial, mensal, semestral, anual */
    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['shop:read'])]
    private ?string $subscriptionPlan = null;

    /** Data até quando a assinatura está ativa */
    #[ORM\Column(nullable: true)]
    #[Groups(['shop:read'])]
    private ?\DateTimeImmutable $subscriptionEndsAt = null;

    /** ID da assinatura no ASAAS (cartão recorrente). Se preenchido, o usuário pode cancelar. */
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['shop:read'])]
    private ?string $asaasSubscriptionId = null;

    /** ID do cliente no ASAAS */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $asaasCustomerId = null;

    public function __construct()
    {
        $this->barbers = new ArrayCollection();
        $this->services = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->clients = new ArrayCollection();
        $this->schedules = new ArrayCollection();
        $this->members = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getInstagram(): ?string
    {
        return $this->instagram;
    }

    public function setInstagram(?string $instagram): static
    {
        $this->instagram = $instagram;
        return $this;
    }

    public function isAutoConfirmAppointments(): bool
    {
        return $this->autoConfirmAppointments;
    }

    public function setAutoConfirmAppointments(bool $autoConfirmAppointments): static
    {
        $this->autoConfirmAppointments = $autoConfirmAppointments;
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getClosedDates(): array
    {
        if ($this->closedDates === null) {
            return [];
        }

        return $this->closedDates;
    }

    /**
     * @param list<string> $closedDates
     */
    public function setClosedDates(array $closedDates): static
    {
        $this->closedDates = $closedDates;

        return $this;
    }

    public function isClosedOnDate(\DateTimeInterface $date): bool
    {
        $key = $date->format('Y-m-d');

        return \in_array($key, $this->getClosedDates(), true);
    }

    public function getEvolutionInstanceName(): ?string
    {
        return $this->evolutionInstanceName;
    }

    public function setEvolutionInstanceName(?string $evolutionInstanceName): static
    {
        $this->evolutionInstanceName = $evolutionInstanceName;
        return $this;
    }

    public function getEvolutionInstanceApiKey(): ?string
    {
        return $this->evolutionInstanceApiKey;
    }

    public function setEvolutionInstanceApiKey(?string $evolutionInstanceApiKey): static
    {
        $this->evolutionInstanceApiKey = $evolutionInstanceApiKey;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        if ($owner !== null) {
            $owner->setOwnedShop($this);
            $owner->setShop($this);
        }
        return $this;
    }

    /** @return Collection<int, User> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    /**
     * @return Collection<int, Barber>
     */
    public function getBarbers(): Collection
    {
        return $this->barbers;
    }

    public function addBarber(Barber $barber): static
    {
        if (!$this->barbers->contains($barber)) {
            $this->barbers->add($barber);
            $barber->setShop($this);
        }
        return $this;
    }

    public function removeBarber(Barber $barber): static
    {
        if ($this->barbers->removeElement($barber)) {
            if ($barber->getShop() === $this) {
                $barber->setShop(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->setShop($this);
        }
        return $this;
    }

    public function removeService(Service $service): static
    {
        if ($this->services->removeElement($service)) {
            if ($service->getShop() === $this) {
                $service->setShop(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setShop($this);
        }
        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            if ($product->getShop() === $this) {
                $product->setShop(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Client>
     */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function addClient(Client $client): static
    {
        if (!$this->clients->contains($client)) {
            $this->clients->add($client);
            $client->setShop($this);
        }
        return $this;
    }

    public function removeClient(Client $client): static
    {
        if ($this->clients->removeElement($client)) {
            if ($client->getShop() === $this) {
                $client->setShop(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ShopSchedule>
     */
    public function getSchedules(): Collection
    {
        return $this->schedules;
    }

    public function addSchedule(ShopSchedule $schedule): static
    {
        if (!$this->schedules->contains($schedule)) {
            $this->schedules->add($schedule);
            $schedule->setShop($this);
        }
        return $this;
    }

    public function removeSchedule(ShopSchedule $schedule): static
    {
        if ($this->schedules->removeElement($schedule)) {
            if ($schedule->getShop() === $this) {
                $schedule->setShop(null);
            }
        }
        return $this;
    }

    public function getSubscriptionPlan(): ?string
    {
        return $this->subscriptionPlan;
    }

    public function setSubscriptionPlan(?string $subscriptionPlan): static
    {
        $this->subscriptionPlan = $subscriptionPlan;
        return $this;
    }

    public function getSubscriptionEndsAt(): ?\DateTimeImmutable
    {
        return $this->subscriptionEndsAt;
    }

    public function setSubscriptionEndsAt(?\DateTimeImmutable $subscriptionEndsAt): static
    {
        $this->subscriptionEndsAt = $subscriptionEndsAt;
        return $this;
    }

    public function getAsaasSubscriptionId(): ?string
    {
        return $this->asaasSubscriptionId;
    }

    public function setAsaasSubscriptionId(?string $asaasSubscriptionId): static
    {
        $this->asaasSubscriptionId = $asaasSubscriptionId;
        return $this;
    }

    public function getAsaasCustomerId(): ?string
    {
        return $this->asaasCustomerId;
    }

    public function setAsaasCustomerId(?string $asaasCustomerId): static
    {
        $this->asaasCustomerId = $asaasCustomerId;
        return $this;
    }

    /** Assinatura está ativa:
     * - Se houver subscriptionEndsAt: data de fim >= hoje
     * - Se for plano gratuito (trial ou sem plano): createdAt + TRIAL_PERIOD_DAYS >= hoje
     */
    public function isSubscriptionActive(): bool
    {
        $today = new \DateTimeImmutable('today');

        // Se existe uma data de fim configurada (planos pagos), usa ela como referência
        if ($this->subscriptionEndsAt !== null) {
            return $this->subscriptionEndsAt >= $today;
        }

        // Plano gratuito (trial): período definido em TRIAL_PERIOD_DAYS a partir da criação
        if ($this->subscriptionPlan === null || $this->subscriptionPlan === 'trial') {
            if ($this->createdAt === null) {
                // fallback defensivo: se por algum motivo não tiver createdAt, considera ativo
                return true;
            }

            $trialEnd = $this->createdAt->modify('+' . self::TRIAL_PERIOD_DAYS . ' days');
            return $trialEnd >= $today;
        }

        // Qualquer outro caso sem subscriptionEndsAt é considerado ativo por segurança
        return true;
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
