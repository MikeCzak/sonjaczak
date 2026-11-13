<?php

declare(strict_types=1);

namespace App\Tests;

use App\Theme\Theme;
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
        // Public pages lie outside the admin firewall: no session, no cookie (docs/planung/rollen.md 3)
        self::assertResponseNotHasHeader('Set-Cookie');
        self::assertSelectorExists('html[lang="de"]');
        self::assertSelectorExists('header #main-nav .navbar-nav');
        self::assertSelectorExists('footer a[href="/kontakt#impressum"]');
        // SVG logo; the hidden text gives the link its name
        self::assertSelectorExists('header .navbar-brand svg.sc-logo path');
        self::assertSelectorTextSame('header .navbar-brand .visually-hidden', 'Sonja Czak');

        // A missing translation is rendered as its key, e.g. "nav.home"; this also covers the menu labels,
        // which debug:translation cannot see because they are set in PHP (App\Menu\MenuBuilder)
        $text = $crawler->filter('title')->text().' '.$crawler->filter('body')->text();
        self::assertDoesNotMatchRegularExpression('/\b(site|meta|nav|title|action|placeholder|footer|home|about|booking|booking_solo|booking_band|lessons|contact|imprint|privacy|error|form|inquiry|mail|image|slider|theme|styleguide)\.[a-z_]+/', $text);
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
        self::assertSelectorCount(\count(Theme::cases()), 'a[href^="?theme="]');
    }

    public function testActiveThemeIsSetOnTheRootElement(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');
        self::assertSelectorExists('html[data-theme="classic"]:not([data-bs-theme])');

        // Preview of a dark theme for this page only; unknown values fall back to the active theme
        $client->request('GET', '/?theme=stage');
        self::assertSelectorExists('html[data-theme="stage"][data-bs-theme="dark"]');

        $client->request('GET', '/?theme=gibtsnicht');
        self::assertSelectorExists('html[data-theme="classic"]');
    }

    public function testHomeSliderShowsTheConfiguredSlides(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        // Without "slide" Bootstrap removes the old photo at once and the page background flashes during the fade
        self::assertSelectorExists('#home-slider.carousel.slide.carousel-fade');
        self::assertSelectorCount(2, '#home-slider .carousel-item');
        self::assertSelectorCount(1, '#home-slider .carousel-item.active');
        // Only the first photo loads with high priority; the others at once too, so they never slide in empty
        self::assertSelectorCount(1, '#home-slider img[fetchpriority="high"]');
        self::assertSelectorNotExists('#home-slider img[loading="lazy"]');
        // Controls appear only with JavaScript (assets/slider.js)
        self::assertSelectorExists('#home-slider [data-slider-controls][hidden] [data-slider-pause]');
    }

    public function testHeroImageIsResponsive(): void
    {
        $client = self::createClient();
        $client->request('GET', '/booking/solo');

        // WebP in several widths, JPEG fallback; the first image loads with high priority, not lazily
        self::assertSelectorExists('.sc-hero picture source[type="image/webp"][srcset*="640w"][srcset*="1280w"]');
        self::assertSelectorExists('.sc-hero img[src$=".jpg"][width][height][fetchpriority="high"]');
    }

    public function testOnlyAiGeneratedImagesAreLabelled(): void
    {
        $client = self::createClient();

        $client->request('GET', '/booking/solo');
        self::assertSelectorTextSame('.sc-hero .sc-ai-label', 'KI-generiert');

        $client->request('GET', '/booking/band');
        self::assertSelectorNotExists('.sc-ai-label');
    }

    public function testNotFoundPageUsesLayout(): void
    {
        // Only without debug Symfony renders the error template instead of the developer exception page
        $client = self::createClient(['debug' => false]);
        $client->request('GET', '/diese-seite-gibt-es-nicht');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Dieser Ton wurde leider nicht getroffen.');
        self::assertSelectorExists('header #main-nav .navbar-nav');
        self::assertSelectorExists('meta[name="robots"][content="noindex"]');
    }

    public function testImprintAnchorExists(): void
    {
        $client = self::createClient();
        $client->request('GET', '/kontakt');

        self::assertSelectorExists('#impressum');
        // § 5 Abs. 1 ECG: name, geographic address and e-mail address
        self::assertSelectorTextContains('#impressum address', 'Sonja Riepl');
        self::assertSelectorTextContains('#impressum address', '8130 Frohnleiten');
        self::assertSelectorExists('#impressum address a[href^="mailto:"]');
    }
}
