<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Enum\LessonState;
use App\Entity\Lesson;
use Psr\Clock\ClockInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * lesson_state(lesson) in Twig: the derived state (planned, held, cancelled) at the current time. The clock is
 * injected so tests can fix the time (MockClock) instead of depending on today.
 */
final readonly class LessonStateExtension
{
    public function __construct(private ClockInterface $clock)
    {
    }

    #[AsTwigFunction('lesson_state')]
    public function state(Lesson $lesson): LessonState
    {
        return $lesson->state($this->clock->now());
    }
}
