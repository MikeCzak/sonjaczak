<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Enum\InvoiceStatus;
use App\Entity\Invoice;
use App\Entity\User;
use App\Lesson\MakeUpRule;
use App\Privacy\RetentionPolicy;
use App\Repository\CourseRepository;
use App\Repository\InvoiceRepository;
use App\Repository\LessonRepository;
use App\Repository\PaymentRepository;
use App\Repository\StudentRepository;
use App\Repository\TrialLessonRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Start page of the admin area (step E, datenmodell.md 9): next lessons and trial lessons, the running courses with
 * the last lesson of each student (for preparing the next one), open invoices and this year's income.
 *
 * "Now" comes from the clock service, so tests can fix the date.
 */
#[IsGranted(User::ROLE_ADMIN)]
final class DashboardController extends AbstractController
{
    private const int UPCOMING_DAYS = 7;
    private const int DELETION_NOTICE_DAYS = 30;

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(
        ClockInterface $clock,
        LessonRepository $lessons,
        TrialLessonRepository $trials,
        CourseRepository $courses,
        InvoiceRepository $invoices,
        PaymentRepository $payments,
        MakeUpRule $makeUpRule,
        StudentRepository $students,
        RetentionPolicy $retention,
    ): Response {
        $now = \DateTimeImmutable::createFromInterface($clock->now());
        $today = $now->setTime(0, 0);
        $until = $today->modify(\sprintf('+%d days', self::UPCOMING_DAYS + 1));

        // Next lessons and trial lessons in one list
        $upcoming = [];
        foreach ($lessons->findPlannedBetween($now, $until) as $lesson) {
            $upcoming[] = ['startsAt' => $lesson->getStartsAt(), 'student' => $lesson->getEnrollment()->getStudent(), 'lesson' => $lesson, 'trial' => null];
        }
        foreach ($trials->findBetween($now, $until) as $trial) {
            $upcoming[] = ['startsAt' => $trial->getStartsAt(), 'student' => $trial->getStudent(), 'lesson' => null, 'trial' => $trial];
        }
        usort($upcoming, static fn (array $a, array $b): int => $a['startsAt'] <=> $b['startsAt']);

        // Running courses: one row per enrollment
        $running = [];
        $withoutInvoice = 0;
        foreach ($courses->findRunningOn($today) as $course) {
            $rows = [];
            foreach ($course->getEnrollments() as $enrollment) {
                $held = $enrollment->getLessons()->filter(static fn ($lesson): bool => $lesson->isHeld($now));
                $rows[] = [
                    'enrollment' => $enrollment,
                    'last' => $held->last() ?: null,
                    'held' => $held->count(),
                    'cancelled' => $enrollment->getLessons()->filter(static fn ($lesson): bool => $lesson->isCancelled())->count(),
                    'make_up_due' => $makeUpRule->due($enrollment),
                ];
                if (!$enrollment->getInvoices()->exists(static fn (int $key, Invoice $invoice): bool => !$invoice->isCreditNote())) {
                    ++$withoutInvoice;
                }
            }
            $running[] = ['course' => $course, 'rows' => $rows];
        }

        // Open invoices, overdue ones first
        $open = array_values(array_filter($invoices->findOriginalsWithPayments(), static fn (Invoice $invoice): bool => InvoiceStatus::Paid !== $invoice->getStatus()));
        $open = array_map(static fn (Invoice $invoice): array => ['invoice' => $invoice, 'missed' => $invoice->getMissedDeadline($today)], $open);
        usort($open, static fn (array $a, array $b): int => (null === $a['missed']) <=> (null === $b['missed']));

        // Income of this year by course (payment date)
        $year = (int) $today->format('Y');
        $income = [];
        $incomeTotal = 0;
        foreach ($payments->findByYear($year) as $payment) {
            $course = $payment->getInvoice()->getEnrollment()->getCourse();
            $key = (int) $course->getId();
            $income[$key] ??= ['course' => $course, 'cents' => 0];
            $income[$key]['cents'] += $payment->getAmountCents();
            $incomeTotal += $payment->getAmountCents();
        }

        // Students whose personal data will be deleted soon: time to add an enrollment if someone comes back
        $deletionLimit = $today->modify(\sprintf('+%d days', self::DELETION_NOTICE_DAYS));
        $deletion = [];
        foreach ($students->findAllWithActivity() as $student) {
            $until = $retention->personalDataUntil($student);
            if ($until < $deletionLimit) {
                $deletion[] = ['student' => $student, 'until' => $until];
            }
        }
        usort($deletion, static fn (array $a, array $b): int => $a['until'] <=> $b['until']);

        return $this->render('admin/dashboard/index.html.twig', [
            'deletion' => $deletion,
            'deletion_days' => self::DELETION_NOTICE_DAYS,
            'upcoming' => $upcoming,
            'upcoming_days' => self::UPCOMING_DAYS,
            'running' => $running,
            'without_invoice' => $withoutInvoice,
            'open_invoices' => $open,
            'income' => array_values($income),
            'income_total' => $incomeTotal,
            'year' => $year,
        ]);
    }
}
