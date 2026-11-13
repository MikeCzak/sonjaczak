<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * How an enrollment is billed (datenmodell.md 3.4). For now only per term: one invoice, paid at once or in two
 * instalments. Further values (single lessons, blocks of ten) need no schema change, the column is a string.
 */
enum Billing: string
{
    case Semester = 'semester';

    public function labelKey(): string
    {
        return 'enrollment.billing.'.$this->value;
    }
}
