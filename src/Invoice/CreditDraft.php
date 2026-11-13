<?php

declare(strict_types=1);

namespace App\Invoice;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form data for a credit note. The amount is entered as a positive number and stored negative.
 */
final class CreditDraft
{
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(propertyPath: 'maxCents', message: 'invoice.credit_too_high')]
    public ?int $amountCents = null;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $issuedOn;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $description;

    public function __construct(
        string $description,
        \DateTimeImmutable $issuedOn,
        // What can still be credited; compared with $amountCents
        public readonly int $maxCents,
    ) {
        $this->description = $description;
        $this->issuedOn = $issuedOn;
    }
}
