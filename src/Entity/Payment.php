<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\GreaterThanOrEqual(
        value: 0,
        message: 'Payment amount cannot be negative.'
    )]
    #[Assert\NotBlank(message: 'Payment amount is required.')]
    private ?string $amount = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $paymentDate = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Tenant $tenant = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Apartment $apartment = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymongoLinkId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymongoPaymentId = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPaymentDate(): ?\DateTimeInterface
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(\DateTimeInterface $paymentDate): static
    {
        $this->paymentDate = $paymentDate;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;
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

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
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

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setTenant(?Tenant $tenant): static
    {
        $this->tenant = $tenant;
        return $this;
    }

    public function getApartment(): ?Apartment
    {
        return $this->apartment;
    }

    public function setApartment(?Apartment $apartment): static
    {
        $this->apartment = $apartment;
        return $this;
    }

    public function getPaymongoLinkId(): ?string
    {
        return $this->paymongoLinkId;
    }

    public function setPaymongoLinkId(?string $paymongoLinkId): static
    {
        $this->paymongoLinkId = $paymongoLinkId;

        return $this;
    }

    public function getPaymongoPaymentId(): ?string
    {
        return $this->paymongoPaymentId;
    }

    public function setPaymongoPaymentId(?string $paymongoPaymentId): static
    {
        $this->paymongoPaymentId = $paymongoPaymentId;

        return $this;
    }

    public function isPayableOnline(): bool
    {
        return in_array($this->status, ['pending', 'overdue'], true);
    }
}
