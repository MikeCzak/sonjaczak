<?php

declare(strict_types=1);

namespace App\Course;

use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enum\BreakSource;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Suggests breaks for a course from the OpenHolidays API: school holidays and public holidays in Styria
 * (docs/planung/datenmodell.md 3.3). Called once when a course is created, not on every page view.
 *
 * Only country, region and dates are sent, no personal data. If the API fails, the course is kept and Sonja enters
 * the breaks herself.
 */
final readonly class HolidayImporter
{
    /**
     * Styria in OpenHolidays. Not the ISO code AT-ST: with that, regional entries are missing without any error.
     */
    public const string SUBDIVISION = 'AT-SM';

    public function __construct(
        // Scoped client "openholidays.client" (config/packages/framework.yaml): base URI, timeout, Accept header
        private HttpClientInterface $openholidaysClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Adds the suggested breaks to the course (not flushed). Days the course already has a break for are skipped.
     *
     * @return int|null number of added breaks, null if the API could not be reached or answered nonsense
     */
    public function importInto(Course $course): ?int
    {
        $from = $course->getStartsOn();
        $to = $course->getEndsOn();
        if (null === $from || null === $to) {
            return 0;
        }

        $query = [
            'countryIsoCode' => 'AT',
            'languageIsoCode' => 'DE',
            'validFrom' => $from->format('Y-m-d'),
            'validTo' => $to->format('Y-m-d'),
        ];

        // Both requests start before the first result is read, so they run in parallel
        $responses = [
            $this->openholidaysClient->request('GET', '/SchoolHolidays', ['query' => $query + ['subdivisionCode' => self::SUBDIVISION]]),
            $this->openholidaysClient->request('GET', '/PublicHolidays', ['query' => $query]),
        ];

        try {
            $school = $this->parse($responses[0]->toArray());
            $public = $this->parse($responses[1]->toArray());
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('OpenHolidays API not available: {message}', ['message' => $exception->getMessage()]);
            // A response that was never read checks its status code when it is destroyed and throws then, somewhere
            // later in the request (TransportResponseTrait::doDestruct()). Cancelled, it stays quiet.
            foreach ($responses as $response) {
                $response->cancel();
            }

            return null;
        }

        // Christtag, Neujahr … lie inside the Christmas holidays: one row is enough
        $public = array_filter($public, static function (CourseBreak $holiday) use ($school): bool {
            foreach ($school as $holidays) {
                if ($holiday->isWithin($holidays)) {
                    return false;
                }
            }

            return true;
        });

        $added = 0;
        foreach ([...$school, ...$public] as $break) {
            if (!$this->alreadyCovered($course, $break)) {
                $course->addBreak($break);
                ++$added;
            }
        }

        return $added;
    }

    /**
     * @param array<mixed> $entries
     *
     * @return list<CourseBreak>
     */
    private function parse(array $entries): array
    {
        $breaks = [];
        foreach ($entries as $entry) {
            if (!\is_array($entry) || !\is_string($entry['startDate'] ?? null) || !\is_string($entry['endDate'] ?? null)) {
                continue;
            }
            $first = \DateTimeImmutable::createFromFormat('!Y-m-d', $entry['startDate']);
            $last = \DateTimeImmutable::createFromFormat('!Y-m-d', $entry['endDate']);
            $label = $this->germanName($entry['name'] ?? null);
            if (false === $first || false === $last || null === $label) {
                continue;
            }
            $breaks[] = new CourseBreak(mb_substr($label, 0, 100), $first, $last, BreakSource::Imported);
        }

        return $breaks;
    }

    private function germanName(mixed $names): ?string
    {
        if (!\is_array($names)) {
            return null;
        }
        foreach ($names as $name) {
            if (\is_array($name) && 'DE' === ($name['language'] ?? null) && \is_string($name['text'] ?? null)) {
                return $name['text'];
            }
        }

        return null;
    }

    private function alreadyCovered(Course $course, CourseBreak $break): bool
    {
        foreach ($course->getBreaks() as $existing) {
            if ($existing->getFirstDay() == $break->getFirstDay() && $existing->getLastDay() == $break->getLastDay()) {
                return true;
            }
        }

        return false;
    }
}
