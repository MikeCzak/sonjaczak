<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Embeddable\Address;
use App\Entity\Enrollment;
use App\Entity\Invoice;
use App\Entity\Settings;
use App\Invoice\CreditDraft;
use App\Invoice\InvoiceDraft;
use App\Invoice\InvoiceIssuer;
use App\Invoice\InvoicePdfException;
use App\Invoice\InvoicePdfRenderer;
use App\Repository\InvoicePdfRepository;
use App\Repository\InvoiceRepository;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;

/**
 * PDF of the invoice (step D2b): rendered and stored together with the invoice, or not at all.
 */
final class InvoicePdfTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testIssuingStoresThePdf(): void
    {
        self::bootKernel();
        $this->completeSettings();
        $invoice = $this->issue($this->enrollment(49500), '2026-12-31');
        $id = $invoice->getId();

        // Loaded fresh from the database: the bytea column comes back as a stream
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $invoice = self::getContainer()->get(InvoiceRepository::class)->find($id);
        self::assertInstanceOf(Invoice::class, $invoice);
        $pdf = self::getContainer()->get(InvoicePdfRepository::class)->findForInvoice($invoice);

        self::assertNotNull($pdf);
        self::assertStringStartsWith('%PDF-', $pdf->getContent());
        // Read twice: the stream must not be empty the second time
        self::assertSame($pdf->getContent(), $pdf->getContent());
    }

    /**
     * The text as in the model HN16: whole euros as "495,-", instalments with cents, IBAN in groups of four.
     */
    public function testInvoiceTextLikeTheModel(): void
    {
        self::bootKernel();
        $this->completeSettings();
        $invoice = $this->issue($this->enrollment(49500), '2026-12-31');

        $html = $this->text($invoice);

        self::assertStringContainsString('Erika Musterfrau Bakk.phil.', $html);
        self::assertStringContainsString('Graz, 07.11.2026', $html);
        self::assertStringContainsString('Honorarnote 1/2026', $html);
        self::assertStringContainsString('Für den Gesangsunterricht im Wintersemester 2026/27', $html);
        self::assertStringContainsString('€ 495,-', $html);
        self::assertStringContainsString('Zahlbar gesamt bis zum 13.11.2026 ODER', $html);
        self::assertStringContainsString('1. Rate à 247,50 bis zum 13.11.2026', $html);
        self::assertStringContainsString('2. Rate à 247,50 bis zum 31.12.2026', $html);
        self::assertStringContainsString('IBAN: AT61 1904 3002 3457 3201', $html);
        self::assertStringContainsString('Umsatzsteuerbefreit gem. §6 (1) Z 27 UStG', $html);
    }

    public function testInvoiceWithoutInstalments(): void
    {
        self::bootKernel();
        $this->completeSettings();
        $invoice = $this->issue($this->enrollment(49501), null);

        $html = $this->text($invoice);

        self::assertStringContainsString('€ 495,01', $html);
        self::assertStringContainsString('Zahlbar bis zum 13.11.2026', $html);
        self::assertStringNotContainsString('Rate', $html);
    }

    public function testCreditNoteText(): void
    {
        self::bootKernel();
        $this->completeSettings();
        $invoice = $this->issue($this->enrollment(49500), null);
        $draft = new CreditDraft('Ermäßigung', new \DateTimeImmutable('2026-11-20'), $invoice->getCreditableCents());
        $draft->amountCents = 5000;
        $note = self::getContainer()->get(InvoiceIssuer::class)->credit($invoice, $draft);

        $html = $this->text($note);

        self::assertStringContainsString('Gutschrift 2/2026', $html);
        self::assertStringContainsString('zu Honorarnote 1/2026', $html);
        self::assertStringContainsString('€ 50,-', $html);
        self::assertStringNotContainsString('IBAN', $html);
        self::assertStringNotContainsString('Zahlbar', $html);
        self::assertNotNull(self::getContainer()->get(InvoicePdfRepository::class)->findForInvoice($note));
    }

    /**
     * Option A (umsetzung-admin.md D2b): if the PDF fails, nothing is saved and the number is not used up. Sonja sees
     * a message on the form and can simply submit again.
     */
    public function testFailedPdfSavesNothing(): void
    {
        $client = self::createClient();
        // Keep the kernel between requests, otherwise the replaced service would be gone after the first one
        $client->disableReboot();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');
        $this->completeSettings();
        $enrollment = $this->enrollment(49500);

        $failing = $this->createStub(InvoicePdfRenderer::class);
        $failing->method('render')->willThrowException(new InvoicePdfException('Test'));
        self::getContainer()->set(InvoicePdfRenderer::class, $failing);

        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId().'/honorarnote');
        $client->submitForm('Honorarnote erstellen', ['invoice[issuedOn]' => '2026-11-07', 'invoice[dueOn]' => '2026-11-13']);

        self::assertResponseRedirects('/admin/teilnahmen/'.$enrollment->getId().'/honorarnote');
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertEquals(0, $connection->fetchOne('SELECT COUNT(*) FROM invoice'));
        self::assertEquals(0, $connection->fetchOne('SELECT COUNT(*) FROM invoice_pdf'));

        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'keine Nummer verbraucht');
    }

    private function issue(Enrollment $enrollment, ?string $secondInstallmentDueOn): Invoice
    {
        $draft = new InvoiceDraft('Gesangsunterricht im Wintersemester 2026/27', new \DateTimeImmutable('2026-11-07'));
        $draft->dueOn = new \DateTimeImmutable('2026-11-13');
        $draft->secondInstallmentDueOn = null === $secondInstallmentDueOn ? null : new \DateTimeImmutable($secondInstallmentDueOn);

        return self::getContainer()->get(InvoiceIssuer::class)->issue($enrollment, $draft);
    }

    /**
     * The HTML of the PDF without tags and with single spaces, as a reader sees it.
     */
    private function text(Invoice $invoice): string
    {
        $html = self::getContainer()->get(InvoicePdfRenderer::class)->renderHtml($invoice);
        $text = html_entity_decode(strip_tags(preg_replace('/<style.*<\/style>/s', '', $html) ?? ''));

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function completeSettings(): void
    {
        persist(Settings::class, [
            'senderName' => 'Erika Musterfrau',
            'senderTitle' => 'Bakk.phil.',
            'senderAddress' => new Address('Musterstraße 1', '8010', 'Graz'),
            'iban' => 'AT611904300234573201',
            'vatNote' => 'Umsatzsteuerbefreit gem. §6 (1) Z 27 UStG',
        ]);
    }

    private function enrollment(int $priceCents): Enrollment
    {
        $student = StudentFactory::createOne(['guardian' => null, 'address' => new Address('Musterweg 7', '8020', 'Graz')]);

        return EnrollmentFactory::createOne(['student' => $student, 'billToGuardian' => false, 'priceCents' => $priceCents]);
    }
}
