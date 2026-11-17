<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PasskeyRepository;
use Doctrine\ORM\Mapping as ORM;
use Webauthn\CredentialRecord;

use function Symfony\Component\Clock\now;

/**
 * A passkey of an account (umsetzung-admin.md B5): the public key and what WebAuthn needs to check a login, mapped
 * by the bundle's superclass (config/packages/doctrine.yaml, mapping "Webauthn"); the private key stays on the device.
 * Belongs to the account through userHandle = User::$webauthnHandle, without a foreign key (question 5, nothing
 * stored twice). Accounts are deactivated, never deleted.
 */
#[ORM\Entity(repositoryClass: PasskeyRepository::class)]
#[ORM\Index(name: 'idx_passkey_user_handle', fields: ['userHandle'])]
#[ORM\UniqueConstraint(name: 'uniq_passkey_credential_id', fields: ['publicKeyCredentialId'])]
class Passkey extends CredentialRecord
{
    public const int NAME_MAX_LENGTH = 100;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Chosen by the account holder ("MacBook", "Handy"), shown in "Mein Konto".
     */
    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private string $name = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    /**
     * The bundle creates a plain CredentialRecord after a successful registration; this turns it into an entity.
     */
    public static function fromRecord(CredentialRecord $record, string $name): self
    {
        $passkey = new self(
            $record->publicKeyCredentialId,
            $record->type,
            $record->transports,
            $record->attestationType,
            $record->trustPath,
            $record->aaguid,
            $record->credentialPublicKey,
            $record->userHandle,
            $record->counter,
            $record->otherUI,
            $record->backupEligible,
            $record->backupStatus,
            $record->uvInitialized,
        );
        $passkey->createdAt = now();
        $passkey->rename($name);

        return $passkey;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = mb_substr(trim($name), 0, self::NAME_MAX_LENGTH);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(): void
    {
        $this->lastUsedAt = now();
    }
}
