<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Enum\InvoiceStatus;
use App\Entity\Enum\PaymentMethod;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Repository\InvoiceRepository;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Payments and credit notes (step D3).
 */
final class PaymentTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testStatusIsCalculated(): void
    {
        $invoice = new Invoice(...$this->invoiceArguments(10000));
        self::assertSame(InvoiceStatus::Open, $invoice->getStatus());

        new Payment($invoice, 4000, new \DateTimeImmutable('2026-11-10'), PaymentMethod::Transfer);
        self::assertSame(InvoiceStatus::PartlyPaid, $invoice->getStatus());
        self::assertSame(6000, $invoice->getOpenCents());

        new Payment($invoice, 6000, new \DateTimeImmutable('2026-12-30'), PaymentMethod::Cash);
        self::assertSame(InvoiceStatus::Paid, $invoice->getStatus());
    }

    public function testCreditAfterPaymentNeedsRefund(): void
    {
        $invoice = new Invoice(...$this->invoiceArguments(10000));
        new Payment($invoice, 10000, new \DateTimeImmutable('2026-11-10'), PaymentMethod::Transfer);
        $note = new Invoice(...[...$this->invoiceArguments(-2500), 'credits' => $invoice]);

        // Both pages show the same account
        self::assertSame(InvoiceStatus::RefundDue, $invoice->getStatus());
        self::assertSame(-2500, $note->getOpenCents());
        self::assertSame(7500, $invoice->getCreditableCents());

        new Payment($note, -2500, new \DateTimeImmutable('2026-11-20'), PaymentMethod::Transfer);
        self::assertSame(InvoiceStatus::Paid, $invoice->getStatus());
    }

    public function testCreditBeforePaymentLowersWhatIsOwed(): void
    {
        $invoice = new Invoice(...$this->invoiceArguments(10000));
        new Invoice(...[...$this->invoiceArguments(-2500), 'credits' => $invoice]);

        self::assertSame(7500, $invoice->getOpenCents());
        self::assertSame(InvoiceStatus::Open, $invoice->getStatus());
    }

    public function testPayRestWithOneClick(): void
    {
        $client = $this->loggedInClient();
        $invoice = InvoiceFactory::createOne(['amountCents' => 49500]);

        $client->request('GET', '/admin/honorarnoten/'.$invoice->getId());
        self::assertSelectorTextContains('h1', 'offen');
        self::assertInputValueSame('payment[amountCents]', '495,00');

        $client->submitForm('Zahlung speichern');
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'bezahlt');
        self::assertSelectorNotExists('form[name="payment"]');
    }

    public function testPaymentCannotExceedOpenAmountOrBeInTheFuture(): void
    {
        $client = $this->loggedInClient();
        $invoice = InvoiceFactory::createOne(['amountCents' => 10000]);

        $client->request('GET', '/admin/honorarnoten/'.$invoice->getId());
        $client->submitForm('Zahlung speichern', ['payment[amountCents]' => '100,01', 'payment[paidOn]' => new \DateTimeImmutable('+1 day')->format('Y-m-d')]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'höher als der offene Betrag');
        self::assertSelectorTextContains('main', 'in der Zukunft');
    }

    public function testCreditNoteAndRefund(): void
    {
        $client = $this->loggedInClient();
        $invoice = InvoiceFactory::createOne(['year' => 2026, 'number' => 16, 'amountCents' => 49500]);
        $client->request('GET', '/admin/honorarnoten/'.$invoice->getId());
        $client->submitForm('Zahlung speichern');

        $client->request('GET', '/admin/honorarnoten/'.$invoice->getId().'/gutschrift');
        self::assertInputValueSame('credit[description]', 'Gutschrift zu Honorarnote 16/2026');
        $client->submitForm('Gutschrift erstellen', ['credit[amountCents]' => '66,00', 'credit[issuedOn]' => '2026-12-01']);
        self::assertResponseRedirects();
        $client->followRedirect();

        // Own number in the year of issue, negative amount, refund due
        self::assertSelectorTextContains('h1', 'Gutschrift 17/2026');
        self::assertSelectorTextContains('h1', 'Rückzahlung offen');
        self::assertSelectorTextContains('main', '-66,00 €');

        $client->submitForm('Rückzahlung speichern');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'bezahlt');

        $note = self::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => 17]);
        self::assertInstanceOf(Invoice::class, $note);
        self::assertSame(-6600, $note->getAmountCents());
        self::assertSame(-6600, $note->getPayments()->first() ? $note->getPayments()->first()->getAmountCents() : null);
    }

    public function testCreditIsLimited(): void
    {
        $client = $this->loggedInClient();
        $invoice = InvoiceFactory::createOne(['amountCents' => 10000]);

        $client->request('GET', '/admin/honorarnoten/'.$invoice->getId().'/gutschrift');
        $client->submitForm('Gutschrift erstellen', ['credit[amountCents]' => '100,01']);
        self::assertResponseStatusCodeSame(422);

        $client->submitForm('Gutschrift erstellen', ['credit[amountCents]' => '100,00']);
        $client->followRedirect();

        // Nothing left to credit; no credit note on a credit note
        $client->request('GET', '/admin/honorarnoten/'.$invoice->getId().'/gutschrift');
        self::assertResponseStatusCodeSame(403);
        $note = self::getContainer()->get(InvoiceRepository::class)->findOneBy(['amountCents' => -10000]);
        self::assertInstanceOf(Invoice::class, $note);
        $client->request('GET', '/admin/honorarnoten/'.$note->getId().'/gutschrift');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDeletePayment(): void
    {
        $client = $this->loggedInClient();
        $invoice = InvoiceFactory::createOne(['amountCents' => 10000]);
        $client->request('GET', '/admin/honorarnoten/'.$invoice->getId());
        $client->submitForm('Zahlung speichern');
        $client->followRedirect();

        $client->submitForm('löschen');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'offen');
    }

    /**
     * @return array{enrollment: \App\Entity\Enrollment, year: int, number: int, issuedOn: \DateTimeImmutable, dueOn: \DateTimeImmutable, secondInstallmentDueOn: null, amountCents: int, description: string, recipient: \App\Entity\Embeddable\Recipient}
     */
    private function invoiceArguments(int $cents): array
    {
        $prototype = InvoiceFactory::new()->withoutPersisting()->create();

        return [
            'enrollment' => $prototype->getEnrollment(),
            'year' => 2026,
            'number' => 1,
            'issuedOn' => new \DateTimeImmutable('2026-11-07'),
            'dueOn' => new \DateTimeImmutable('2026-11-13'),
            'secondInstallmentDueOn' => null,
            'amountCents' => $cents,
            'description' => 'Test',
            'recipient' => $prototype->getRecipient(),
        ];
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        return $client;
    }
}
