<?php

declare(strict_types=1);

namespace App\Inquiry;

/**
 * Occasions for a solo booking. Labels: form.choice.occasion.<value> (messages).
 *
 * Fixed keys instead of the list on the page (content booking_solo.occasions.items): that list is prose and will
 * be editable later, the choices must stay stable.
 */
enum Occasion: string
{
    case Wedding = 'wedding';
    case Baptism = 'baptism';
    case Agape = 'agape';
    case Corporate = 'corporate';
    case Birthday = 'birthday';
    case Other = 'other';

    public function labelKey(): string
    {
        return 'form.choice.occasion.'.$this->value;
    }
}
