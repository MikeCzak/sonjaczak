<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Every public page renders with the shared layout and without untranslated keys.
 */
final class PublicPagesTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function publicUrls(): iterable
    {
        yield 'home' => ['/'];
        yield 'about' => ['/ueber-mich'];
        yield 'booking' => ['/booking'];
        yield 'booking solo' => ['/booking/solo'];
        yield 'booking band' => ['/booking/band'];
        yield 'lessons' => ['/unterricht'];
        yield 'contact' => ['/kontakt'];
        yield 'privacy' => ['/datenschutz'];
        yield 'style guide' => ['/_design'];
    }

    #[DataProvider('publicUrls')]
    public function testPageRendersWithLayout(string $url): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="de"]');
        self::assertSelectorExists('header #main-nav .navbar-nav');
        self::assertSelectorExists('footer a[href="/kontakt#impressum"]');

        // A missing translation is rendered as its key, e.g. "nav.home"; this also covers the menu labels,
        // which debug:translation cannot see because they are set in PHP (App\Menu\MenuBuilder)
        $text = $crawler->filter('title')->text().' '.$crawler->filter('body')->text();
        self::assertDoesNotMatchRegularExpression('/\b(site|meta|nav|title|action|placeholder|footer|home|about|booking|booking_solo|booking_band|lessons|contact|form|design_preview|styleguide)\.[a-z_]+/', $text);
    }

    public function testCurrentPageIsMarkedInMenu(): void
    {
        $client = self::createClient();
        $client->request('GET', '/booking/solo');

        self::assertSelectorTextSame('#main-nav [aria-current="page"]', 'Solo');
        self::assertSelectorTextSame('#main-nav .nav-link.active', 'Booking');
    }

    public function testStyleGuideIsHiddenFromSearchEngines(): void
    {
        $client = self::createClient();
        $client->request('GET', '/_design');

        self::assertSelectorExists('meta[name="robots"][content="noindex, nofollow"]');
        self::assertSelectorCount(4, '[data-logo-candidate]');
    }

    public function testImprintAnchorExists(): void
    {
        $client = self::createClient();
        $client->request('GET', '/kontakt');

        self::assertSelectorExists('#impressum');
    }
}
