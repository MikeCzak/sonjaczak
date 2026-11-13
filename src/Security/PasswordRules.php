<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Rules for new passwords, used by the console command and later by the password forms (docs/planung/rollen.md 4).
 * No check against leaked passwords (NotCompromisedPassword): decided against, it would call an external service.
 */
final class PasswordRules
{
    public const int MIN_LENGTH = 12;

    /**
     * @return list<Constraint>
     */
    public static function constraints(): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Length(min: self::MIN_LENGTH, minMessage: 'password.too_short'),
            // Estimates how hard the password is to guess; default minimum is "medium"
            new Assert\PasswordStrength(message: 'password.too_weak'),
        ];
    }
}
