<?php

declare(strict_types=1);

namespace App\Inquiry;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * General inquiry from the contact page.
 */
final class ContactInquiry extends Inquiry
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    public ?string $message = null;
}
