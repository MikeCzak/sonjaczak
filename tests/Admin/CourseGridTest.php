<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Enum\CancelledBy;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The read-only course grid (umsetzung-admin.md C4b). Placement rules are unit-tested in tests/Course/CourseGridTest.
 */
final class CourseGridTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGridShowsSlotsWeeksAndLessons(): void
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
        self::assertSelectorTextContains('thead', 'Fr 15:30');
        self::assertSelectorTextContains('thead', '14 von 15');
        self::assertSelectorTextContains('thead', '1 Nachholstunde fällig');
        // 15 lessons, each a link to its edit page; the cancelled one in the low red row
        self::assertCount(15, $crawler->filter('a.grid-lesson[href^="/admin/einheiten/"]'));
        self::assertCount(1, $crawler->filter('tr.grid-row--cancelled a.grid-lesson'));
        self::assertSelectorTextContains('tr.grid-row--cancelled', 'Anna Rasterfrau');
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
