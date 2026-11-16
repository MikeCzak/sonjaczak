<?php

declare(strict_types=1);

namespace App\Lesson;

use App\Entity\Enrollment;

/**
 * When Sonja owes a make-up lesson (docs/planung/datenmodell.md 3.5): all lessons of the enrollment count except
 * cancellations by Sonja; if that number is below the enrollment's planned lessons (the minimum Sonja owes, copied from the course), make-up
 * lessons are due until it is reached. Cancellations by the student count (they are lost), so they never lead to a
 * make-up lesson. Holding more lessons than planned is allowed and simply means nothing is due.
 */
final class MakeUpRule
{
    /**
     * How many make-up lessons are due, 0 if none.
     */
    public function due(Enrollment $enrollment): int
    {
        return max(0, $enrollment->getPlannedLessons() - $enrollment->countedLessons());
    }
}
