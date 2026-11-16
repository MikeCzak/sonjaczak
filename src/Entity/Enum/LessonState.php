<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * What the admin shows for a lesson. Not stored: derived from Lesson::$cancelledBy and the time
 * (App\Entity\Lesson::state()). "Held" is not a fact anyone enters, it follows from "not cancelled and over".
 */
enum LessonState: string
{
    case Planned = 'planned';
    case Held = 'held';
    case CancelledByStudent = 'cancelled_by_student';
    case CancelledByTeacher = 'cancelled_by_teacher';

    public function labelKey(): string
    {
        return 'lesson.state.'.$this->value;
    }
}
