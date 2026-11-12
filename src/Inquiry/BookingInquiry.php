<?php

declare(strict_types=1);

namespace App\Inquiry;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inquiry for Sonja solo (wedding, baptism, …) from /booking/solo.
 */
final class BookingInquiry extends Inquiry
{
    #[Assert\NotNull]
    public ?Occasion $occasion = null;

    // "today" in the PHP timezone (UTC, frankenphp/conf.d/10-app.ini); close enough for a day in Austria
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual('today', message: 'inquiry.date.past')]
    public ?\DateTimeImmutable $date = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    public ?string $place = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    public ?string $message = null;
}
