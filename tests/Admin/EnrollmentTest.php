<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Enrollment;
use App\Entity\Enum\LessonStatus;
use App\Entity\Lesson;
use App\Repository\EnrollmentRepository;
use App\Repository\LessonRepository;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Enrollments and lessons (step C3).
 */
final class EnrollmentTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testEnrollMinorGeneratesLessonsAndBillsTheGuardian(): void
    {
        $client = $this->loggedInClient();
        $course = CourseFactory::new()->withBreak()->create();
        $student = StudentFactory::new()->minor()->create(['firstName' => 'Lena', 'lastName' => 'Muster']);
        $enrolled = EnrollmentFactory::createOne(['course' => $course])->getStudent();

        $client->request('GET', '/admin/kurse/'.$course->getId().'/teilnahmen/neu');
        // Who is enrolled already is not offered
        self::assertSelectorNotExists(\sprintf('option[value="%d"]', $enrolled?->getId()));
        // submitForm() sends POST unless told otherwise, even for a form with method="get"
        $client->submitForm('Weiter', ['student' => (string) $student->getId()], 'GET');
        self::assertSelectorTextContains('h1', 'Lena Muster');

        self::assertCheckboxChecked('enrollment[billToGuardian]');
        $client->submitForm('Teilnahme anlegen', [
            'enrollment[weekday]' => '5',
            'enrollment[startTime]' => '13:40',
            'enrollment[priceCents]' => '450,00',
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('main', '15 von 15');

        $enrollment = self::getContainer()->get(EnrollmentRepository::class)->findOneBy(['student' => $student->getId()]);
        self::assertInstanceOf(Enrollment::class, $enrollment);
        self::assertSame(45000, $enrollment->getPriceCents());
        self::assertTrue($enrollment->isBillToGuardian());
        self::assertCount(15, $enrollment->getLessons());
        $first = $enrollment->getLessons()->first();
        self::assertInstanceOf(Lesson::class, $first);
        self::assertSame('2026-10-09 13:40', $first->getStartsAt()?->format('Y-m-d H:i'));
    }

    public function testAdultIsBilledThemselves(): void
    {
        $client = $this->loggedInClient();
        $course = CourseFactory::createOne();
        $student = StudentFactory::createOne();

        $client->request('GET', \sprintf('/admin/kurse/%d/teilnahmen/neu?student=%d', $course->getId(), $student->getId()));
        // The checkbox must exist: assertCheckboxNotChecked() also passes without it
        self::assertSelectorExists('input[name="enrollment[billToGuardian]"]');
        self::assertCheckboxNotChecked('enrollment[billToGuardian]');
    }

    public function testCancellationBySonjaLeadsToMakeUpLesson(): void
    {
        $client = $this->loggedInClient();
        $enrollment = EnrollmentFactory::createOne();
        $first = $enrollment->getLessons()->first();
        self::assertInstanceOf(Lesson::class, $first);

        $client->request('GET', '/admin/einheiten/'.$first->getId());
        $client->submitForm('Speichern', [
            'lesson[status]' => LessonStatus::CancelledByTeacher->value,
            'lesson[note]' => 'krank',
        ]);
        self::assertResponseRedirects('/admin/teilnahmen/'.$enrollment->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('main', '14 von 15');
        self::assertSelectorTextContains('.badge', '1 Nachholstunde fällig');

        $client->clickLink('Nachholstunde eintragen');
        $client->submitForm('Speichern', ['make_up_lesson[startsAt]' => '2026-10-13T15:30']);
        self::assertResponseRedirects('/admin/teilnahmen/'.$enrollment->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('main', '15 von 15');
        self::assertSelectorTextContains('main', 'Nachholstunde für 09.10.2026');
        self::assertSelectorNotExists('a:contains("Nachholstunde eintragen")');
    }

    public function testNewBreakRemovesPlannedLessons(): void
    {
        $client = $this->loggedInClient();
        $enrollment = EnrollmentFactory::createOne();
        $course = $enrollment->getCourse();

        $client->request('GET', '/admin/kurse/'.$course->getId());
        $client->submitForm('Eintragen', [
            'course_break[label]' => 'Schulautonom',
            'course_break[firstDay]' => '2026-10-16',
        ]);
        self::assertResponseRedirects();

        $days = array_map(
            static fn (Lesson $lesson): string => (string) $lesson->getStartsAt()?->format('Y-m-d'),
            self::getContainer()->get(LessonRepository::class)->findBy(['enrollment' => $enrollment->getId()]),
        );
        self::assertCount(15, $days);
        self::assertNotContains('2026-10-16', $days);
        self::assertContains('2027-01-22', $days);
    }

    public function testCourseWithEnrollmentsCannotBeDeletedButEnrollmentCan(): void
    {
        $client = $this->loggedInClient();
        $enrollment = EnrollmentFactory::createOne();
        $courseId = $enrollment->getCourse()->getId();
        $lessons = $enrollment->getLessons()->toArray();
        // A make-up lesson points to a cancelled one: deleting both together must work (ON DELETE SET NULL)
        $lessons[0]->setStatus(LessonStatus::CancelledByTeacher);
        new Lesson($enrollment, new \DateTimeImmutable('2026-10-13 15:30'))->setMakeUpFor($lessons[0]);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::assertSame(16, self::getContainer()->get(LessonRepository::class)->count([]));

        $client->request('GET', '/admin/kurse/'.$courseId);
        self::assertSelectorNotExists('summary:contains("Kurs löschen")');
        $client->request('POST', '/admin/kurse/'.$courseId.'/loeschen');
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId());
        $client->submitForm('Teilnahme löschen');
        self::assertResponseRedirects('/admin/kurse/'.$courseId);
        EnrollmentFactory::assert()->empty();
        self::assertSame(0, self::getContainer()->get(LessonRepository::class)->count([]));
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        return $client;
    }
}
