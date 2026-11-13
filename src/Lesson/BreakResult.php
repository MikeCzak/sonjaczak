<?php

declare(strict_types=1);

namespace App\Lesson;

use App\Entity\Lesson;

/**
 * What LessonPlanner::applyBreaks() changed.
 */
final class BreakResult
{
    public int $removed = 0;

    public int $added = 0;

    /**
     * Held or cancelled lessons inside a break: not removed, Sonja decides.
     *
     * @var list<Lesson>
     */
    public array $conflicts = [];
}
