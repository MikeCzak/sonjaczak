<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * State of a lesson (datenmodell.md 3.5). Who cancelled is a business category, not a login.
 */
enum LessonStatus: string
{
    case Planned = 'planned';
    case Held = 'held';
    // Lost for the student
    case CancelledByStudent = 'cancelled_by_student';
    // Made up if the make-up rule applies (App\Lesson\MakeUpRule)
    case CancelledByTeacher = 'cancelled_by_teacher';

    public function labelKey(): string
    {
        return 'lesson.status.'.$this->value;
    }

    /**
     * Counts towards the planned number of lessons: everything except a cancellation by Sonja.
     */
    public function counts(): bool
    {
        return self::CancelledByTeacher !== $this;
    }
}
