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

        if (!$enrollment->getInvoices()->isEmpty()) {
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
     * @throws \LogicException if problems() is not empty (the controller checks first)
     */
    public function issue(Enrollment $enrollment, InvoiceDraft $draft): Invoice
    {
        $recipient = $this->recipientFor($enrollment);
        $issuedOn = $draft->issuedOn;
        $dueOn = $draft->dueOn;
        if ([] !== $this->problems($enrollment) || null === $recipient || null === $issuedOn || null === $dueOn) {
            throw new \LogicException('The invoice cannot be created, check problems() first.');
        }

        $year = (int) $issuedOn->format('Y');

        return $this->entityManager->wrapInTransaction(function () use ($enrollment, $draft, $recipient, $issuedOn, $dueOn, $year): Invoice {
            // Released automatically at the end of the transaction (commit or rollback)
            $this->entityManager->getConnection()->executeStatement('SELECT pg_advisory_xact_lock(?)', [self::NUMBER_LOCK]);

            $invoice = new Invoice(
                $enrollment,
                $year,
                $this->invoices->nextNumber($year),
                $issuedOn,
                $dueOn,
                $draft->secondInstallmentDueOn,
                $enrollment->getPriceCents(),
                $draft->description,
                $recipient,
            );
            $this->entityManager->persist($invoice);
            $enrollment->getInvoices()->add($invoice);

            return $invoice;
        });
    }
}
