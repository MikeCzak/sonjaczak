<?php

declare(strict_types=1);

namespace App\Privacy;

use App\Entity\Enrollment;
use App\Entity\Student;

/**
 * How long the data of a student is kept (umsetzung-admin.md F3, decided 2026-11-17, published in the privacy notice
 * /datenschutz/unterricht):
 *
 * - Personal data until the end of the semester after the last activity. Fixed semesters, independent of the courses
 *   in the app: winter = September to February, summer = March to August.
 * - Invoices, credit notes and payments seven years from the end of the calendar year of issue (§ 132 BAO).
 *
 * Both dates are the last day the data is kept; it is deleted from the following day on.
 */
final readonly class RetentionPolicy
{
    public const int INVOICE_YEARS = 7;

    /**
     * The latest lesson (also planned ones), trial lesson or the creation of the record, whichever is latest.
     */
    public function lastActivity(Student $student): \DateTimeImmutable
    {
        $latest = $student->getCreatedAt();
        foreach ($student->getEnrollments() as $enrollment) {
            foreach ($enrollment->getLessons() as $lesson) {
                $latest = max($latest, $lesson->getStartsAt() ?? $latest);
            }
        }
        foreach ($student->getTrialLessons() as $trial) {
            $latest = max($latest, $trial->getStartsAt() ?? $latest);
        }

        return $latest;
    }

    public function personalDataUntil(Student $student): \DateTimeImmutable
    {
        return self::endOfFollowingSemester($this->lastActivity($student));
    }

    /**
     * 31 December of the seventh year after the latest invoice or credit note of all enrollments; null without any.
     */
    public function invoicesUntil(Student $student): ?\DateTimeImmutable
    {
        $until = null;
        foreach ($student->getEnrollments() as $enrollment) {
            $enrollmentUntil = $this->enrollmentInvoicesUntil($enrollment);
            if (null !== $enrollmentUntil && (null === $until || $enrollmentUntil > $until)) {
                $until = $enrollmentUntil;
            }
        }

        return $until;
    }

    /**
     * The same for one enrollment. Invoices and credit notes of an enrollment are kept and deleted together: a credit
     * note refers to its invoice, so both stay until the later one may go.
     */
    public function enrollmentInvoicesUntil(Enrollment $enrollment): ?\DateTimeImmutable
    {
        $year = null;
        foreach ($enrollment->getInvoices() as $invoice) {
            $year = max($year ?? 0, (int) $invoice->getIssuedOn()->format('Y'));
        }

        return null === $year ? null : new \DateTimeImmutable(\sprintf('%d-12-31', $year + self::INVOICE_YEARS));
    }

    /**
     * Activity in the winter semester (September to February) → 31 August; in the summer semester (March to August)
     * → last day of February of the next year.
     */
    public static function endOfFollowingSemester(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        return match (true) {
            $month >= 9 => new \DateTimeImmutable(\sprintf('%d-08-31', $year + 1)),
            $month <= 2 => new \DateTimeImmutable(\sprintf('%d-08-31', $year)),
            // The day before 1 March: 28 or 29 February
            default => new \DateTimeImmutable(\sprintf('%d-03-01', $year + 1))->modify('-1 day'),
        };
    }
}
