<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Enrollment;
use App\Entity\Enum\CancelledBy;
use App\Entity\Lesson;
use App\Repository\EnrollmentRepository;
use App\Repository\LessonRepository;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Enrollments and lessons (step C3).
 */
final class EnrollmentTest extends WebTestCase
{
    use ClockSensitiveTrait;
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
            'lesson[cancelledBy]' => CancelledBy::Teacher->value,
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

    /**
     * A break over a lesson: without confirmation nothing is saved, with it the lesson goes (also a past one,
     * umsetzung-admin.md C4 question 6) and comes again at the end.
     */
    public function testNewBreakDeletesLessonsAfterConfirmation(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-11-01 10:00'));
        $client = $this->loggedInClient();
        $enrollment = EnrollmentFactory::createOne();
        $course = $enrollment->getCourse();
        $form = ['course_break[label]' => 'Schulautonom', 'course_break[firstDay]' => '2026-10-16'];

        $client->request('GET', '/admin/kurse/'.$course->getId());
        $client->submitForm('Eintragen', $form);
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'Nichts gespeichert');
        self::assertSelectorTextContains('.alert-warning', 'Fr 16.10. 15:30, gehalten');
        self::assertSame(0, CourseFactory::repository()->find($course->getId() ?? 0)?->getBreaks()->count());

        $client->submitForm('Eintragen', $form + ['confirmed' => '1']);
        self::assertResponseRedirects('/admin/kurse/'.$course->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-info', 'Einheiten: 1 entfernt, 1 angelegt.');

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
        $lessons[0]->setCancelledBy(CancelledBy::Teacher);
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

    /**
     * umsetzung-admin.md C7: lowering lists the lessons that go and deletes them only after confirmation.
     */
    public function testLoweringThePlannedNumberAsksFirst(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-10-30 10:00'));
        $client = $this->loggedInClient();
        $enrollment = EnrollmentFactory::createOne();
        $lessons = self::getContainer()->get(LessonRepository::class);

        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId());
        self::assertSelectorTextContains('#anzahl + p', '3 Einheiten haben schon begonnen');
        $client->submitForm('Anzahl ändern', [
            'planned_lessons[plannedLessons]' => '12',
            'planned_lessons[reason]' => 'Umzug nach Graz',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-warning', 'Bei 12 Einheiten werden diese Einheiten gelöscht');
        self::assertSelectorCount(3, '.alert-warning li');
        self::assertSelectorTextContains('.alert-warning li:first-child', 'Fr 01.01.2027 15:30, geplant');
        self::assertSame(15, $lessons->count(['enrollment' => $enrollment->getId()]), 'nothing deleted yet');

        $client->submitForm('Einheiten löschen und speichern');
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Anzahl auf 12 gesetzt. Einheiten: 3 gelöscht, 0 angelegt.');
        self::assertSelectorTextContains('main', '12 von 12 (Kurs: 15)');
        self::assertSame(12, $lessons->count(['enrollment' => $enrollment->getId()]));
        $saved = EnrollmentFactory::repository()->find($enrollment->getId() ?? 0);
        self::assertSame(12, $saved?->getPlannedLessons());
        self::assertSame('Umzug nach Graz', $saved->getPlannedLessonsReason());
    }

    public function testEndingWithInvoicePointsToTheCreditNote(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-10-30 10:00'));
        $client = $this->loggedInClient();
        $enrollment = InvoiceFactory::createOne()->getEnrollment();

        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId());
        // The number field is ignored by "end"
        $client->submitForm('Teilnahme beenden', ['planned_lessons[plannedLessons]' => '', 'planned_lessons[reason]' => 'Krankheit']);
        self::assertSelectorCount(12, '.alert-warning li');
        $client->submitForm('Einheiten löschen und speichern');
        $client->followRedirect();

        self::assertSelectorTextContains('main', '3 von 3 (Kurs: 15)');
        self::assertSelectorTextContains('.alert-info', 'erstelle auf der Seite der Honorarnote eine Gutschrift');
        // Still not deletable: the invoice stays
        self::assertSelectorNotExists('summary:contains("Teilnahme löschen")');
    }

    public function testBelowTheMinimumOrWithoutReasonNothingChanges(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-10-30 10:00'));
        $client = $this->loggedInClient();
        $enrollment = EnrollmentFactory::createOne();

        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId());
        $client->submitForm('Anzahl ändern', ['planned_lessons[plannedLessons]' => '2', 'planned_lessons[reason]' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#anzahl ~ form', 'Erlaubt sind 3 bis 40.');
        self::assertSelectorTextContains('#anzahl ~ form', 'Dieser Wert sollte nicht leer sein.');
        self::assertSame(15, self::getContainer()->get(LessonRepository::class)->count(['enrollment' => $enrollment->getId()]));
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        return $client;
    }
}
