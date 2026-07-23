<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ACCESS_OWNER = 'owner';
    public const ACCESS_BARBER = 'barber';
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'shop:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['user:read', 'user:write', 'shop:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 150)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $email = null;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $avatar = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['user:read'])]
    private array $roles = [];

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['user:read'])]
    private ?Shop $shop = null;

    #[ORM\OneToOne(mappedBy: 'user')]
    #[Groups(['user:read'])]
    private ?Barber $barber = null;

    #[ORM\OneToOne(mappedBy: 'owner')]
    private ?Shop $ownedShop = null;

    #[ORM\Column(length: 20, options: ['default' => self::ACCESS_OWNER])]
    #[Groups(['user:read'])]
    private string $accessRole = self::ACCESS_OWNER;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['user:read'])]
    private bool $mustChangePassword = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $sessionVersion = 0;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read'])]
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        $roles[] = $this->isOwner() ? 'ROLE_OWNER' : 'ROLE_BARBER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
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

    public function getBarber(): ?Barber
    {
        return $this->barber;
    }

    public function setBarber(?Barber $barber): static
    {
        if ($barber !== null && $barber->getUser() !== $this) {
            $barber->setUser($this);
        }
        $this->barber = $barber;
        return $this;
    }

    public function getOwnedShop(): ?Shop
    {
        return $this->ownedShop;
    }

    public function setOwnedShop(?Shop $shop): static
    {
        $this->ownedShop = $shop;
        return $this;
    }

    public function getAccessRole(): string
    {
        return $this->accessRole;
    }

    public function setAccessRole(string $accessRole): static
    {
        if (!in_array($accessRole, [self::ACCESS_OWNER, self::ACCESS_BARBER], true)) {
            throw new \InvalidArgumentException('Papel de acesso inválido');
        }
        $this->accessRole = $accessRole;
        return $this;
    }

    public function isOwner(): bool
    {
        return $this->accessRole === self::ACCESS_OWNER;
    }

    public function isBarberUser(): bool
    {
        return $this->accessRole === self::ACCESS_BARBER;
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

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function setMustChangePassword(bool $mustChange): static
    {
        $this->mustChangePassword = $mustChange;
        return $this;
    }

    public function getSessionVersion(): int
    {
        return $this->sessionVersion;
    }

    public function revokeSessions(): static
    {
        $this->sessionVersion++;
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

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }
}
