<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Embeddable\Address;
use App\Repository\SettingsRepository;
use App\Theme\Theme;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Settings Sonja changes in the admin area (docs/planung/datenmodell.md 3.9). Exactly one row: the id is always 1,
 * so the primary key itself prevents a second row. Typed columns instead of a key-value table, so every setting has
 * its type and validation.
 *
 * Sender data are personal data of Sonja: empty in code and fixtures, entered in production only.
 */
#[ORM\Entity(repositoryClass: SettingsRepository::class)]
class Settings
{
    public const int ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    #[ORM\Column(length: 20, enumType: Theme::class)]
    private Theme $theme = Theme::Classic;

    /**
     * Default for new courses and trial lessons.
     */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 10, max: 180)]
    private int $lessonMinutes = 50;

    /**
     * Make-up rule (App\Lesson\MakeUpRule): a make-up lesson is due below plannedLessons minus this number.
     */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 0, max: 20)]
    private int $makeUpThreshold = 0;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $senderName = null;

    /**
     * Academic title after the name on the invoice.
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $senderTitle = null;

    #[ORM\Embedded(class: Address::class)]
    #[Assert\Valid]
    private Address $senderAddress;

    #[ORM\Column(length: 34, nullable: true)]
    #[Assert\Iban]
    private ?string $iban = null;

    /**
     * Format check only: 4 letters bank, 2 letters country, 2 characters location, optionally 3 characters branch.
     * Assert\Bic would also check the country code, but needs symfony/intl (a large package) for that.
     */
    #[ORM\Column(length: 11, nullable: true)]
    #[Assert\Regex(pattern: '/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', message: 'settings.bic.invalid')]
    private ?string $bic = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $bankName = null;

    /**
     * Place in front of the date on the invoice ("Graz, am …").
     */
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $invoicePlace = null;

    /**
     * Note on the VAT exemption printed on every invoice.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $vatNote = null;

    public function __construct()
    {
        $this->senderAddress = new Address();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getLessonMinutes(): int
    {
        return $this->lessonMinutes;
    }

    public function setLessonMinutes(int $lessonMinutes): static
    {
        $this->lessonMinutes = $lessonMinutes;

        return $this;
    }

    public function getMakeUpThreshold(): int
    {
        return $this->makeUpThreshold;
    }

    public function setMakeUpThreshold(int $makeUpThreshold): static
    {
        $this->makeUpThreshold = $makeUpThreshold;

        return $this;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function setSenderName(?string $senderName): static
    {
        $this->senderName = $senderName;

        return $this;
    }

    public function getSenderTitle(): ?string
    {
        return $this->senderTitle;
    }

    public function setSenderTitle(?string $senderTitle): static
    {
        $this->senderTitle = $senderTitle;

        return $this;
    }

    public function getSenderAddress(): Address
    {
        return $this->senderAddress;
    }

    public function setSenderAddress(Address $senderAddress): static
    {
        $this->senderAddress = $senderAddress;

        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    /**
     * Stored without spaces; shown in groups of four on the invoice.
     */
    public function setIban(?string $iban): static
    {
        $this->iban = null === $iban ? null : strtoupper(str_replace(' ', '', $iban));

        return $this;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): static
    {
        $this->bic = null === $bic ? null : strtoupper(trim($bic));

        return $this;
    }

    public function getBankName(): ?string
    {
        return $this->bankName;
    }

    public function setBankName(?string $bankName): static
    {
        $this->bankName = $bankName;

        return $this;
    }

    public function getInvoicePlace(): ?string
    {
        return $this->invoicePlace;
    }

    public function setInvoicePlace(?string $invoicePlace): static
    {
        $this->invoicePlace = $invoicePlace;

        return $this;
    }

    public function getVatNote(): ?string
    {
        return $this->vatNote;
    }

    public function setVatNote(?string $vatNote): static
    {
        $this->vatNote = $vatNote;

        return $this;
    }

    /**
     * Enough for an invoice: name, address and bank account.
     */
    public function isSenderComplete(): bool
    {
        return null !== $this->senderName && $this->senderAddress->isComplete() && null !== $this->iban;
    }
}
