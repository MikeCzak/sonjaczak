<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Embeddable\Address;
use App\Entity\Enum\PaymentMethod;
use App\Entity\Payment;
use App\Entity\Settings;
use App\Invoice\InvoiceDraft;
use App\Invoice\InvoiceIssuer;
use App\Privacy\PersonalDataPurger;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;

/**
 * Pages with an enrollment whose student was deleted and whose invoice is kept (umsetzung-admin.md F3b): nothing
 * breaks, nothing is missing from lists and exports, nothing can be edited.
 */
final class PersonDeletedTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    public function testPagesWithoutPerson(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-09-15 10:00'));
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        persist(Settings::class, ['senderName' => 'Erika Musterfrau', 'senderAddress' => new Address('Musterstraße 1', '8010', 'Graz'), 'iban' => 'AT611904300234573201']);
        $course = CourseFactory::createOne();
        $student = StudentFactory::createOne(['guardian' => null, 'firstName' => 'Lena', 'lastName' => 'Springer']);
        $enrollment = EnrollmentFactory::createOne(['student' => $student, 'course' => $course, 'billToGuardian' => false, 'priceCents' => 49500]);
        $draft = new InvoiceDraft('Gesangsunterricht', new \DateTimeImmutable('2026-11-07'));
        $draft->dueOn = new \DateTimeImmutable('2026-11-13');
        $invoice = self::getContainer()->get(InvoiceIssuer::class)->issue($enrollment, $draft);
        persist(Payment::class, ['invoice' => $invoice, 'amountCents' => 49500, 'paidOn' => new \DateTimeImmutable('2026-11-20'), 'method' => PaymentMethod::Transfer]);
        $courseId = $course->getId();
        $enrollmentId = $enrollment->getId();
        $invoiceId = $invoice->getId();

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $result = self::getContainer()->get(PersonalDataPurger::class)->purge(new \DateTimeImmutable('2027-09-01'));
        self::assertSame(1, $result->detachedEnrollments);

        $client->request('GET', '/admin/kurse/'.$courseId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('a[href="/admin/teilnahmen/'.$enrollmentId.'"]', 'Person gelöscht');

        $client->request('GET', '/admin/teilnahmen/'.$enrollmentId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#enrollment-person-deleted', 'bis 31.12.2033');
        self::assertSelectorExists('a[href="/admin/honorarnoten/'.$invoiceId.'"]');
        self::assertSelectorNotExists('main form');
        $client->request('POST', '/admin/teilnahmen/'.$enrollmentId.'/anzahl');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/admin/honorarnoten/'.$invoiceId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Person gelöscht');
        // The recipient is part of the receipt and stays
        self::assertSelectorTextContains('main', 'Lena Springer');

        $client->request('GET', '/admin/honorarnoten?jahr=2026');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/honorarnoten/'.$invoiceId.'"]');

        $client->request('GET', '/admin/einnahmen/2026.csv');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Lena Springer', (string) $client->getInternalResponse()->getContent());

        $client->request('GET', '/admin/kurse/'.$courseId.'/honorarnoten.zip');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('HN1_GU_WiSe_2026_Springer.pdf', (string) $client->getInternalResponse()->getContent());

        // An enrollment without a person must not hide everybody else (NOT IN with NULL)
        self::mockTime(new \DateTimeImmutable('2027-09-01 10:00'));
        StudentFactory::createOne(['lastName' => 'Neumann']);
        $client->request('GET', '/admin/kurse/'.$courseId.'/teilnahmen/neu');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Neumann');
    }
}
