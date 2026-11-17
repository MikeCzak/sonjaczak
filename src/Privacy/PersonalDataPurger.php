<?php

declare(strict_types=1);

namespace App\Privacy;

use App\Entity\Enrollment;
use App\Entity\Guardian;
use App\Entity\Student;
use App\Repository\EnrollmentRepository;
use App\Repository\InvoicePdfRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Deletes what RetentionPolicy no longer allows to keep (umsetzung-admin.md F3, decided 2026-11-17):
 *
 * 1. Invoices and credit notes of an enrollment seven years after the end of the year of the latest one, with their
 *    payments and PDFs. An enrollment without a person goes with them.
 * 2. Students after the end of the semester following their last activity, with trial lessons. Each enrollment goes
 *    with its lessons, unless it still has invoices: then it stays without a person (only course, price and the
 *    invoices, whose recipient is a copy on the invoice), its lessons go.
 * 3. Guardians whose students were all deleted in this run.
 *
 * Courses, breaks, accounts and settings stay. Everything is saved with one flush, which Doctrine runs in one
 * transaction (UnitOfWork::commit()): either all of it is deleted or nothing.
 */
final readonly class PersonalDataPurger
{
    public function __construct(
        private RetentionPolicy $policy,
        private StudentRepository $students,
        private EnrollmentRepository $enrollments,
        private InvoicePdfRepository $pdfs,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param \DateTimeImmutable $today data whose last day to keep is before this day is deleted
     * @param bool               $dryRun only count, change nothing
     */
    public function purge(\DateTimeImmutable $today, bool $dryRun = false): PurgeResult
    {
        $today = $today->setTime(0, 0);
        $result = new PurgeResult();

        $expired = [];
        foreach ($this->enrollments->findWithInvoices() as $enrollment) {
            $until = $this->policy->enrollmentInvoicesUntil($enrollment);
            if (null !== $until && $until < $today) {
                $expired[] = $enrollment;
            }
        }

        $students = array_values(array_filter(
            $this->students->findAllWithActivity(),
            fn (Student $student): bool => $this->policy->personalDataUntil($student) < $today,
        ));

        foreach ($expired as $enrollment) {
            $result->invoices += $enrollment->getInvoices()->count();
            if (!$dryRun) {
                $this->deleteInvoices($enrollment);
                if ($enrollment->isPersonDeleted()) {
                    $this->entityManager->remove($enrollment);
                }
            }
        }

        $guardians = [];
        foreach ($students as $student) {
            ++$result->students;
            $guardian = $student->getGuardian();
            if (null !== $guardian) {
                $guardians[spl_object_id($guardian)] = $guardian;
            }

            foreach ($student->getEnrollments()->toArray() as $enrollment) {
                if (!$enrollment->getInvoices()->isEmpty() && !\in_array($enrollment, $expired, true)) {
                    ++$result->detachedEnrollments;
                    if (!$dryRun) {
                        // orphanRemoval: the lessons are deleted on flush
                        $enrollment->getLessons()->clear();
                        $enrollment->detachStudent();
                    }
                } elseif (!$dryRun) {
                    // The lessons go with it (cascade remove)
                    $this->entityManager->remove($enrollment);
                }
            }

            if (!$dryRun) {
                // The trial lessons go with it (cascade remove)
                $this->entityManager->remove($student);
            }
        }

        foreach ($guardians as $guardian) {
            if ($this->allIn($guardian, $students)) {
                ++$result->guardians;
                if (!$dryRun) {
                    $this->entityManager->remove($guardian);
                }
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }

    private function deleteInvoices(Enrollment $enrollment): void
    {
        foreach ($enrollment->getInvoices() as $invoice) {
            foreach ($invoice->getPayments() as $payment) {
                $this->entityManager->remove($payment);
            }
            $pdf = $this->pdfs->findForInvoice($invoice);
            if (null !== $pdf) {
                $this->entityManager->remove($pdf);
            }
            // Credit notes refer to their invoice; Doctrine orders the deletes by these references
            $this->entityManager->remove($invoice);
        }
        $enrollment->getInvoices()->clear();
    }

    /**
     * @param list<Student> $deleted
     */
    private function allIn(Guardian $guardian, array $deleted): bool
    {
        foreach ($guardian->getStudents() as $student) {
            if (!\in_array($student, $deleted, true)) {
                return false;
            }
        }

        return true;
    }
}
