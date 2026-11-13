<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Embeddable\Address;
use App\Entity\Embeddable\Recipient;
use App\Entity\Invoice;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Invoices for tests and the local data. Number and year must be given (or left at the defaults): the factory
 * bypasses App\Invoice\InvoiceIssuer, which assigns numbers in the app.
 *
 * @extends PersistentObjectFactory<Invoice>
 */
final class InvoiceFactory extends PersistentObjectFactory
{
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
            // Keep the inverse side in sync, voters and pages read it in the same request
            $invoice->getEnrollment()->getInvoices()->add($invoice);
        });
    }
}
