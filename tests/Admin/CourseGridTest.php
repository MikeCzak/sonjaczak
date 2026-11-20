<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Enum\CancelledBy;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\TrialLessonFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The course grid page (umsetzung-admin.md C4b, time axis C4g). Placement rules are unit-tested in
 * tests/Course/CourseGridTest.
 */
final class CourseGridTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGridShowsWeeksDaysAndLessons(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');
        $course = CourseFactory::createOne();
        $enrollment = EnrollmentFactory::createOne([
            'course' => $course,
            'student' => StudentFactory::new(['firstName' => 'Anna', 'lastName' => 'Rasterfrau']),
        ]);
        $first = $enrollment->getLessons()->first();
        self::assertNotFalse($first);
        $first->setCancelledBy(CancelledBy::Teacher);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', '/admin/kurse/'.$course->getId().'/raster');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Raster: Wintersemester 2026/27');
        // Hours on the axis; time, number and the make-up hint in the boxes (C4g)
        self::assertSelectorTextContains('thead', '15:00');
        self::assertSelectorTextContains('tbody', '15:30-16:20 · 1/15');
        self::assertSelectorTextContains('tbody', '1 Nachholstunde fällig');
        self::assertCount(1, $crawler->filter('.grid-lesson a.badge.grid-item__link[href="/admin/teilnahmen/'.$enrollment->getId().'/nachholen"]'));
        // 15 lessons, each with a link to its edit page and the name to the enrollment; the cancelled one in the low red row
        self::assertCount(15, $crawler->filter('.grid-lesson a.stretched-link[href^="/admin/einheiten/"]'));
        self::assertCount(15, $crawler->filter('.grid-lesson a.grid-item__name[href="/admin/teilnahmen/'.$enrollment->getId().'"]'));
        self::assertCount(1, $crawler->filter('tr.grid-row--cancelled .grid-lesson'));
        self::assertSelectorTextContains('tr.grid-row--cancelled', 'Anna Rasterfrau');
        // Its slot is free
        self::assertCount(1, $crawler->filter('.grid-free'));
        self::assertMatchesRegularExpression('/--start: 30; --length: 50; --lane: 0/', (string) $crawler->filter('.grid-free')->attr('style'));
        // One row per Friday (C4f); the week cell spans the day and, once, its low row of cancelled lessons
        self::assertCount(1, $crawler->filter('tbody th.grid-week[rowspan="2"]'));
        self::assertCount($crawler->filter('tbody th.grid-week')->count(), $crawler->filter('tbody tr:not(.grid-row--cancelled) th.grid-day'));
        self::assertStringStartsWith('Fr ', trim($crawler->filter('tbody th.grid-day')->first()->text()));
    }

    /**
     * Trial lessons stand on the axis at their time (C4g), linked to their page; one at the time of a lesson is
     * marked as overlapping. The list above the grid is gone.
     */
    public function testTrialLessonsInTheGrid(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');
        $enrollment = EnrollmentFactory::createOne();
        $lesson = $enrollment->getLessons()->first();
        self::assertNotFalse($lesson);
        $trial = TrialLessonFactory::createOne([
            'student' => StudentFactory::new(['firstName' => 'Sina', 'lastName' => 'Schnupper']),
            'startsAt' => $lesson->getStartsAt()?->modify('+20 minutes'),
        ]);

        $crawler = $client->request('GET', '/admin/kurse/'.$enrollment->getCourse()->getId().'/raster');

        self::assertResponseIsSuccessful();
        $box = $crawler->filter('a.grid-trial[href="/admin/schnupperstunden/'.$trial->getId().'"]');
        self::assertCount(1, $box);
        self::assertStringContainsString('Sina Schnupper', $box->text());
        self::assertStringContainsString('grid-item--overlap', (string) $box->attr('class'));
        self::assertStringContainsString('Überschneidet sich mit', (string) $box->attr('title'));
        self::assertCount(2, $crawler->filter('.grid-item--overlap'));
        self::assertSelectorNotExists('p strong:contains("Schnupperstunden")');
    }

    public function testCourseWithoutEnrollments(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');
        $course = CourseFactory::createOne();

        $client->request('GET', '/admin/kurse/'.$course->getId().'/raster');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Noch niemand eingetragen');
    }
}
