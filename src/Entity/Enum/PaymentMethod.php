<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case Cash = 'cash';

    public function labelKey(): string
    {
        return 'payment.method.'.$this->value;
    }
}
