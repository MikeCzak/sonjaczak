<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Embeddable\Address;
use App\Entity\Enrollment;
use App\Entity\Invoice;
use App\Entity\Settings;
use App\Invoice\CreditDraft;
use App\Invoice\InvoiceConflictException;
use App\Invoice\InvoiceDraft;
use App\Invoice\InvoiceIssuer;
use App\Repository\InvoiceRepository;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\GuardianFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;

/**
 * Invoices (step D1).
 */
final class InvoiceTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testCreateInvoiceFromEnrollment(): void
    {
        $client = $this->loggedInClient();
        $this->completeSettings();
        $enrollment = $this->enrollment();

        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId().'/honorarnote');
        self::assertResponseIsSuccessful();
        self::assertInputValueSame('invoice[description]', 'Gesangsunterricht im Wintersemester 2026/27');

        $client->submitForm('Honorarnote erstellen', [
            'invoice[issuedOn]' => '2026-11-07',
            'invoice[dueOn]' => '2026-11-13',
            'invoice[secondInstallmentDueOn]' => '2026-12-31',
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();

        self::assertSelectorTextContains('h1', 'Honorarnote 1/2026');
        // 495.01 € in two instalments: the first gets the extra cent
        self::assertSelectorTextContains('main', '1. Rate: 247,51 € bis 13.11.2026');
        self::assertSelectorTextContains('main', '2. Rate: 247,50 € bis 31.12.2026');
        self::assertSelectorTextContains('main', 'Musterweg 7');

        // Only one invoice per enrollment
        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId().'/honorarnote');
        self::assertSelectorTextContains('.alert-warning', 'schon eine Honorarnote');
        self::assertSelectorNotExists('form[name="invoice"]');
    }

    public function testNumbersAreConsecutivePerYear(): void
    {
        self::bootKernel();
        $this->completeSettings();
        $issuer = self::getContainer()->get(InvoiceIssuer::class);

        $numbers = [];
        foreach (['2026-11-07', '2026-12-01', '2027-01-10', '2027-02-01'] as $day) {
            $draft = new InvoiceDraft('Gesangsunterricht', new \DateTimeImmutable($day));
            $draft->dueOn = new \DateTimeImmutable($day);
            $numbers[] = $issuer->issue($this->enrollment(), $draft)->getDisplayNumber();
        }

        self::assertSame(['1/2026', '2/2026', '1/2027', '2/2027'], $numbers);
    }

    /**
     * Double click: the second request loaded the enrollment before the first committed, so its checks see no
     * invoice yet. The check under the lock must stop it (docs/erklaerungen/15-sperren.md).
     */
    public function testOutdatedRequestCreatesNoSecondInvoice(): void
    {
        self::bootKernel();
        $this->completeSettings();
        $issuer = self::getContainer()->get(InvoiceIssuer::class);
        $enrollment = $this->enrollment();
        $draft = new InvoiceDraft('Gesangsunterricht', new \DateTimeImmutable('2026-11-07'));
        $draft->dueOn = new \DateTimeImmutable('2026-11-21');
        $issuer->issue($enrollment, $draft);

        // The state of the second request: the loaded collection does not know the first invoice
        $enrollment->getInvoices()->clear();
        self::assertSame([], $issuer->problems($enrollment));

        try {
            $issuer->issue($enrollment, $draft);
            self::fail('A second invoice was created.');
        } catch (InvoiceConflictException) {
        }
        self::assertSame(1, $this->countInvoices());
    }

    /**
     * Double click on "create credit note": the second must not credit more than the amount.
     */
    public function testOutdatedRequestCannotCreditMoreThanTheAmount(): void
    {
        self::bootKernel();
        $issuer = self::getContainer()->get(InvoiceIssuer::class);
        $invoice = InvoiceFactory::createOne(['amountCents' => 10000]);
        $draft = new CreditDraft('Gutschrift', new \DateTimeImmutable('2026-11-07'), 10000);
        $draft->amountCents = 10000;
        $issuer->credit($invoice, $draft);

        // The state of the second request: the loaded collection does not know the first credit note
        $invoice->getCreditNotes()->clear();
        self::assertSame(10000, $invoice->getCreditableCents());

        try {
            $issuer->credit($invoice, $draft);
            self::fail('The amount was credited twice.');
        } catch (InvoiceConflictException) {
        }
        self::assertSame(2, $this->countInvoices());
    }

    public function testRecipientIsACopy(): void
    {
        self::bootKernel();
        $this->completeSettings();
        $guardian = GuardianFactory::createOne(['name' => 'Anna und Peter Beispiel', 'address' => new Address('Altgasse 1', '8010', 'Graz')]);
        $student = StudentFactory::new()->minor()->create(['guardian' => $guardian]);
        $enrollment = EnrollmentFactory::createOne(['student' => $student, 'billToGuardian' => true]);

        $draft = new InvoiceDraft('Gesangsunterricht', new \DateTimeImmutable('2026-11-07'));
        $draft->dueOn = new \DateTimeImmutable('2026-11-13');
        $id = self::getContainer()->get(InvoiceIssuer::class)->issue($enrollment, $draft)->getId();

        // The guardian moves; the invoice keeps the old address
        $guardian->getAddress()->setStreet('Neugasse 2');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
        $entityManager->clear();

        $invoice = self::getContainer()->get(InvoiceRepository::class)->find($id);
        self::assertInstanceOf(Invoice::class, $invoice);
        self::assertSame('Anna und Peter Beispiel', $invoice->getRecipient()->getName());
        self::assertSame('Altgasse 1', $invoice->getRecipient()->getAddress()->getStreet());
    }

    public function testMissingDataPreventsInvoice(): void
    {
        $client = $this->loggedInClient();
        // No settings saved, guardian wanted but missing
        $enrollment = EnrollmentFactory::createOne(['student' => StudentFactory::new()->create(['guardian' => null]), 'billToGuardian' => true]);

        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId().'/honorarnote');
        self::assertSelectorTextContains('.alert-warning', 'In den Einstellungen fehlen');
        self::assertSelectorTextContains('.alert-warning', 'keiner eingetragen');
        self::assertSelectorNotExists('form[name="invoice"]');

        // Posting anyway creates nothing
        $client->request('POST', '/admin/teilnahmen/'.$enrollment->getId().'/honorarnote', ['invoice' => ['issuedOn' => '2026-11-07', 'dueOn' => '2026-11-13', 'description' => 'x']]);
        self::assertSame(0, InvoiceFactory::count());
    }

    public function testSecondInstalmentMustBeLater(): void
    {
        $client = $this->loggedInClient();
        $this->completeSettings();
        $enrollment = $this->enrollment();

        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId().'/honorarnote');
        $client->submitForm('Honorarnote erstellen', [
            'invoice[issuedOn]' => '2026-11-07',
            'invoice[dueOn]' => '2026-11-13',
            'invoice[secondInstallmentDueOn]' => '2026-11-01',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, InvoiceFactory::count());
    }

    public function testEnrollmentWithInvoiceCannotBeDeleted(): void
    {
        $client = $this->loggedInClient();
        $invoice = InvoiceFactory::createOne();
        $enrollment = $invoice->getEnrollment();

        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId());
        self::assertSelectorTextContains('main', 'Honorarnote '.$invoice->getDisplayNumber());
        self::assertSelectorNotExists('form[action$="/loeschen"]');

        $client->request('POST', '/admin/teilnahmen/'.$enrollment->getId().'/loeschen');
        self::assertResponseStatusCodeSame(403);
    }

    public function testListPerYear(): void
    {
        $client = $this->loggedInClient();
        InvoiceFactory::createOne(['year' => 2026, 'number' => 16, 'description' => 'Wintersemester']);
        InvoiceFactory::createOne(['year' => 2027, 'number' => 1, 'description' => 'Sommersemester']);

        $client->request('GET', '/admin/honorarnoten');
        self::assertSelectorTextContains('h1', 'Honorarnoten 2027');
        self::assertSelectorTextContains('table', '1/2027');
        self::assertSelectorTextNotContains('table', '16/2026');

        $client->request('GET', '/admin/honorarnoten?jahr=2026');
        self::assertSelectorTextContains('table', '16/2026');
    }

    /**
     * Counted in the database: after a failed transaction Doctrine's EntityManager is closed.
     */
    private function countInvoices(): int
    {
        $count = self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice');

        return is_numeric($count) ? (int) $count : -1;
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        return $client;
    }

    private function completeSettings(): void
    {
        persist(Settings::class, ['senderName' => 'Erika Musterfrau', 'senderAddress' => new Address('Musterstraße 1', '8010', 'Graz'), 'iban' => 'AT611904300234573201']);
    }

    /**
     * Adult student with a complete address, billed directly, 495.01 €.
     */
    private function enrollment(): Enrollment
    {
        $student = StudentFactory::createOne(['guardian' => null, 'address' => new Address('Musterweg 7', '8020', 'Graz')]);

        return EnrollmentFactory::createOne(['student' => $student, 'billToGuardian' => false, 'priceCents' => 49501]);
    }
}
