<?php

namespace App\Entity;

use App\Repository\ShopScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Horário de funcionamento da barbearia por dia da semana.
 * dayOfWeek: 0 = Domingo, 1 = Segunda, ..., 6 = Sábado.
 */
#[ORM\Entity(repositoryClass: ShopScheduleRepository::class)]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'shop_day_unique', columns: ['shop_id', 'day_of_week'])]
class ShopSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['schedule:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'schedules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shop $shop = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Range(min: 0, max: 6)]
    #[Groups(['schedule:read', 'schedule:write'])]
    private ?int $dayOfWeek = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['schedule:read', 'schedule:write'])]
    private bool $isOpen = true;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    #[Groups(['schedule:read', 'schedule:write'])]
    private ?\DateTimeInterface $timeOpen = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    #[Groups(['schedule:read', 'schedule:write'])]
    private ?\DateTimeInterface $timeClose = null;

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

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function setIsOpen(bool $isOpen): static
    {
        $this->isOpen = $isOpen;
        return $this;
    }

    public function getTimeOpen(): ?\DateTimeInterface
    {
        return $this->timeOpen;
    }

    public function setTimeOpen(?\DateTimeInterface $timeOpen): static
    {
        $this->timeOpen = $timeOpen;
        return $this;
    }

    public function getTimeClose(): ?\DateTimeInterface
    {
        return $this->timeClose;
    }

    public function setTimeClose(?\DateTimeInterface $timeClose): static
    {
        $this->timeClose = $timeClose;
        return $this;
    }
}
