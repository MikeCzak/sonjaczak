<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Where a course break comes from: entered by Sonja, or suggested by the OpenHolidays API when the course was created.
 */
enum BreakSource: string
{
    case Manual = 'manual';
    case Imported = 'imported';
}
