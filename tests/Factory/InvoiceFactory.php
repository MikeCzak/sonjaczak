<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Embeddable\Address;
use App\Entity\Embeddable\Recipient;
use App\Entity\Invoice;
use App\Entity\InvoicePdf;
use App\Invoice\InvoicePdfRenderer;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

use function Zenstruck\Foundry\Persistence\persist;

/**
 * Invoices for tests and the local data. Number and year must be given (or left at the defaults): the factory
 * bypasses App\Invoice\InvoiceIssuer, which assigns numbers in the app. For the same reason there is no PDF, unless
 * withPdf() is used (like an invoice from before step D2, which tests for the missing PDF rely on).
 *
 * @extends PersistentObjectFactory<Invoice>
 */
final class InvoiceFactory extends PersistentObjectFactory
{
    public function __construct(private readonly InvoicePdfRenderer $pdfRenderer)
    {
        parent::__construct();
    }

    /**
     * Also stores the PDF, rendered by the real renderer with the current settings (as the issuer does).
     */
    public function withPdf(): static
    {
        return $this->afterPersist(function (Invoice $invoice): void {
            persist(InvoicePdf::class, ['invoice' => $invoice, 'content' => $this->pdfRenderer->render($invoice), 'fileName' => $invoice->getFileName()]);
        });
    }
    #[\Override]
    public static function class(): string
    {
        return Invoice::class;
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'enrollment' => EnrollmentFactory::new(),
            'year' => 2026,
            'number' => self::faker()->unique()->numberBetween(1, 9999),
            'issuedOn' => new \DateTimeImmutable('2026-10-02'),
            'dueOn' => new \DateTimeImmutable('2026-10-16'),
            'secondInstallmentDueOn' => null,
            'amountCents' => 49500,
            'description' => 'Gesangsunterricht im Wintersemester 2026/27',
            'recipient' => new Recipient(self::faker()->name(), new Address(self::faker()->streetAddress(), self::faker()->postcode(), self::faker()->city())),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Invoice $invoice): void {
            // Keep the inverse side in sync, voters and pages read it in the same request (unless Foundry already
            // has, see EnrollmentFactory)
            if (!$invoice->getEnrollment()->getInvoices()->contains($invoice)) {
                $invoice->getEnrollment()->getInvoices()->add($invoice);
            }
        });
    }
}
