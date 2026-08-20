<?php

namespace App\Entity;

use App\Repository\PlanServiceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Item de um Plan: quantidade de um determinado Service incluída por ciclo.
 */
#[ORM\Entity(repositoryClass: PlanServiceRepository::class)]
class PlanService
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['plan:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Plan $plan = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['plan:read'])]
    private ?Service $service = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['plan:read'])]
    private int $quantityPerCycle = 1;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function getQuantityPerCycle(): int
    {
        return $this->quantityPerCycle;
    }

    public function setQuantityPerCycle(int $quantityPerCycle): static
    {
        $this->quantityPerCycle = $quantityPerCycle;
        return $this;
    }
}
