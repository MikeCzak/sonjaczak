<?php

declare(strict_types=1);

namespace App\Tests\Story;

use App\Entity\Embeddable\Address;
use App\Entity\Embeddable\Recipient;
use App\Entity\Enum\LessonStatus;
use App\Entity\Enum\PaymentMethod;
use App\Entity\Payment;
use App\Entity\Settings;
use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\GuardianFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\TrialLessonFactory;
use App\Tests\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

use function Zenstruck\Foundry\Persistence\flush_after;
use function Zenstruck\Foundry\Persistence\persist;

/**
 * Invented data for the local database: `bin/console foundry:load-fixtures main`.
 * Never real people, see "Daten für Entwicklung und Tests" in docs/planung/datenmodell.md.
 * Extended step by step (docs/planung/umsetzung-admin.md).
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        // Logins with UserFactory::PASSWORD (docs/entwicklung.md)
        UserFactory::new()->superAdmin()->create(['email' => 'mike@example.com']);
        UserFactory::new()->admin()->create(['email' => 'sonja@example.com']);

        // Invented sender data (Sonja's real data are entered in production only). The IBAN is the example from
        // the Austrian banks' documentation, it belongs to nobody.
        persist(Settings::class, [
            'senderName' => 'Erika Musterfrau',
            'senderTitle' => 'Bakk.phil.',
            'senderAddress' => new Address('Musterstraße 1', '8010', 'Graz'),
            'iban' => 'AT611904300234573201',
            'bic' => 'BKAUATWW',
            'bankName' => 'Musterbank',
            'invoicePlace' => 'Graz',
            // Placeholder: the real wording is Sonja's and must be checked, not invented here
            'vatNote' => 'Beispieltext für den Hinweis zur Umsatzsteuer.',
        ]);

        // Adults, children with one guardian each, and two siblings sharing one
        $adults = StudentFactory::createMany(12);
        StudentFactory::new()->minor()->many(6)->create();
        $siblingsParent = GuardianFactory::createOne();
        StudentFactory::new()->minor()->many(2)->create(['guardian' => $siblingsParent, 'lastName' => 'Geschwister']);

        // Breaks entered here instead of fetched: loading fixtures must not depend on the OpenHolidays API
        $course = CourseFactory::new()
            ->withBreak('Nationalfeiertag', '2026-10-26', '2026-10-26')
            ->withBreak('Herbstferien', '2026-10-27', '2026-10-31')
            ->withBreak('Allerseelen', '2026-11-02', '2026-11-02')
            ->withBreak('Mariä Empfängnis', '2026-12-08', '2026-12-08')
            ->withBreak('Weihnachtsferien', '2026-12-24', '2027-01-06')
            ->withBreak('Semesterferien', '2027-02-15', '2027-02-20')
            ->create();

        // Two people who came for a trial lesson and are not enrolled yet
        TrialLessonFactory::createOne(['student' => $adults[6]]);
        TrialLessonFactory::createOne(['student' => $adults[7], 'startsAt' => new \DateTimeImmutable('2026-11-18 17:00'), 'note' => null]);

        // Friday afternoon as in Sonja's list; lessons before today are held, with a song as note
        // flush_after(): changes made after create() are saved in one flush at the end
        flush_after(static function () use ($adults, $course): void {
            $songs = ['Runaway', 'Flowers', 'Auf uns', 'Animal', 'Hallelujah', 'Skyfall', 'Valerie', 'Jolene'];
            $today = new \DateTimeImmutable('today');
            $enrollments = $invoices = [];
            foreach (array_slice($adults, 0, 6) as $i => $student) {
                $enrollments[] = $enrollment = EnrollmentFactory::createOne([
                    'course' => $course,
                    'student' => $student,
                    'startTime' => new \DateTimeImmutable('13:40')->modify(\sprintf('+%d minutes', 55 * $i)),
                ]);
                foreach ($enrollment->getLessons() as $n => $lesson) {
                    if ($lesson->getStartsAt() >= $today) {
                        break;
                    }
                    $lesson->setStatus(LessonStatus::Held)->setNote($songs[($i + $n) % \count($songs)]);
                }
            }
            // One cancellation of each kind in the first enrollment
            $lessons = $enrollments[0]->getLessons()->getValues();
            $lessons[1]->setStatus(LessonStatus::CancelledByStudent)->setNote('krank');
            $lessons[2]->setStatus(LessonStatus::CancelledByTeacher)->setNote('Konzert');

            // Invoices for the first two enrollments, one with instalments and the first one paid; the others still
            // need one
            foreach (array_slice($enrollments, 0, 2) as $n => $enrollment) {
                $student = $enrollment->getStudent() ?? throw new \LogicException('Enrollment without student');
                $invoices[] = InvoiceFactory::createOne([
                    'enrollment' => $enrollment,
                    'number' => $n + 1,
                    'amountCents' => $enrollment->getPriceCents(),
                    'secondInstallmentDueOn' => 0 === $n ? new \DateTimeImmutable('2026-12-31') : null,
                    'recipient' => new Recipient($student->getFullName(), $student->getAddress()),
                ]);
            }
            persist(Payment::class, [
                'invoice' => $invoices[0],
                'amountCents' => $invoices[0]->getFirstInstallmentCents(),
                'paidOn' => new \DateTimeImmutable('2026-10-12'),
                'method' => PaymentMethod::Transfer,
            ]);
        });
    }
}
