<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Embeddable\Recipient;
use App\Entity\Enum\InvoiceStatus;
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
 *
 * Credit note ("Gutschrift", datenmodell.md 3.7): an invoice with a negative amount and its own number that points
 * to the original ($credits). An original and its credit notes are settled together: owed is the original amount
 * minus the credits, paid is the sum of all payments of the group (refunds are negative payments on a credit note).
 * So a credit works both before payment (less to pay) and after (refund due).
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
     * Credit notes of this invoice (inverse side of $credits).
     *
     * @var Collection<int, Invoice>
     */
    #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'credits')]
    #[ORM\OrderBy(['issuedOn' => 'ASC', 'id' => 'ASC'])]
    private Collection $creditNotes;

    /**
     * The deadline Sonja was last reminded of (App\Command\RemindOverdueInvoicesCommand), so that each deadline
     * leads to exactly one reminder mail. Technical bookkeeping, not part of the receipt: the only value that changes.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $remindedFor = null;

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
        // Set on a credit note only: the invoice it corrects
        #[ORM\ManyToOne(inversedBy: 'creditNotes')]
        private ?Invoice $credits = null,
    ) {
        $this->payments = new ArrayCollection();
        $this->creditNotes = new ArrayCollection();
        $credits?->creditNotes->add($this);
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

    public function getCredits(): ?self
    {
        return $this->credits;
    }

    public function isCreditNote(): bool
    {
        return null !== $this->credits;
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getCreditNotes(): Collection
    {
        return $this->creditNotes;
    }

    /**
     * The original invoice of the group (itself, or the invoice a credit note corrects).
     */
    public function getOriginal(): self
    {
        return $this->credits ?? $this;
    }

    /**
     * How much may still be credited: the original amount minus all credits (credit amounts are negative).
     */
    public function getCreditableCents(): int
    {
        $original = $this->getOriginal();

        return $original->amountCents + array_sum($original->creditNotes->map(static fn (self $note): int => $note->amountCents)->getValues());
    }

    /**
     * What the recipient owes for the whole group: original amount minus credits.
     */
    public function getOwedCents(): int
    {
        return $this->getCreditableCents();
    }

    /**
     * Sum of all payments of the group; refunds are negative.
     */
    public function getPaidCents(): int
    {
        $paid = 0;
        $original = $this->getOriginal();
        foreach ([$original, ...$original->creditNotes] as $invoice) {
            foreach ($invoice->payments as $payment) {
                $paid += $payment->getAmountCents();
            }
        }

        return $paid;
    }

    /**
     * Positive: still to be paid. Negative: to be refunded.
     */
    public function getOpenCents(): int
    {
        return $this->getOwedCents() - $this->getPaidCents();
    }

    /**
     * Calculated, never stored, so it cannot get out of date (datenmodell.md 3.8).
     */
    public function getStatus(): InvoiceStatus
    {
        $open = $this->getOpenCents();

        return match (true) {
            $open < 0 => InvoiceStatus::RefundDue,
            0 === $open => InvoiceStatus::Paid,
            $this->getPaidCents() > 0 => InvoiceStatus::PartlyPaid,
            default => InvoiceStatus::Open,
        };
    }

    /**
     * The latest deadline before $today that is not met, or null (datenmodell.md 3.7): with instalments at least the
     * first instalment by dueOn and everything by the second date, otherwise everything by dueOn. Credit notes have
     * no deadline.
     */
    public function getMissedDeadline(\DateTimeImmutable $today): ?\DateTimeImmutable
    {
        if ($this->isCreditNote()) {
            return null;
        }

        $owed = $this->getOwedCents();
        $deadlines = null === $this->secondInstallmentDueOn
            ? [[$this->dueOn, $owed]]
            : [[$this->dueOn, min($this->getFirstInstallmentCents(), $owed)], [$this->secondInstallmentDueOn, $owed]];

        $missed = null;
        foreach ($deadlines as [$deadline, $required]) {
            if ($deadline < $today && $this->getPaidCents() < $required) {
                $missed = $deadline;
            }
        }

        return $missed;
    }

    /**
     * Whether Sonja has to be reminded today: a deadline is missed that she was not reminded of yet.
     */
    public function needsReminder(\DateTimeImmutable $today): bool
    {
        $missed = $this->getMissedDeadline($today);

        return null !== $missed && (null === $this->remindedFor || $this->remindedFor < $missed);
    }

    public function getRemindedFor(): ?\DateTimeImmutable
    {
        return $this->remindedFor;
    }

    public function markReminded(\DateTimeImmutable $deadline): void
    {
        $this->remindedFor = $deadline;
    }
}
