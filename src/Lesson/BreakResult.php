<?php

declare(strict_types=1);

namespace App\Lesson;

/**
 * What LessonPlanner::applyBreaks() or restoreBreakDays() changed.
 */
final class BreakResult
{
    /**
     * Lessons deleted (in new breaks) or, after a break was deleted, the ones that went at the end.
     */
    public int $removed = 0;

    /**
     * Lessons added at the end or, after a break was deleted, on the freed days.
     */
    public int $added = 0;
}
