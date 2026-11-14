<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Entity\Embeddable\Recipient;
use App\Entity\Enrollment;
use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates the invoice for an enrollment (datenmodell.md 3.7): copies recipient and amount, assigns the next number
 * of the year.
 *
 * Numbers: the next number is MAX(number) + 1 of the year. Two requests at the same moment would both read the same
 * maximum. So the number is assigned inside a transaction that first takes a PostgreSQL advisory lock: a second
 * request waits at the lock until the first has committed, then reads the new maximum. The unique constraint on
 * (year, number) stays as a safety net.
 *
 * The same goes for "one invoice per enrollment" and "credits up to the amount": problems() and the voter look at
 * objects loaded at the start of the request. Both are checked again under the lock, in the database
 * (docs/erklaerungen/15-sperren.md).
 */
final readonly class InvoiceIssuer
{
    /**
     * Any fixed number; it names the lock, all requests that assign invoice numbers use the same one.
     */
    private const int NUMBER_LOCK = 3_700_001;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvoiceRepository $invoices,
        private SettingsRepository $settings,
    ) {
    }

    /**
     * What prevents creating the invoice, as translation keys (domain admin); empty if nothing does.
     *
     * @return list<string>
     */
    public function problems(Enrollment $enrollment): array
    {
        $problems = [];

        if ($enrollment->getInvoices()->exists(static fn (int $key, Invoice $invoice): bool => !$invoice->isCreditNote())) {
            $problems[] = 'invoice.problem.already_issued';
        }
        if (!$this->settings->current()->isSenderComplete()) {
            $problems[] = 'invoice.problem.sender';
        }
        if ($enrollment->getPriceCents() <= 0) {
            $problems[] = 'invoice.problem.price';
        }
        if ($enrollment->isBillToGuardian() && null === $enrollment->getStudent()?->getGuardian()) {
            $problems[] = 'invoice.problem.no_guardian';
        } elseif (!$this->recipientFor($enrollment)?->getAddress()->isComplete()) {
            $problems[] = 'invoice.problem.address';
        }

        return $problems;
    }

    /**
     * Who gets the invoice: the guardian if the enrollment says so, otherwise the student.
     */
    public function recipientFor(Enrollment $enrollment): ?Recipient
    {
        $student = $enrollment->getStudent();
        if (null === $student) {
            return null;
        }

        if ($enrollment->isBillToGuardian()) {
            $guardian = $student->getGuardian();

            return null === $guardian ? null : new Recipient($guardian->getName(), $guardian->getAddress());
        }

        return new Recipient($student->getFullName(), $student->getAddress());
    }

    /**
     * Creates and saves the invoice.
     *
     * @throws \LogicException            if problems() is not empty (the controller checks first)
     * @throws InvoiceConflictException if another request has created the invoice in the meantime
     */
    public function issue(Enrollment $enrollment, InvoiceDraft $draft): Invoice
    {
        $recipient = $this->recipientFor($enrollment);
        $issuedOn = $draft->issuedOn;
        $dueOn = $draft->dueOn;
        if ([] !== $this->problems($enrollment) || null === $recipient || null === $issuedOn || null === $dueOn) {
            throw new \LogicException('The invoice cannot be created, check problems() first.');
        }

        return $this->withNextNumber((int) $issuedOn->format('Y'), function (int $year, int $number) use ($enrollment, $draft, $recipient, $issuedOn, $dueOn): Invoice {
            if ($this->invoices->hasInvoiceFor($enrollment)) {
                throw new InvoiceConflictException('The enrollment already has an invoice.');
            }

            $invoice = new Invoice(
                $enrollment,
                $year,
                $number,
                $issuedOn,
                $dueOn,
                $draft->secondInstallmentDueOn,
                $enrollment->getPriceCents(),
                $draft->description,
                $recipient,
            );
            $enrollment->getInvoices()->add($invoice);

            return $invoice;
        });
    }

    /**
     * Creates and saves a credit note for an invoice: negative amount, own number, same recipient (datenmodell.md
     * 3.7). It is due on the day it is issued; the refund is recorded as a negative payment on it.
     *
     * @throws \LogicException            if the amount is not between 1 cent and what can still be credited
     * @throws InvoiceConflictException if another credit note in the meantime leaves less than the amount
     */
    public function credit(Invoice $invoice, CreditDraft $draft): Invoice
    {
        $original = $invoice->getOriginal();
        $issuedOn = $draft->issuedOn;
        $cents = $draft->amountCents;
        if (null === $issuedOn || null === $cents || $cents <= 0 || $cents > $original->getCreditableCents()) {
            throw new \LogicException('Invalid credit amount.');
        }

        return $this->withNextNumber((int) $issuedOn->format('Y'), function (int $year, int $number) use ($original, $draft, $issuedOn, $cents): Invoice {
            if ($cents > $this->invoices->creditableCents($original)) {
                throw new InvoiceConflictException('The credit exceeds what can still be credited.');
            }

            $note = new Invoice(
                $original->getEnrollment(),
                $year,
                $number,
                $issuedOn,
                $issuedOn,
                null,
                -$cents,
                $draft->description,
                $original->getRecipient(),
                $original,
            );
            $original->getEnrollment()->getInvoices()->add($note);

            return $note;
        });
    }

    /**
     * Runs $create with the next free number of the year inside a transaction that holds the lock for invoice
     * numbers, persists the result and commits. $create runs after the lock is taken, so its database queries see
     * everything committed by a request that held the lock before (PostgreSQL's default READ COMMITTED). If it
     * throws, the transaction is rolled back and nothing is saved.
     *
     * @param callable(int, int): Invoice $create receives year and number
     */
    private function withNextNumber(int $year, callable $create): Invoice
    {
        return $this->entityManager->wrapInTransaction(function () use ($year, $create): Invoice {
            // Released automatically at the end of the transaction (commit or rollback)
            $this->entityManager->getConnection()->executeStatement('SELECT pg_advisory_xact_lock(?)', [self::NUMBER_LOCK]);

            $invoice = $create($year, $this->invoices->nextNumber($year));
            $this->entityManager->persist($invoice);

            return $invoice;
        });
    }
}
