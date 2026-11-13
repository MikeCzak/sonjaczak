<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enum\BreakSource;
use App\Entity\Enum\Term;
use App\Repository\CourseRepository;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Courses and breaks (step C2). The OpenHolidays API is replaced by recorded answers in the test environment.
 */
final class CourseTest extends WebTestCase
{
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

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        return $client;
    }
}
