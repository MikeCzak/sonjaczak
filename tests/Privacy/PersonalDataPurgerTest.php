<?php

declare(strict_types=1);

namespace App\Tests\Privacy;

use App\Entity\Course;
use App\Entity\Embeddable\Address;
use App\Entity\Enrollment;
use App\Entity\Enum\PaymentMethod;
use App\Entity\Guardian;
use App\Entity\Invoice;
use App\Entity\InvoicePdf;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\Settings;
use App\Entity\Student;
use App\Entity\TrialLesson;
use App\Invoice\CreditDraft;
use App\Invoice\InvoiceDraft;
use App\Invoice\InvoiceIssuer;
use App\Lesson\LessonPlanner;
use App\Privacy\PersonalDataPurger;
use App\Privacy\PurgeResult;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\GuardianFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\TrialLessonFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;

/**
 * Automatic deletion (umsetzung-admin.md F3b). The course runs in the winter semester 2026/27 (last lesson in
 * February 2027), so personal data is kept until 31.08.2027 and invoices from 2026 until 31.12.2033.
 */
final class PersonalDataPurgerTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-09-15 10:00'));
    }

    public function testDeletesStudentWithoutInvoicesAndTheirOnlyGuardian(): void
    {
        $guardian = GuardianFactory::createOne();
        $student = StudentFactory::createOne(['guardian' => $guardian]);
        EnrollmentFactory::createOne(['student' => $student, 'course' => $course = CourseFactory::createOne()]);
        TrialLessonFactory::createOne(['student' => $student, 'startsAt' => new \DateTimeImmutable('2026-09-20 16:00')]);

        // The last day is kept
        self::assertTrue($this->purge('2027-08-31')->isEmpty());
        self::assertSame(1, $this->rows(Student::class));

        $result = $this->purge('2027-09-01');
        self::assertSame(1, $result->students);
        self::assertSame(1, $result->guardians);
        self::assertSame(0, $result->detachedEnrollments);
        foreach ([Student::class, Guardian::class, Enrollment::class, Lesson::class, TrialLesson::class] as $class) {
            self::assertSame(0, $this->rows($class), $class);
        }
        // Courses have no personal data and stay
        self::assertNotNull($this->entityManager()->find(Course::class, $course->getId()));
    }

    public function testGuardianWithAnotherStudentStays(): void
    {
        $guardian = GuardianFactory::createOne();
        $leaving = StudentFactory::createOne(['guardian' => $guardian]);
        EnrollmentFactory::createOne(['student' => $leaving, 'course' => CourseFactory::createOne()]);
        // The sibling comes back for the next course
        self::mockTime(new \DateTimeImmutable('2027-09-10 10:00'));
        StudentFactory::createOne(['guardian' => $guardian]);

        $result = $this->purge('2027-09-15');
        self::assertSame(1, $result->students);
        self::assertSame(0, $result->guardians);
        self::assertSame(1, $this->rows(Student::class));
        self::assertSame(1, $this->rows(Guardian::class));
    }

    /**
     * A student with an invoice: the person goes, the invoice stays seven years with PDF, credit note and payments,
     * linked to an enrollment without a person. Then everything goes.
     */
    public function testInvoicesOutliveThePerson(): void
    {
        $guardian = GuardianFactory::createOne();
        $student = StudentFactory::createOne(['guardian' => $guardian, 'lastName' => 'Springer']);
        $enrollment = EnrollmentFactory::createOne(['student' => $student, 'course' => CourseFactory::createOne(), 'billToGuardian' => false, 'priceCents' => 49500]);
        $invoice = $this->issueInvoice($enrollment);
        $this->credit($invoice, 6600, '2027-01-10');
        persist(Payment::class, ['invoice' => $invoice, 'amountCents' => 42900, 'paidOn' => new \DateTimeImmutable('2026-11-20'), 'method' => PaymentMethod::Transfer]);
        $fileName = $invoice->getFileName();
        $recipient = $invoice->getRecipient()->getName();

        $result = $this->purge('2027-09-01');
        self::assertSame(1, $result->students);
        self::assertSame(1, $result->detachedEnrollments);
        self::assertSame(1, $result->guardians);
        self::assertSame(0, $result->invoices);
        self::assertSame(0, $this->rows(Student::class));
        self::assertSame(0, $this->rows(Guardian::class));
        self::assertSame(0, $this->rows(Lesson::class));

        $kept = $this->entityManager()->find(Enrollment::class, $enrollment->getId());
        self::assertNotNull($kept);
        self::assertTrue($kept->isPersonDeleted());
        self::assertFalse($kept->isBillToGuardian());
        self::assertCount(2, $kept->getInvoices());
        self::assertSame(1, $this->rows(Payment::class));
        $pdf = $this->entityManager()->getRepository(InvoicePdf::class)->findOneBy(['invoice' => $invoice->getId()]);
        self::assertSame($fileName, $pdf?->getFileName());
        self::assertSame($recipient, $this->entityManager()->find(Invoice::class, $invoice->getId())?->getRecipient()->getName());

        // No lessons come back for an enrollment without a person (e.g. when the breaks of the old course change)
        self::assertSame(0, new LessonPlanner()->fillUp($kept));

        // The credit note from 2027 keeps both until the end of 2034
        self::assertTrue($this->purge('2034-12-31')->isEmpty());

        $result = $this->purge('2035-01-01');
        self::assertSame(2, $result->invoices);
        self::assertSame(0, $result->students);
        foreach ([Enrollment::class, Invoice::class, InvoicePdf::class, Payment::class] as $class) {
            self::assertSame(0, $this->rows($class), $class);
        }
    }

    /**
     * A student still taking lessons keeps everything, even an enrollment from the year before.
     */
    public function testActiveStudentKeepsEverything(): void
    {
        $student = StudentFactory::createOne();
        EnrollmentFactory::createOne(['student' => $student, 'course' => CourseFactory::createOne()]);
        TrialLessonFactory::createOne(['student' => $student, 'startsAt' => new \DateTimeImmutable('2027-10-01 16:00')]);

        self::assertTrue($this->purge('2028-08-31')->isEmpty());
        self::assertSame(1, $this->purge('2028-09-01')->students);
    }

    public function testCommandDryRunDeletesNothing(): void
    {
        EnrollmentFactory::createOne(['course' => CourseFactory::createOne()]);
        self::mockTime(new \DateTimeImmutable('2027-09-01 03:00'));

        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:privacy:purge'));
        $tester->execute(['--dry-run' => true]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('would delete: 1 student(s), 0 guardian(s)', $tester->getDisplay());
        self::assertSame(1, $this->rows(Student::class));

        $tester->execute([]);
        self::assertStringContainsString('Deleted: 1 student(s)', $tester->getDisplay());
        self::assertSame(0, $this->rows(Student::class));
    }

    private function purge(string $today): PurgeResult
    {
        // Like a cron run in a new process: nothing from the setup left in memory
        $this->entityManager()->clear();

        return self::getContainer()->get(PersonalDataPurger::class)->purge(new \DateTimeImmutable($today));
    }

    /**
     * @param class-string $class
     */
    private function rows(string $class): int
    {
        return $this->entityManager()->getRepository($class)->count();
    }

    private function issueInvoice(Enrollment $enrollment): Invoice
    {
        persist(Settings::class, ['senderName' => 'Erika Musterfrau', 'senderAddress' => new Address('Musterstraße 1', '8010', 'Graz'), 'iban' => 'AT611904300234573201']);
        $draft = new InvoiceDraft('Gesangsunterricht', new \DateTimeImmutable('2026-11-07'));
        $draft->dueOn = new \DateTimeImmutable('2026-11-13');

        return self::getContainer()->get(InvoiceIssuer::class)->issue($enrollment, $draft);
    }

    private function credit(Invoice $invoice, int $cents, string $issuedOn): void
    {
        $draft = new CreditDraft('Gutschrift', new \DateTimeImmutable($issuedOn), $invoice->getCreditableCents());
        $draft->amountCents = $cents;
        self::getContainer()->get(InvoiceIssuer::class)->credit($invoice, $draft);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
