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

    /** Nome da instância na Evolution API (uma por barbearia). */
    #[ORM\Column(length: 80, nullable: true)]
    #[Groups(['shop:read', 'shop:write'])]
    private ?string $evolutionInstanceName = null;

    /** API Key da instância retornada pela Evolution ao criar. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $evolutionInstanceApiKey = null;

    #[ORM\OneToOne(inversedBy: 'shop', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['shop:read'])]
    private ?User $owner = null;

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

    #[ORM\Column]
    #[Groups(['shop:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['shop:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->barbers = new ArrayCollection();
        $this->services = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->clients = new ArrayCollection();
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
        return $this;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
