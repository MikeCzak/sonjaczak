<?php

declare(strict_types=1);

namespace App\Inquiry;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Inquiry for singing lessons from /unterricht.
 */
final class LessonsInquiry extends Inquiry
{
    public const int ADULT_AGE = 18;

    // Plausibility only; lessons start at 12, but a parent may ask early
    #[Assert\NotNull]
    #[Assert\Range(min: 5, max: 120)]
    public ?int $age = null;

    /** Name of a parent or guardian, required for students under 18 (see validateGuardian()). */
    #[Assert\Length(max: 100)]
    public ?string $guardian = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public ?string $preferredTime = null;

    #[Assert\Length(max: 2000)]
    public ?string $experience = null;

    #[Assert\Length(max: 5000)]
    public ?string $message = null;

    public function isMinor(): bool
    {
        return null !== $this->age && $this->age < self::ADULT_AGE;
    }

    #[Assert\Callback]
    public function validateGuardian(ExecutionContextInterface $context): void
    {
        if ($this->isMinor() && null === $this->guardian) {
            $context->buildViolation('inquiry.guardian.required')->atPath('guardian')->addViolation();
        }
    }
}
