<?php

declare(strict_types=1);

namespace App\Tests\Course;

use App\Course\HolidayImporter;
use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enum\BreakSource;
use App\Tests\Http\OpenHolidaysResponses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HolidayImporterTest extends TestCase
{
    public function testImportsSchoolHolidaysAndPublicHolidaysOutsideThem(): void
    {
        $requests = [];
        $responses = new OpenHolidaysResponses();
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $responses): MockResponse {
            $requests[] = $url;

            return $responses($method, $url, $options);
        }, 'https://openholidaysapi.org');

        $course = $this->course();
        self::assertSame(7, new HolidayImporter($client, new NullLogger())->importInto($course));

        // Styria as AT-SM, only for school holidays; public holidays are national
        self::assertStringContainsString('subdivisionCode=AT-SM', $requests[0]);
        self::assertStringContainsString('validFrom=2026-10-05&validTo=2027-02-26', $requests[0]);
        self::assertStringNotContainsString('subdivisionCode', $requests[1]);

        self::assertSame([
            '2026-10-26 2026-10-26 Nationalfeiertag',
            '2026-10-27 2026-10-31 Herbstferien',
            '2026-11-01 2026-11-01 Allerheiligen',
            '2026-11-02 2026-11-02 Allerseelen',
            '2026-12-08 2026-12-08 Mariä Empfängnis',
            // Christtag, Stefanitag, Neujahr and Heilige drei Könige lie inside: not a row of their own
            '2026-12-24 2027-01-06 Weihnachtsferien',
            '2027-02-15 2027-02-20 Semesterferien',
        ], $this->rows($course));
        foreach ($course->getBreaks() as $break) {
            self::assertSame(BreakSource::Imported, $break->getSource());
        }
    }

    public function testExistingBreaksAreNotAddedTwice(): void
    {
        $client = new MockHttpClient(new OpenHolidaysResponses(), 'https://openholidaysapi.org');
        $course = $this->course();
        $course->addBreak(new CourseBreak('Herbst', new \DateTimeImmutable('2026-10-27'), new \DateTimeImmutable('2026-10-31')));

        $importer = new HolidayImporter($client, new NullLogger());
        self::assertSame(6, $importer->importInto($course));
        self::assertSame(0, $importer->importInto($course));
        self::assertCount(7, $course->getBreaks());
    }

    public function testUnreachableApiKeepsTheCourseAsItIs(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['http_code' => 503]), new MockResponse('', ['http_code' => 503])]);
        $course = $this->course();

        self::assertNull(new HolidayImporter($client, new NullLogger())->importInto($course));
        self::assertCount(0, $course->getBreaks());
    }

    private function course(): Course
    {
        return new Course()
            ->setStartsOn(new \DateTimeImmutable('2026-10-05'))
            ->setEndsOn(new \DateTimeImmutable('2027-02-26'));
    }

    /**
     * @return list<string>
     */
    private function rows(Course $course): array
    {
        $rows = array_map(
            static fn (CourseBreak $break): string => \sprintf('%s %s %s', $break->getFirstDay()?->format('Y-m-d'), $break->getLastDay()?->format('Y-m-d'), $break->getLabel()),
            $course->getBreaks()->toArray(),
        );
        sort($rows);

        return $rows;
    }
}
