<?php

declare(strict_types=1);

namespace App\Lesson;

use App\Entity\Enrollment;

/**
 * When Sonja owes a make-up lesson (docs/planung/datenmodell.md 3.5): all lessons of the enrollment count except
 * cancellations by Sonja; if that number is below plannedLessons − threshold, a make-up lesson is due.
 * Cancellations by the student count (they are lost), so they never lead to a make-up lesson.
 *
 * The threshold is passed in (App\Entity\Settings::getMakeUpThreshold()) instead of injected: the service lives
 * across requests in FrankenPHP's worker mode and must not keep an old value.
 */
final class MakeUpRule
{
    /**
     * How many make-up lessons are due, 0 if none.
     */
    public function due(Enrollment $enrollment, int $threshold): int
    {
        $minimum = $enrollment->getCourse()->getPlannedLessons() - $threshold;

        return max(0, $minimum - $enrollment->countedLessons());
    }
}
