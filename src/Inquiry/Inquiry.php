<?php

declare(strict_types=1);

namespace App\Inquiry;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fields shared by all inquiry forms. Not an entity: inquiries are only mailed, never stored.
 */
abstract class Inquiry
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    // Strict mode validates like the Mailer does for the Reply-To address
    #[Assert\NotBlank]
    #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT)]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 30)]
    #[Assert\Regex('/^[0-9 +\/()-]+$/', message: 'inquiry.phone.invalid')]
    public ?string $phone = null;

    /** Honeypot: hidden from people, so only bots fill it in (see App\Form\HoneypotType). */
    public ?string $website = null;
}
