<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Winter or summer term. Stored as its value; together with the start year it gives the title
 * ("Wintersemester 2026/27") and the short form in invoice file names ("WiSe_2026"), datenmodell.md 3.3.
 */
enum Term: string
{
    case Winter = 'winter';
    case Summer = 'summer';

    /**
     * Label key in the admin domain, e.g. "Wintersemester %year%" with the year already formatted.
     */
    public function labelKey(): string
    {
        return 'course.term.'.$this->value;
    }

    /**
     * Year as shown: the winter term spans two years ("2026/27"), the summer term one ("2027").
     */
    public function yearLabel(int $year): string
    {
        return match ($this) {
            self::Winter => \sprintf('%d/%02d', $year, ($year + 1) % 100),
            self::Summer => (string) $year,
        };
    }

    /**
     * Short form for file names, without translation: it must stay the same in every language.
     */
    public function abbreviation(): string
    {
        return match ($this) {
            self::Winter => 'WiSe',
            self::Summer => 'SoSe',
        };
    }
}
