<?php

declare(strict_types=1);

namespace App\Privacy;

/**
 * What one run of PersonalDataPurger deleted (or would delete in a dry run). Only counts: the output goes to cron.
 */
final class PurgeResult
{
    public int $students = 0;

    /**
     * Enrollments kept without a person because their invoices must still be kept.
     */
    public int $detachedEnrollments = 0;

    /**
     * Invoices and credit notes past the seven years, with their payments and PDFs.
     */
    public int $invoices = 0;

    public int $guardians = 0;

    public function isEmpty(): bool
    {
        return 0 === $this->students + $this->detachedEnrollments + $this->invoices + $this->guardians;
    }
}
