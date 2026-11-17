<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Enum\CancelledBy;
use App\Entity\Enum\PaymentMethod;
use App\Entity\Payment;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\TrialLessonFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;

/**
 * Start page of the admin area (step E), on a fixed day: Friday, 13 November 2026, 10:00.
 */
final class DashboardTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    public function testOverview(): void
    {
        $client = self::createClient();
        self::mockTime(new \DateTimeImmutable('2026-11-13 10:00'));
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        // Friday 15:30, course from 5 October: lessons on 9.10., 16.10., … 13.11., 20.11.
        $student = StudentFactory::createOne(['firstName' => 'Lena', 'lastName' => 'Muster']);
        $enrollment = EnrollmentFactory::createOne(['student' => $student]);
        $lessons = $enrollment->getLessons()->getValues();
        // 30.10. is over at the mocked time, so it counts as held without a flag
        $lessons[3]->setNote('Skyfall');
        $lessons[4]->setCancelledBy(CancelledBy::Teacher);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        TrialLessonFactory::createOne(['student' => StudentFactory::new()->create(['firstName' => 'Tom', 'lastName' => 'Probe']), 'startsAt' => new \DateTimeImmutable('2026-11-16 17:00')]);
        // Outside the 7 days: not shown
        TrialLessonFactory::createOne(['student' => StudentFactory::new()->create(['firstName' => 'Zoe', 'lastName' => 'Später']), 'startsAt' => new \DateTimeImmutable('2026-11-30 17:00')]);

        $invoice = InvoiceFactory::createOne(['enrollment' => $enrollment, 'year' => 2026, 'number' => 16, 'amountCents' => 49500, 'dueOn' => new \DateTimeImmutable('2026-11-06')]);
        persist(Payment::class, ['invoice' => $invoice, 'amountCents' => 10000, 'paidOn' => new \DateTimeImmutable('2026-11-10'), 'method' => PaymentMethod::Cash]);

        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        $upcoming = $client->getCrawler()->filter('#dashboard-upcoming + ul li')->each(static fn ($li): string => preg_replace('/\s+/', ' ', trim($li->text())) ?? '');
        self::assertSame(['Fr 13.11. 15:30 Lena Muster', 'Mo 16.11. 17:00 Tom Probe Schnupperstunde', 'Fr 20.11. 15:30 Lena Muster'], $upcoming);

        self::assertSelectorTextContains('main', '16/2026');
        self::assertSelectorTextContains('main', '395,00 €');
        self::assertSelectorTextContains('main', 'überfällig seit 06.11.2026');
        self::assertSelectorTextContains('main', '30.10. Skyfall');
        self::assertSelectorTextContains('main', '1 Nachholstunde fällig');
        self::assertSelectorTextContains('main', 'Einnahmen 2026 je Kurs');
        self::assertSelectorTextContains('tfoot', '100,00 €');
        self::assertSelectorTextNotContains('main', 'noch keine Honorarnote');
    }

    public function testEmptyDashboard(): void
    {
        $client = self::createClient();
        self::mockTime(new \DateTimeImmutable('2026-11-13 10:00'));
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Keine Einheiten geplant.');
        self::assertSelectorTextContains('main', 'Gerade läuft kein Kurs.');
    }

    /**
     * umsetzung-admin.md F3a: who will be deleted within 30 days, so Sonja can add an enrollment if someone returns.
     */
    public function testUpcomingDeletion(): void
    {
        $client = self::createClient();
        // Last activity in the summer semester 2026 → kept until 28.02.2027
        self::mockTime(new \DateTimeImmutable('2026-06-10 10:00'));
        StudentFactory::createOne(['firstName' => 'Anna', 'lastName' => 'Alt']);
        // Winter semester → kept until 31.08.2027
        self::mockTime(new \DateTimeImmutable('2026-10-10 10:00'));
        StudentFactory::createOne(['firstName' => 'Nina', 'lastName' => 'Neu']);

        self::mockTime(new \DateTimeImmutable('2027-02-10 10:00'));
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');
        $client->request('GET', '/admin');

        self::assertSelectorTextContains('#dashboard-deletion', 'Löschung in den nächsten 30 Tagen');
        $items = $client->getCrawler()->filter('#dashboard-deletion + ul li')->each(static fn ($li): string => preg_replace('/\s+/', ' ', trim($li->text())) ?? '');
        self::assertSame(['Anna Alt gespeichert bis 28.02.2027'], $items);
    }

}
