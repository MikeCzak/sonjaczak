<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Content\PageTexts;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * PageTexts::kind() tells the editor what a line break does in a text. It is a list in PHP, the templates decide how
 * the text is really shown: this test renders every public page as an admin and compares both (docs/planung/cms.md K4).
 */
final class PageTextKindTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const array PAGES = ['/', '/ueber-mich', '/booking', '/booking/solo', '/booking/band', '/unterricht', '/kontakt', '/datenschutz', '/datenschutz/unterricht'];

    public function testKindMatchesHowTheTemplatesShowTheText(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        $seen = [];
        foreach (self::PAGES as $page) {
            $crawler = $client->request('GET', $page);
            self::assertResponseIsSuccessful($page);

            foreach ($crawler->filter('[data-page-text]') as $element) {
                \assert($element instanceof \DOMElement);
                $key = $element->getAttribute('data-page-text');
                $seen[$key] = true;

                $actual = match (true) {
                    \in_array($element->tagName, ['ul', 'ol'], true) => PageTexts::KIND_LIST,
                    // text.paragraphs wraps its <p> elements in a <div> carrying the marker
                    'div' === $element->tagName && $element->getElementsByTagName('p')->length > 0 => PageTexts::KIND_PARAGRAPHS,
                    $element->getElementsByTagName('br')->length > 0 => PageTexts::KIND_LINES,
                    default => PageTexts::KIND_LINE,
                };
                // A marker on an image column (about.image_alt) or an empty placeholder says nothing about line breaks
                if ($element->getElementsByTagName('img')->length > 0 || str_contains($element->getAttribute('class'), 'sc-page-text-empty')) {
                    continue;
                }

                self::assertSame($actual, PageTexts::kind($key), \sprintf('%s on %s', $key, $page));
            }
        }

        self::assertGreaterThan(80, \count($seen), 'Only '.\count($seen).' marked texts found');
    }
}
