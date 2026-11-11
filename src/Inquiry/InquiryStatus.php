<?php

declare(strict_types=1);

namespace App\Inquiry;

use Symfony\Component\HttpFoundation\Response;

/**
 * Outcome of App\Inquiry\InquiryProcessor::process(). The value is part of the translation keys (inquiry.*).
 */
enum InquiryStatus: string
{
    case Sent = 'sent';
    case RateLimited = 'rate_limited';
    case Failed = 'failed';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Sent => Response::HTTP_OK,
            self::RateLimited => Response::HTTP_TOO_MANY_REQUESTS,
            self::Failed => Response::HTTP_SERVICE_UNAVAILABLE,
        };
    }
}
