<?php

declare(strict_types=1);

namespace App\Tests\Http;

use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Recorded answers of the OpenHolidays API for 2026-10-01 to 2027-02-28 (tests/Fixtures/openholidays, fetched
 * 2026-11-13). Used as mock_response_factory of openholidays.client in the test environment
 * (config/packages/framework.yaml), so no test calls the real API.
 */
final class OpenHolidaysResponses
{
    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        $file = match (parse_url($url, \PHP_URL_PATH)) {
            '/SchoolHolidays' => 'school',
            '/PublicHolidays' => 'public',
            default => null,
        };
        if (null === $file) {
            return new MockResponse('', ['http_code' => 404]);
        }

        $json = (string) file_get_contents(__DIR__.'/../Fixtures/openholidays/'.$file.'-2026-10-01-2027-02-28.json');

        return new JsonMockResponse(json_decode($json, true, flags: \JSON_THROW_ON_ERROR));
    }
}
