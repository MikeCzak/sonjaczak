<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Enrollment;
use App\Entity\Enum\LessonStatus;
use App\Entity\Lesson;
use App\Repository\EnrollmentRepository;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\TrialLessonFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Trial lessons (step C5) and the takeover into a course.
 */
final class TrialLessonTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testCreateTrialLesson(): void
    {
        $client = $this->loggedInClient();
        $student = StudentFactory::createOne(['firstName' => 'Lena', 'lastName' => 'Muster']);

        $client->request('GET', '/admin/schnupperstunden/neu?student='.$student->getId());
        $client->submitForm('Speichern', ['trial_lesson[startsAt]' => '2026-09-30T16:00']);
        self::assertResponseRedirects('/admin/schnupperstunden');
        $client->followRedirect();
        self::assertSelectorTextContains('tbody', 'Mi 30.09.2026 16:00');
        self::assertSelectorTextContains('tbody', 'Lena Muster');
    }

    public function testTrialLessonIsTakenOverIntoTheCourse(): void
    {
        $client = $this->loggedInClient();
        $course = CourseFactory::createOne();
        $trial = TrialLessonFactory::createOne();
        $student = $trial->getStudent();
        self::assertNotNull($student);

        $client->request('GET', \sprintf('/admin/kurse/%d/teilnahmen/neu?student=%d', $course->getId(), $student->getId()));
        self::assertCheckboxChecked('enrollment[trials][]');
        $client->submitForm('Teilnahme anlegen', ['enrollment[startTime]' => '15:30']);
        self::assertResponseRedirects();

        $enrollment = self::getContainer()->get(EnrollmentRepository::class)->findOneBy([]);
        self::assertInstanceOf(Enrollment::class, $enrollment);
        $lessons = $enrollment->getLessons();
        // The trial counts: 1 taken over + 14 generated
        self::assertCount(15, $lessons);
        $first = $lessons->first();
        self::assertInstanceOf(Lesson::class, $first);
        self::assertTrue($first->isFromTrial());
        self::assertSame(LessonStatus::Held, $first->getStatus());
        self::assertSame('2026-09-30 16:00', $first->getStartsAt()?->format('Y-m-d H:i'));
        self::assertSame('Stimmumfang, „Someone Like You“', $first->getNote());
        TrialLessonFactory::assert()->empty();
    }

    public function testStudentWithEnrollmentCannotBeDeletedStudentWithTrialCan(): void
    {
        $client = $this->loggedInClient();
        $enrolled = EnrollmentFactory::createOne()->getStudent();
        $trial = TrialLessonFactory::createOne();

        $client->request('GET', '/admin/schueler/'.$enrolled?->getId());
        self::assertSelectorTextContains('main', 'Löschen ist erst möglich');
        $client->request('POST', '/admin/schueler/'.$enrolled?->getId().'/loeschen');
        self::assertResponseStatusCodeSame(403);

        // Somebody who only came for a trial lesson is deleted completely, the trial lesson with them
        $client->request('GET', '/admin/schueler/'.$trial->getStudent()?->getId());
        $client->submitForm('Schüler:in löschen');
        self::assertResponseRedirects('/admin/schueler');
        TrialLessonFactory::assert()->empty();
        StudentFactory::assert()->count(1);
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        return $client;
    }
}
