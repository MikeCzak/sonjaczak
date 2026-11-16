<?php

declare(strict_types=1);

namespace App\Lesson;

use App\Entity\Lesson;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lessons as plain text lines for confirm() popups and flash messages, e.g. "Berta Nachbar, Fr 16.10. 15:30,
 * gehalten (Vorsingen)". Plain text, because confirm() shows no HTML.
 */
final readonly class LessonDescriber
{
    public function __construct(
        private TranslatorInterface $translator,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<Lesson> $lessons
     */
    public function describe(array $lessons): string
    {
        $now = $this->clock->now();

        return implode("\n", array_map(function (Lesson $lesson) use ($now): string {
            $startsAt = $lesson->getStartsAt();

            return \sprintf('%s, %s %s, %s%s',
                $lesson->getEnrollment()->getStudent()?->getFullName() ?? '?',
                null !== $startsAt ? $this->translator->trans('weekday_short.'.$startsAt->format('N'), [], 'admin') : '',
                $startsAt?->format('d.m. H:i') ?? '',
                $this->translator->trans($lesson->state($now)->labelKey(), [], 'admin'),
                null !== $lesson->getNote() ? ' ('.$lesson->getNote().')' : '',
            );
        }, $lessons));
    }
}
