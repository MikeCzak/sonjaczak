<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enum\BreakSource;
use App\Entity\Enum\Term;
use App\Entity\Lesson;
use App\Repository\CourseRepository;
use App\Repository\LessonRepository;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Courses and breaks (step C2). The OpenHolidays API is replaced by recorded answers in the test environment.
 */
final class CourseTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    public function testNewCourseGetsHolidaySuggestions(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/admin/kurse/neu');
        $client->submitForm('Kurs anlegen', [
            'course[term]' => 'winter',
            'course[year]' => '2026',
            'course[startsOn]' => '2026-10-05',
            'course[endsOn]' => '2027-02-26',
            'course[room]' => '1.12',
            'course[priceCents]' => '495,00',
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();

        self::assertSelectorTextContains('h1', 'Wintersemester 2026/27');
        self::assertSelectorTextContains('.alert-success', 'Schulferien und Feiertage sind eingetragen');
        self::assertSelectorTextContains('main', 'Weihnachtsferien');
        self::assertSelectorTextContains('main', 'Semesterferien');

        $course = self::getContainer()->get(CourseRepository::class)->findOneBy([]);
        self::assertInstanceOf(Course::class, $course);
        self::assertSame(Term::Winter, $course->getTerm());
        self::assertSame(49500, $course->getPriceCents());
        self::assertSame(15, $course->getPlannedLessons());
        self::assertCount(7, $course->getBreaks());
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/admin/kurse/neu');
        $client->submitForm('Kurs anlegen', [
            'course[startsOn]' => '2027-02-26',
            'course[endsOn]' => '2026-10-05',
            'course[priceCents]' => '495',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Das Ende muss nach dem Beginn liegen');
    }

    public function testSingleDayBreakAndDelete(): void
    {
        $client = $this->loggedInClient();
        $course = CourseFactory::createOne();

        $client->request('GET', '/admin/kurse/'.$course->getId());
        $client->submitForm('Eintragen', [
            'course_break[label]' => 'Schulautonomer Tag',
            'course_break[firstDay]' => '2026-11-20',
        ]);
        self::assertResponseRedirects('/admin/kurse/'.$course->getId());

        // Read again from the database: the request ran in its own entity manager
        $break = CourseFactory::repository()->find($course->getId() ?? 0)?->getBreaks()->first();
        self::assertInstanceOf(CourseBreak::class, $break);
        self::assertSame(BreakSource::Manual, $break->getSource());
        self::assertSame('2026-11-20', $break->getLastDay()?->format('Y-m-d'));

        $client->followRedirect();
        $client->submitForm('Löschen');
        self::assertResponseRedirects('/admin/kurse/'.$course->getId());
        self::assertSame(0, CourseFactory::repository()->find($course->getId() ?? 0)?->getBreaks()->count());
    }

    public function testBreakOfAnotherCourseCannotBeDeleted(): void
    {
        $client = $this->loggedInClient();
        $mine = CourseFactory::createOne();
        $other = CourseFactory::new()->withBreak()->create();
        $break = $other->getBreaks()->first();
        self::assertInstanceOf(CourseBreak::class, $break);

        $client->request('POST', \sprintf('/admin/kurse/%d/ferien/%d/loeschen', $mine->getId(), $break->getId()));
        self::assertResponseStatusCodeSame(404);
    }

    public function testCheckListsTheLessonsInThePeriod(): void
    {
        $client = $this->loggedInClient();
        $course = EnrollmentFactory::createOne(['student' => StudentFactory::new(['firstName' => 'Anna', 'lastName' => 'Ferien'])])->getCourse();
        $url = '/admin/kurse/'.$course->getId().'/ferien/pruefen';

        $client->request('GET', $url, ['von' => '2026-10-12', 'bis' => '2026-10-18']);
        $answer = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($answer);
        self::assertIsString($answer['confirm']);
        self::assertStringContainsString('Anna Ferien, Fr 16.10. 15:30', $answer['confirm']);

        // A single day without a lesson: nothing to ask
        $client->request('GET', $url, ['von' => '2026-10-13']);
        self::assertSame('{"confirm":null}', $client->getResponse()->getContent());
    }

    public function testDeletingABreakGivesTheLessonBack(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-10-01 10:00'));
        $client = $this->loggedInClient();
        $course = CourseFactory::createOne();
        $course->addBreak(new CourseBreak('Schulautonom', new \DateTimeImmutable('2026-10-16')));
        $enrollment = EnrollmentFactory::createOne(['course' => $course]);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $days = static fn (): array => array_map(
            static fn (Lesson $lesson): string => (string) $lesson->getStartsAt()?->format('Y-m-d'),
            self::getContainer()->get(LessonRepository::class)->findBy(['enrollment' => $enrollment->getId()]),
        );
        self::assertNotContains('2026-10-16', $days());

        $client->request('GET', '/admin/kurse/'.$course->getId());
        $client->submitForm('Löschen');
        $client->followRedirect();

        self::assertSelectorTextContains('.alert-info', 'Einheiten: 1 entfernt, 1 angelegt.');
        self::assertContains('2026-10-16', $days());
        self::assertCount(15, $days());
    }

    /**
     * C4e: the week link of the grid opens the course page with the break form prefilled; saving leads back.
     */
    public function testBreakFromTheGridWeek(): void
    {
        $client = $this->loggedInClient();
        $course = EnrollmentFactory::createOne()->getCourse();

        $crawler = $client->request('GET', '/admin/kurse/'.$course->getId().'/raster');
        $client->click($crawler->filter('a:contains("Ferien …")')->eq(1)->link());

        self::assertInputValueSame('course_break[firstDay]', '2026-10-12');
        self::assertInputValueSame('course_break[lastDay]', '2026-10-18');
        // confirmed: the Friday lesson in that week goes (the popup is JavaScript, here the field is set directly)
        $client->submitForm('Eintragen', ['course_break[label]' => 'Projektwoche', 'confirmed' => '1']);
        self::assertResponseRedirects('/admin/kurse/'.$course->getId().'/raster');
        $client->followRedirect();
        self::assertSelectorTextContains('tr.grid-row--break', 'Projektwoche');
    }

    public function testTitleShowsTheWeekdaysOfTheEnrollments(): void
    {
        $client = $this->loggedInClient();
        $course = CourseFactory::createOne();
        EnrollmentFactory::createOne(['course' => $course]);
        EnrollmentFactory::createOne(['course' => $course, 'weekday' => 1]);

        $client->request('GET', '/admin/kurse');

        self::assertSelectorTextContains('main table', 'Wintersemester 2026/27 (Mo, Fr)');
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        return $client;
    }
}
