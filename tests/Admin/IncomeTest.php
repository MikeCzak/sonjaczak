<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Embeddable\Address;
use App\Entity\Embeddable\Recipient;
use App\Entity\Enum\PaymentMethod;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;

/**
 * Income export (step D5).
 */
final class IncomeTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testIncomeCountsInTheYearOfPayment(): void
    {
        $client = $this->loggedInClient();
        $invoice = InvoiceFactory::createOne([
            'year' => 2026,
            'number' => 16,
            'amountCents' => 49500,
            'description' => 'Gesangsunterricht im Wintersemester 2026/27',
            'recipient' => new Recipient('Anna Beispiel', new Address('Altgasse 1', '8010', 'Graz')),
        ]);
        $this->pay($invoice, 24750, '2026-12-30', PaymentMethod::Transfer);
        $this->pay($invoice, 24750, '2027-01-04', PaymentMethod::Cash);

        $client->request('GET', '/admin/einnahmen?jahr=2026');
        self::assertSelectorTextContains('tfoot', '247,50 €');

        $client->request('GET', '/admin/einnahmen/2026.csv');
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=Einnahmen_2026.csv');
        $csv = (string) $client->getInternalResponse()->getContent();

        self::assertStringStartsWith("\u{FEFF}Datum;Honorarnote;", $csv);
        self::assertSame(
            "30.12.2026;16/2026;\"Anna Beispiel\";",
            substr(explode("\r\n", $csv)[1], 0, \strlen("30.12.2026;16/2026;\"Anna Beispiel\";")),
        );
        self::assertStringEndsWith(";Überweisung;247,50\r\n", $csv);
        self::assertSame(3, substr_count($csv, "\r\n") + 1, 'Header, one payment, empty last line');
    }

    public function testFormulasAreNeutralised(): void
    {
        $client = $this->loggedInClient();
        $invoice = InvoiceFactory::createOne(['description' => '=HYPERLINK("http://example.com")', 'amountCents' => 1000]);
        $this->pay($invoice, 1000, '2026-11-20', PaymentMethod::Transfer);

        $client->request('GET', '/admin/einnahmen/2026.csv');
        self::assertStringContainsString('"\'=HYPERLINK(""http://example.com"")"', (string) $client->getInternalResponse()->getContent());
    }

    private function pay(Invoice $invoice, int $cents, string $day, PaymentMethod $method): void
    {
        persist(Payment::class, ['invoice' => $invoice, 'amountCents' => $cents, 'paidOn' => new \DateTimeImmutable($day), 'method' => $method]);
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        return $client;
    }
}
