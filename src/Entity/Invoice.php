<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Embeddable\Recipient;
use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Invoice ("Honorarnote") for one enrollment (datenmodell.md 3.7). Created by App\Invoice\InvoiceIssuer.
 *
 * Immutable: everything is set in the constructor and there are no setters. Corrections are made with a new
 * receipt (credit note, step D3). Name, address, amount and description are copies, not references.
 */
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\UniqueConstraint(name: 'invoice_number', columns: ['year', 'number'])]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'invoice')]
    #[ORM\OrderBy(['paidOn' => 'ASC', 'id' => 'ASC'])]
    private Collection $payments;

    /**
     * @param int $year   year of issuedOn; with $number the invoice number "16/2026"
     * @param int $number consecutive within the year, starting at 1
     */
    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'invoices')]
        #[ORM\JoinColumn(nullable: false)]
        private Enrollment $enrollment,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $year,
        #[ORM\Column]
        private int $number,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $issuedOn,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $dueOn,
        // Null: no instalments, the whole amount is due on dueOn
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $secondInstallmentDueOn,
        #[ORM\Column]
        private int $amountCents,
        // e.g. "Gesangsunterricht im Wintersemester 2026/27"
        #[ORM\Column(length: 255)]
        private string $description,
        #[ORM\Embedded(class: Recipient::class)]
        private Recipient $recipient,
    ) {
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnrollment(): Enrollment
    {
        return $this->enrollment;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * As printed: "16/2026".
     */
    public function getDisplayNumber(): string
    {
        return $this->number.'/'.$this->year;
    }

    public function getIssuedOn(): \DateTimeImmutable
    {
        return $this->issuedOn;
    }

    public function getDueOn(): \DateTimeImmutable
    {
        return $this->dueOn;
    }

    public function getSecondInstallmentDueOn(): ?\DateTimeImmutable
    {
        return $this->secondInstallmentDueOn;
    }

    public function hasInstallments(): bool
    {
        return null !== $this->secondInstallmentDueOn;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    /**
     * Half of the amount; with an odd number of cents the first instalment gets the extra cent.
     * Calculated, not stored (datenmodell.md 3.7).
     */
    public function getFirstInstallmentCents(): int
    {
        return intdiv($this->amountCents + 1, 2);
    }

    public function getSecondInstallmentCents(): int
    {
        return $this->amountCents - $this->getFirstInstallmentCents();
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getRecipient(): Recipient
    {
        return $this->recipient;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }
}
