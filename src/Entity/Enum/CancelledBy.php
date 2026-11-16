<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Who cancelled a lesson (datenmodell.md 3.5, changed 2026-11-16). Stored on the lesson, NULL means not cancelled:
 * a lesson that exists is planned, there is no flag for that. Who cancelled is a business category, not a login.
 */
enum CancelledBy: string
{
    // Lost for the student
    case Student = 'student';
    // Made up if the make-up rule applies (App\Lesson\MakeUpRule)
    case Teacher = 'teacher';

    public function labelKey(): string
    {
        return 'lesson.cancelled_by.'.$this->value;
    }
}
