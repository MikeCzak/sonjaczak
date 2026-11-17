<?php

declare(strict_types=1);

namespace App\Tests\Privacy;

use App\Entity\Course;
use App\Entity\Embeddable\Address;
use App\Entity\Enrollment;
use App\Entity\Settings;
use App\Entity\Student;
use App\Invoice\InvoiceDraft;
use App\Invoice\InvoiceIssuer;
use App\Privacy\RetentionPolicy;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\TrialLessonFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\persist;

/**
 * Retention periods (umsetzung-admin.md F3): fixed semesters, seven years for invoices.
 */
final class RetentionPolicyTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function semesters(): iterable
    {
        yield 'winter, autumn' => ['2026-10-02 15:30', '2027-08-31'];
        yield 'winter, new year' => ['2027-02-26 15:30', '2027-08-31'];
        yield 'first day of summer' => ['2027-03-01 08:00', '2028-02-29'];
        yield 'summer, no leap year' => ['2027-06-25 15:30', '2028-02-29'];
        yield 'summer, next year no leap year' => ['2028-07-01 10:00', '2029-02-28'];
        yield 'last day of summer' => ['2027-08-31 23:00', '2028-02-29'];
        yield 'first day of winter' => ['2027-09-01 00:00', '2028-08-31'];
    }

    #[DataProvider('semesters')]
    public function testEndOfFollowingSemester(string $activity, string $until): void
    {
        self::assertSame($until, RetentionPolicy::endOfFollowingSemester(new \DateTimeImmutable($activity))->format('Y-m-d'));
    }

    /**
     * Planned lessons count too: the deadline follows the last lesson of the course, not today.
     */
    public function testLastLessonOfTheCourseCounts(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-09-15 10:00'));
        $student = StudentFactory::createOne();
        $enrollment = $this->enrollment($student, CourseFactory::createOne());
        $starts = $enrollment->getLessons()->map(static fn ($lesson) => $lesson->getStartsAt())->toArray();
        self::assertCount(15, $starts);

        $policy = new RetentionPolicy();
        self::assertEquals(max($starts), $policy->lastActivity($student));
        self::assertGreaterThan(new \DateTimeImmutable('2026-12-31'), $policy->lastActivity($student));
        self::assertSame('2027-08-31', $policy->personalDataUntil($student)->format('Y-m-d'));
        self::assertNull($policy->invoicesUntil($student));
    }

    /**
     * Without lessons or trial lessons the creation counts; a later trial lesson moves the deadline.
     */
    public function testCreationAndTrialLesson(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-04-10 10:00'));
        $student = StudentFactory::createOne();
        $policy = new RetentionPolicy();
        self::assertSame('2027-02-28', $policy->personalDataUntil($student)->format('Y-m-d'));

        TrialLessonFactory::createOne(['student' => $student, 'startsAt' => new \DateTimeImmutable('2026-09-20 16:00')]);
        self::assertSame('2027-08-31', $policy->personalDataUntil($student)->format('Y-m-d'));
    }

    /**
     * § 132 BAO: seven years from the end of the calendar year of the latest invoice.
     */
    public function testInvoicesSevenYearsFromTheEndOfTheYear(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-09-15 10:00'));
        persist(Settings::class, ['senderName' => 'Erika Musterfrau', 'senderAddress' => new Address('Musterstraße 1', '8010', 'Graz'), 'iban' => 'AT611904300234573201']);
        $student = StudentFactory::createOne(['guardian' => null]);
        $enrollment = $this->enrollment($student, CourseFactory::createOne());
        $draft = new InvoiceDraft('Gesangsunterricht', new \DateTimeImmutable('2026-11-07'));
        $draft->dueOn = new \DateTimeImmutable('2026-11-13');
        self::getContainer()->get(InvoiceIssuer::class)->issue($enrollment, $draft);

        self::assertSame('2033-12-31', new RetentionPolicy()->invoicesUntil($student)?->format('Y-m-d'));
    }

    private function enrollment(Student $student, Course $course): Enrollment
    {
        return EnrollmentFactory::createOne(['student' => $student, 'course' => $course, 'billToGuardian' => false, 'priceCents' => 49500]);
    }
}
