<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fullName = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private bool $verified = false;

    #[ORM\OneToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Tenant $tenant = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $notifyEmail = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $notifyPush = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $notifyPaymentReminders = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $notifyMaintenance = true;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $passwordKey = "\0".self::class."\0password";
        if (isset($data[$passwordKey])) {
            $data[$passwordKey] = hash('crc32c', $data[$passwordKey]);
        }

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true);
    }

    public function isStaff(): bool
    {
        return in_array('ROLE_STAFF', $this->roles, true);
    }

    public function isCustomer(): bool
    {
        return in_array('ROLE_CUSTOMER', $this->roles, true);
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

    public function getDisplayName(): string
    {
        if ($this->fullName !== null && trim($this->fullName) !== '') {
            return $this->fullName;
        }

        return $this->email ?? 'User';
    }

    public function isNotifyEmail(): bool
    {
        return $this->notifyEmail;
    }

    public function setNotifyEmail(bool $notifyEmail): static
    {
        $this->notifyEmail = $notifyEmail;

        return $this;
    }

    public function isNotifyPush(): bool
    {
        return $this->notifyPush;
    }

    public function setNotifyPush(bool $notifyPush): static
    {
        $this->notifyPush = $notifyPush;

        return $this;
    }

    public function isNotifyPaymentReminders(): bool
    {
        return $this->notifyPaymentReminders;
    }

    public function setNotifyPaymentReminders(bool $notifyPaymentReminders): static
    {
        $this->notifyPaymentReminders = $notifyPaymentReminders;

        return $this;
    }

    public function isNotifyMaintenance(): bool
    {
        return $this->notifyMaintenance;
    }

    public function setNotifyMaintenance(bool $notifyMaintenance): static
    {
        $this->notifyMaintenance = $notifyMaintenance;

        return $this;
    }
}
