<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Enum\CancelledBy;
use App\Entity\Lesson;
use App\Repository\LessonRepository;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\TrialLessonFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The course grid's endpoints (umsetzung-admin.md C4c/C4d): PATCH and DELETE /admin/einheiten/{id},
 * POST /admin/einheiten/{id}/doppelstunde. Moving by drag and drop (C4h) is PATCH with startsAt.
 * Anonymous access is covered by AdminRoutesRequireLoginTest.
 */
final class LessonPatchTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->loginUser(UserFactory::new()->admin()->create(), 'admin');
        // Before the course: every lesson is still planned
        self::mockTime(new \DateTimeImmutable('2026-10-01 10:00'));
    }

    public function testCancelAndNote(): void
    {
        $course = CourseFactory::createOne();
        $enrollment = EnrollmentFactory::createOne(['course' => $course]);
        $lesson = $this->lessonOn($enrollment, '2026-10-16');

        $answer = $this->patch($lesson, ['cancelledBy' => 'teacher', 'note' => '  Konzert  '], $this->token($course));

        self::assertResponseIsSuccessful();
        self::assertSame('teacher', $answer['lesson']['cancelledBy']);
        self::assertSame('Konzert', $answer['lesson']['note']);
        self::assertSame('abgesagt (ich)', $answer['lesson']['stateLabel']);
        self::assertSame('14 von 15', $answer['enrollments'][0]['count']);
        self::assertSame('1 Nachholstunde fällig', $answer['enrollments'][0]['makeUpLabel']);

        $saved = $this->reload($lesson);
        self::assertSame(CancelledBy::Teacher, $saved->getCancelledBy());
        self::assertSame('Konzert', $saved->getNote());
    }

    public function testOnlySentKeysChange(): void
    {
        $course = CourseFactory::createOne();
        $lesson = $this->lessonOn(EnrollmentFactory::createOne(['course' => $course]), '2026-10-16');
        $token = $this->token($course);
        $this->patch($lesson, ['cancelledBy' => 'student', 'note' => 'krank'], $token);

        $answer = $this->patch($lesson, ['note' => ''], $token);

        self::assertSame('student', $answer['lesson']['cancelledBy'], 'not sent, so unchanged');
        self::assertNull($answer['lesson']['note'], 'empty note is stored as NULL');
    }

    public function testUnknownCancellationIsRejected(): void
    {
        $course = CourseFactory::createOne();
        $lesson = $this->lessonOn(EnrollmentFactory::createOne(['course' => $course]), '2026-10-16');

        $answer = $this->patch($lesson, ['cancelledBy' => 'nobody'], $this->token($course));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Unbekannte Art der Absage.', $answer['error']);
        self::assertNull($this->reload($lesson)->getCancelledBy());
    }

    public function testWrongTokenIsRejected(): void
    {
        $course = CourseFactory::createOne();
        $other = CourseFactory::createOne();
        $lesson = $this->lessonOn(EnrollmentFactory::createOne(['course' => $course]), '2026-10-16');

        // A valid token, but for another course's grid
        $this->patch($lesson, ['cancelledBy' => 'teacher'], $this->token($other));

        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->reload($lesson)->getCancelledBy());
    }

    /**
     * Withdrawing a cancellation: the double lesson of the neighbour in the freed slot goes, after confirmation,
     * and the neighbour gets its lesson at the end back (question 8, points 2 and 4).
     */
    public function testWithdrawingACancellationDeletesWhatOverlapsAfterConfirmation(): void
    {
        $course = CourseFactory::createOne();
        $anna = EnrollmentFactory::createOne(['course' => $course, 'startTime' => new \DateTimeImmutable('15:30')]);
        $berta = EnrollmentFactory::createOne([
            'course' => $course,
            'student' => StudentFactory::new(['firstName' => 'Berta', 'lastName' => 'Nachbar']),
            'startTime' => new \DateTimeImmutable('16:20'),
        ]);
        $annaLesson = $this->lessonOn($anna, '2026-10-16')->setCancelledBy(CancelledBy::Student);
        // Double lesson of Berta in Anna's slot; Berta's last lesson went for it
        $double = new Lesson($berta, new \DateTimeImmutable('2026-10-16 15:30'))->setNote('Vorsingen');
        $last = $berta->getLessons()->filter(static fn (Lesson $l): bool => $l !== $double)->last();
        self::assertInstanceOf(Lesson::class, $last);
        $berta->removeLesson($last);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($double);
        $entityManager->flush();
        $token = $this->token($course);

        $answer = $this->patch($annaLesson, ['cancelledBy' => null], $token);

        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('Berta Nachbar, Fr 16.10. 15:30, geplant (Vorsingen)', $answer['confirm']);
        self::assertTrue($this->reload($annaLesson)->isCancelled(), 'nothing changed before the confirmation');

        $answer = $this->patch($annaLesson, ['cancelledBy' => null, 'deleteOverlapping' => true], $token);

        self::assertResponseIsSuccessful();
        self::assertSame([$double->getId()], $answer['deleted']);
        self::assertSame(1, $answer['added']);
        self::assertSame('planned', $answer['lesson']['state']);
        $lessons = self::getContainer()->get(LessonRepository::class);
        self::assertNull($lessons->find($double->getId() ?? 0));
        self::assertSame(15, $lessons->count(['enrollment' => $berta->getId()]));
    }

    public function testDeleteAddsALessonAtTheEnd(): void
    {
        $course = CourseFactory::createOne();
        $enrollment = EnrollmentFactory::createOne(['course' => $course]);
        $lesson = $this->lessonOn($enrollment, '2026-10-16');
        $lastBefore = $this->lastStart($enrollment);

        $this->client->request('DELETE', '/admin/einheiten/'.$lesson->getId(), server: ['HTTP_X_CSRF_TOKEN' => $this->token($course)]);

        self::assertResponseIsSuccessful();
        $lessons = self::getContainer()->get(LessonRepository::class)->findBy(['enrollment' => $enrollment->getId()], ['startsAt' => 'ASC']);
        self::assertCount(15, $lessons);
        self::assertNotContains('2026-10-16', array_map(static fn (Lesson $l): string => $l->getStartsAt()?->format('Y-m-d') ?? '', $lessons), 'not put back into the same week');
        self::assertGreaterThan($lastBefore, $lessons[14]->getStartsAt());
    }

    public function testDeleteNeedsTheToken(): void
    {
        $course = CourseFactory::createOne();
        $lesson = $this->lessonOn(EnrollmentFactory::createOne(['course' => $course]), '2026-10-16');

        $this->client->request('DELETE', '/admin/einheiten/'.$lesson->getId(), server: ['HTTP_X_CSRF_TOKEN' => 'wrong']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Anna 15:30, Berta 16:20. Anna gets a double lesson after hers, in Berta's slot: only once Berta cancelled.
     */
    public function testDoubleLessonInTheFreedSlot(): void
    {
        $course = CourseFactory::createOne();
        $anna = EnrollmentFactory::createOne(['course' => $course, 'startTime' => new \DateTimeImmutable('15:30')]);
        $berta = EnrollmentFactory::createOne([
            'course' => $course,
            'student' => StudentFactory::new(['firstName' => 'Berta', 'lastName' => 'Nachbar']),
            'startTime' => new \DateTimeImmutable('16:20'),
        ]);
        $annaLesson = $this->lessonOn($anna, '2026-10-16');
        $lastBefore = $this->lastStart($anna);
        $token = $this->token($course);
        $url = '/admin/einheiten/'.$annaLesson->getId().'/doppelstunde';

        $answer = $this->post($url, ['position' => 'after'], $token);
        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('Berta Nachbar, Fr 16.10. 16:20, geplant', $answer['error']);

        $this->patch($this->lessonOn($berta, '2026-10-16'), ['cancelledBy' => 'student'], $token);
        $answer = $this->post($url, ['position' => 'after'], $token);

        self::assertResponseIsSuccessful();
        self::assertNull($answer['message'], 'the last lesson went');
        $lessons = self::getContainer()->get(LessonRepository::class)->findBy(['enrollment' => $anna->getId()], ['startsAt' => 'ASC']);
        self::assertCount(15, $lessons);
        $starts = array_map(static fn (Lesson $l): string => $l->getStartsAt()?->format('Y-m-d H:i') ?? '', $lessons);
        self::assertContains('2026-10-16 16:20', $starts);
        self::assertNotContains($lastBefore->format('Y-m-d H:i'), $starts);
        self::assertSame(50, $lessons[2]->getDurationMinutes(), 'the length of Anna\'s enrollment');
    }

    public function testDoubleLessonBeforeAndInvalidPosition(): void
    {
        $course = CourseFactory::createOne();
        $lesson = $this->lessonOn(EnrollmentFactory::createOne(['course' => $course]), '2026-10-16');
        $token = $this->token($course);
        $url = '/admin/einheiten/'.$lesson->getId().'/doppelstunde';

        $this->post($url, ['position' => 'beside'], $token);
        self::assertResponseStatusCodeSame(422);

        $this->post($url, ['position' => 'before'], $token);
        self::assertResponseIsSuccessful();
        self::assertNotNull(self::getContainer()->get(LessonRepository::class)->findOneBy(['startsAt' => new \DateTimeImmutable('2026-10-16 14:40')]));
    }

    /**
     * Dragged within the day (C4h): saved, and the answer carries the day's row drawn anew, with the lesson at its new
     * time and a free slot where it was.
     */
    public function testMoveWithinTheDay(): void
    {
        $course = CourseFactory::createOne();
        $lesson = $this->lessonOn(EnrollmentFactory::createOne(['course' => $course]), '2026-10-16');

        $answer = $this->patch($lesson, ['startsAt' => '2026-10-16T16:20'], $this->token($course));

        self::assertResponseIsSuccessful();
        self::assertSame('2026-10-16 16:20', $this->reload($lesson)->getStartsAt()?->format('Y-m-d H:i'));
        $row = $answer['row']['html'] ?? '';
        self::assertStringContainsString('data-day="2026-10-16"', $row);
        self::assertStringContainsString('data-lesson-id="'.$lesson->getId().'"', $row);
        self::assertStringContainsString('16:20–17:10 · 2/15', $row);
        self::assertStringContainsString('15:30 · frei', $row, 'the slot it left');
        // The lesson now ends at 17:10, so the axis reaches 18:00 instead of 17:00: the browser reloads then
        self::assertStringContainsString('data-axis="900-1080"', $row);
    }

    public function testMoveIsRefusedWhereSomethingTakesPlace(): void
    {
        $course = CourseFactory::createOne();
        $anna = EnrollmentFactory::createOne(['course' => $course]);
        EnrollmentFactory::createOne([
            'course' => $course,
            'student' => StudentFactory::new(['firstName' => 'Berta', 'lastName' => 'Nachbar']),
            'startTime' => new \DateTimeImmutable('16:30'),
        ]);
        TrialLessonFactory::createOne([
            'student' => StudentFactory::new(['firstName' => 'Clara', 'lastName' => 'Probe']),
            'startsAt' => new \DateTimeImmutable('2026-10-16 13:00'),
        ]);
        $lesson = $this->lessonOn($anna, '2026-10-16');
        $token = $this->token($course);

        $answer = $this->patch($lesson, ['startsAt' => '2026-10-16T15:45'], $token);
        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('Berta Nachbar, Fr 16.10. 16:30, geplant', $answer['error']);

        $answer = $this->patch($lesson, ['startsAt' => '2026-10-16T13:30'], $token);
        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('Clara Probe, Fr 16.10. 13:00, Schnupperstunde', $answer['error']);
        self::assertSame('15:30', $this->reload($lesson)->getStartsAt()?->format('H:i'));

        // Back to back on both sides is no overlap: 13:50 after the trial lesson, ends 16:30 before Berta
        $this->patch($lesson, ['startsAt' => '2026-10-16T13:50'], $token);
        self::assertResponseIsSuccessful();
        $this->patch($lesson, ['startsAt' => '2026-10-16T15:40'], $token);
        self::assertResponseIsSuccessful();
    }

    public function testMoveRules(): void
    {
        $course = CourseFactory::createOne();
        $enrollment = EnrollmentFactory::createOne(['course' => $course]);
        $lesson = $this->lessonOn($enrollment, '2026-10-16');
        $token = $this->token($course);

        foreach (['2026-10-16T15:33', '2026-10-17T15:30', '16:00', 1530, null] as $startsAt) {
            $this->patch($lesson, ['startsAt' => $startsAt], $token);
            self::assertResponseStatusCodeSame(422, 'refused: '.var_export($startsAt, true));
        }

        $cancelled = $this->lessonOn($enrollment, '2026-10-23');
        $this->patch($cancelled, ['cancelledBy' => 'student'], $token);
        $answer = $this->patch($cancelled, ['startsAt' => '2026-10-23T16:00'], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('nur geplante Einheiten', $answer['error']);

        // On the day itself: not into the past, and a lesson that is over cannot be moved at all
        self::mockTime(new \DateTimeImmutable('2026-10-16 14:00'));
        $answer = $this->patch($lesson, ['startsAt' => '2026-10-16T13:00'], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Vergangenheit', $answer['error']);
        $held = $this->lessonOn($enrollment, '2026-10-09');
        $this->patch($held, ['startsAt' => '2026-10-09T17:00'], $token);
        self::assertResponseStatusCodeSame(422);

        self::assertSame('2026-10-16 15:30', $this->reload($lesson)->getStartsAt()?->format('Y-m-d H:i'));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{error: string, message: string|null}
     */
    private function post(string $url, array $data, string $token): array
    {
        $this->client->request('POST', $url, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $token,
        ], content: json_encode($data, \JSON_THROW_ON_ERROR));

        /** @var array{error: string, message: string|null} */
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function lastStart(Enrollment $enrollment): \DateTimeImmutable
    {
        $last = $enrollment->getLessons()->last();
        self::assertInstanceOf(Lesson::class, $last);

        return $last->getStartsAt() ?? throw new \LogicException('No start');
    }

    /**
     * @param array<string, mixed> $data
     *
     * The shape of every answer, error, confirm and success merged; each test reads only the keys of its case.
     *
     * @return array{lesson: array{cancelledBy: string|null, note: string|null, state: string, stateLabel: string}, enrollments: list<array{count: string, makeUpLabel: string|null}>, deleted: list<int>, added: int, row: array{html: string}|null, error: string, confirm: string}
     */
    private function patch(Lesson $lesson, array $data, string $token): array
    {
        $this->client->request('PATCH', '/admin/einheiten/'.$lesson->getId(), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $token,
        ], content: json_encode($data, \JSON_THROW_ON_ERROR));

        /** @var array{lesson: array{cancelledBy: string|null, note: string|null, state: string, stateLabel: string}, enrollments: list<array{count: string, makeUpLabel: string|null}>, deleted: list<int>, added: int, row: array{html: string}|null, error: string, confirm: string} */
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * The token as the grid page hands it out (stored in the test client's session).
     */
    private function token(Course $course): string
    {
        $crawler = $this->client->request('GET', '/admin/kurse/'.$course->getId().'/raster');
        self::assertResponseIsSuccessful();

        return (string) $crawler->filter('[data-grid-token-value]')->attr('data-grid-token-value');
    }

    private function lessonOn(Enrollment $enrollment, string $day): Lesson
    {
        foreach ($enrollment->getLessons() as $lesson) {
            if ($day === $lesson->getStartsAt()?->format('Y-m-d')) {
                return $lesson;
            }
        }

        throw new \LogicException('No lesson on '.$day);
    }

    private function reload(Lesson $lesson): Lesson
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->find(Lesson::class, $lesson->getId()) ?? throw new \LogicException('Lesson gone');
    }
}
