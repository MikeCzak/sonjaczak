<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Payment status of an invoice with its credit notes; calculated by Invoice::getStatus(), not stored.
 */
enum InvoiceStatus: string
{
    case Open = 'open';
    case PartlyPaid = 'partly_paid';
    case Paid = 'paid';
    case RefundDue = 'refund_due';

    public function labelKey(): string
    {
        return 'invoice.status.'.$this->value;
    }

    /**
     * Bootstrap colour of the badge.
     */
    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::PartlyPaid => 'warning',
            self::Open => 'secondary',
            self::RefundDue => 'danger',
        };
    }
}
