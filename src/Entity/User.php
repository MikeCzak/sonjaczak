<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Login account (Mike, Sonja, later students). Personal data of students lives in Student, not here.
 * Roles and access: docs/planung/rollen.md.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
// "user" is a reserved word in PostgreSQL and would have to be quoted in every query, so the table gets a prefix
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_app_user_email', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_app_user_webauthn_handle', fields: ['webauthnHandle'])]
#[UniqueEntity(fields: ['email'], message: 'user.email.taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string ROLE_ADMIN = 'ROLE_ADMIN';
    public const string ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email;

    /**
     * Roles as stored; the role hierarchy (config/packages/security.yaml) adds the inherited ones at runtime,
     * so never check access with getRoles(), always with isGranted().
     *
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * The password hash, never the password itself. Empty until the account holder sets one (invitation link).
     */
    #[ORM\Column]
    private string $password = '';

    /**
     * Deactivated accounts cannot log in (App\Security\UserChecker). Kept instead of deleted, so it stays visible
     * who had access.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /**
     * Identifies the account to the devices that hold its passkeys (WebAuthn "user handle", umsetzung-admin.md B5):
     * random, so it reveals nothing about the person or the order of accounts (the WebAuthn specification forbids
     * personal data in it). 32 random bytes as 64 hex characters, ASCII so it fits a text column; the specification
     * allows up to 64 bytes. Never changes.
     */
    #[ORM\Column(length: 64)]
    private string $webauthnHandle;

    public function __construct(string $email)
    {
        $this->setEmail($email);
        $this->webauthnHandle = bin2hex(random_bytes(32));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWebauthnHandle(): string
    {
        return $this->webauthnHandle;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Stored in lower case: PostgreSQL compares strings case-sensitively, the login must not
     * (UserRepository::loadUserByIdentifier() lowercases the input the same way).
     */
    public function setEmail(string $email): static
    {
        $this->email = self::normalizeEmail($email);

        return $this;
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('A user without e-mail address cannot be identified.');
        }

        return $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique($roles));

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): static
    {
        $this->password = $hashedPassword;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function activate(): static
    {
        $this->active = true;

        return $this;
    }

    /**
     * Also clears the password hash: open sessions of the account end at their next request, because the checksum
     * stored in them no longer matches (see __serialize()). Activating again needs a new invitation.
     */
    public function deactivate(): static
    {
        $this->active = false;
        $this->password = '';

        return $this;
    }

    /**
     * The one role an admin account has in the database (ROLE_ADMIN or ROLE_SUPER_ADMIN); null for other accounts.
     */
    public function getAdminRole(): ?string
    {
        foreach ([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN] as $role) {
            if (\in_array($role, $this->roles, true)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * The user is stored in the session. Instead of the password hash, the session only gets a CRC32C checksum
     * of it: enough for Symfony to notice a changed password and log out other sessions, useless for cracking.
     * Supported since Symfony 7.3 (generated by make:user).
     *
     * @return array<mixed>
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
