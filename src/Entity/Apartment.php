<?php

namespace App\Entity;

use App\Repository\ApartmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ApartmentRepository::class)]
class Apartment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(
        value: 1,
        message: 'Number of bedrooms must be at least 1.'
    )]
    #[Assert\NotBlank(message: 'Number of bedrooms is required.')]
    private ?int $bedrooms = null;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(
        value: 1,
        message: 'Number of bathrooms must be at least 1.'
    )]
    #[Assert\NotBlank(message: 'Number of bathrooms is required.')]
    private ?int $bathrooms = null;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(
        value: 0,
        message: 'Rent amount cannot be negative.'
    )]
    #[Assert\NotBlank(message: 'Rent amount is required.')]
    private ?float $rentAmount = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\OneToMany(mappedBy: 'apartment', targetEntity: Payment::class)]
    private Collection $payments;

    #[ORM\OneToMany(mappedBy: 'apartment', targetEntity: Lease::class)]
    private Collection $leases;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
        $this->leases = new ArrayCollection();
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getBedrooms(): ?int
    {
        return $this->bedrooms;
    }

    public function setBedrooms(int $bedrooms): static
    {
        $this->bedrooms = $bedrooms;
        return $this;
    }

    public function getBathrooms(): ?int
    {
        return $this->bathrooms;
    }

    public function setBathrooms(int $bathrooms): static
    {
        $this->bathrooms = $bathrooms;
        return $this;
    }

    public function getRentAmount(): ?float
    {
        return $this->rentAmount;
    }

    public function setRentAmount(float $rentAmount): static
    {
        $this->rentAmount = $rentAmount;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return Collection<int, Lease>
     */
    public function getLeases(): Collection
    {
        return $this->leases;
    }

    public function addLease(Lease $lease): static
    {
        if (!$this->leases->contains($lease)) {
            $this->leases->add($lease);
            $lease->setApartment($this);
        }
        return $this;
    }

    public function removeLease(Lease $lease): static
    {
        if ($this->leases->removeElement($lease)) {
            if ($lease->getApartment() === $this) {
                $lease->setApartment(null);
            }
        }
        return $this;
    }

    public function getCurrentLease(): ?Lease
    {
        foreach ($this->leases as $lease) {
            if ($lease->isActive()) {
                return $lease;
            }
        }
        return null;
    }

    public function getCurrentTenant(): ?Tenant
    {
        $currentLease = $this->getCurrentLease();
        return $currentLease ? $currentLease->getTenant() : null;
    }

    public function isOccupied(): bool
    {
        return $this->getCurrentLease() !== null;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setApartment($this);
        }
        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getApartment() === $this) {
                $payment->setApartment(null);
            }
        }
        return $this;
    }
}
