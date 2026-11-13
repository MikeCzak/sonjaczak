<?php

declare(strict_types=1);

namespace App\Invoice;

use App\Entity\Enum\PaymentMethod;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form data for a payment or a refund. The amount is always entered positive; a refund is stored negative.
 * Prefilled with the open amount and today, so recording "paid in full today" is one click.
 */
final class PaymentDraft
{
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(propertyPath: 'maxCents', message: 'payment.too_high')]
    public ?int $amountCents;

    // The date decides the year of the income, so no payments in the future
    #[Assert\NotNull]
    #[Assert\LessThanOrEqual('today', message: 'payment.in_future')]
    public ?\DateTimeImmutable $paidOn;

    public PaymentMethod $method = PaymentMethod::Transfer;

    public function __construct(
        // Open amount (or refund due) as a positive number
        public readonly int $maxCents,
        \DateTimeImmutable $paidOn,
    ) {
        $this->amountCents = $maxCents;
        $this->paidOn = $paidOn;
    }
}
