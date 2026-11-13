<?php

declare(strict_types=1);

namespace App\Lesson;

use App\Entity\Enrollment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * When Sonja owes a make-up lesson (docs/planung/datenmodell.md 3.5): all lessons of the enrollment count except
 * cancellations by Sonja; if that number is below plannedLessons − threshold, a make-up lesson is due.
 * Cancellations by the student count (they are lost), so they never lead to a make-up lesson.
 */
final readonly class MakeUpRule
{
    public function __construct(
        // config/services.yaml, parameter app.make_up_threshold; from step C6 on set by Sonja in the settings
        #[Autowire(param: 'app.make_up_threshold')]
        private int $threshold,
    ) {
    }

    /**
     * How many make-up lessons are due, 0 if none.
     */
    public function due(Enrollment $enrollment): int
    {
        $minimum = $enrollment->getCourse()->getPlannedLessons() - $this->threshold;

        return max(0, $minimum - $enrollment->countedLessons());
    }
}
