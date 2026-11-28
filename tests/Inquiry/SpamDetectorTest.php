<?php

declare(strict_types=1);

namespace App\Tests\Inquiry;

use App\Inquiry\ContactInquiry;
use App\Inquiry\LessonsInquiry;
use App\Inquiry\SpamDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The phrase list from config/services.yaml against real pitches and ordinary inquiries (docs/planung/spam.md SP4).
 */
final class SpamDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function pitches(): iterable
    {
        // Received 2026-11-27, shortened, addresses left out
        yield 'SEO offer addressing the domain' => ["Hi http://sonjaczak.com,\n\nCould you please tell me if you want to show your website on top in Google searches and to increase organic\ntraffic on your website.\n\nWe are a digital marketing company that deals in SEO."];
        yield 'SEO report' => ['With your permission I would like to send you a SEO Report with prices showing you a few things to greatly improve these search results for you.'];
        yield 'backlinks, upper case' => ['We offer high quality BACKLINKS for your site.'];
    }

    #[DataProvider('pitches')]
    public function testPitchesAreSuspicious(string $message): void
    {
        self::assertTrue($this->detector()->isSuspicious($this->contact($message)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ordinaryMessages(): iterable
    {
        yield 'found via Google' => ['Hallo Sonja, ich habe Sie über Google gefunden und hätte gerne ein Angebot für unsere Trauung im Juni.'];
        yield 'word containing seo' => ['Liebe Grüße von Seong und Museo-Besucher:innen'];
        yield 'English, no pitch' => ['Hi Sonja, we would love you to sing at our wedding in Graz.'];
    }

    #[DataProvider('ordinaryMessages')]
    public function testOrdinaryInquiriesAreNotSuspicious(string $message): void
    {
        self::assertFalse($this->detector()->isSuspicious($this->contact($message)));
    }

    public function testEveryTextFieldIsChecked(): void
    {
        $inquiry = new LessonsInquiry();
        $inquiry->name = 'Max Muster';
        $inquiry->email = 'max@example.com';
        $inquiry->experience = 'Digital Marketing';

        self::assertTrue($this->detector()->isSuspicious($inquiry));
    }

    public function testEmptyListMarksNothing(): void
    {
        self::assertFalse((new SpamDetector([]))->isSuspicious($this->contact('SEO SEO SEO')));
    }

    private function detector(): SpamDetector
    {
        /** @var array{parameters: array{'app.inquiry_spam_phrases': list<string>}} $config */
        $config = Yaml::parseFile(__DIR__.'/../../config/services.yaml', Yaml::PARSE_CUSTOM_TAGS);

        return new SpamDetector($config['parameters']['app.inquiry_spam_phrases']);
    }

    private function contact(string $message): ContactInquiry
    {
        $inquiry = new ContactInquiry();
        $inquiry->name = 'Erika Muster';
        $inquiry->email = 'erika@example.com';
        $inquiry->message = $message;

        return $inquiry;
    }
}
