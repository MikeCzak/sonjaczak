<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\PageText;
use App\Repository\PageTextRepository;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Page texts changed in the admin area (docs/planung/cms.md K1–K3).
 */
final class PageTextTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testChangedTextReplacesTheTextFromTheRepository(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/admin/texte');
        self::assertSelectorTextContains('#lessons', 'Für wen · Text');
        $client->clickLink('Für wen · Text');
        $client->submitForm('Speichern', ['page_text[body]' => "Für alle ab 10 Jahren.\r\n\r\nAuch für Erwachsene."]);
        self::assertResponseRedirects('/admin/texte#lessons');

        $text = $this->stored('lessons.audience.body');
        self::assertNotNull($text);
        // Browser line breaks are stored as \n, so paragraphs are recognised
        self::assertSame("Für alle ab 10 Jahren.\n\nAuch für Erwachsene.", $text->getBody());

        $client->request('GET', '/unterricht');
        self::assertSelectorTextContains('main', 'Für alle ab 10 Jahren.');
        self::assertSelectorTextNotContains('main', 'Für alle ab 12 Jahren');

        $client->request('GET', '/admin/texte');
        self::assertSelectorTextContains('#lessons .badge', 'geändert am');
    }

    public function testResetGoesBackToTheTextFromTheRepository(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/admin/texte/lessons.audience.body');
        $client->submitForm('Speichern', ['page_text[body]' => 'Neuer Text.']);

        $client->request('GET', '/admin/texte/lessons.audience.body');
        $client->submitForm('Auf ursprünglichen Text zurücksetzen');
        self::assertResponseRedirects('/admin/texte#lessons');

        self::assertNull($this->stored('lessons.audience.body'));
        $client->request('GET', '/unterricht');
        self::assertSelectorTextContains('main', 'Für alle ab 12 Jahren');
    }

    public function testTextEqualToTheDefaultIsNotStored(): void
    {
        $client = $this->loggedInClient();
        $crawler = $client->request('GET', '/admin/texte/lessons.audience.body');
        $default = $crawler->filter('#default-text')->text();

        $client->submitForm('Speichern', ['page_text[body]' => $default]);

        self::assertResponseRedirects();
        self::assertNull($this->stored('lessons.audience.body'));
    }

    public function testEmptyTextHidesTheSection(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/admin/texte/lessons.faq.body');
        $client->submitForm('Speichern', ['page_text[body]' => 'Wie lange dauert eine Einheit? 50 Minuten.']);

        $client->request('GET', '/unterricht');
        self::assertSelectorTextContains('main', 'Häufige Fragen');

        $client->request('GET', '/admin/texte/lessons.faq.body');
        $client->submitForm('Speichern', ['page_text[body]' => '']);

        // Admins keep the section in the page, hidden until edit mode is on (cms.md K4) ...
        $client->request('GET', '/unterricht');
        self::assertAnySelectorTextContains('section.sc-page-text-hidden h2', 'Häufige Fragen');

        // ... visitors do not get it at all
        $client->restart();
        $client->request('GET', '/unterricht');
        self::assertSelectorTextNotContains('main', 'Häufige Fragen');
    }

    public function testPricePlaceholdersComeFromTheSettings(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/unterricht');
        self::assertSelectorTextContains('main', '€ 495,– pro Semester (15 Einheiten, entspricht € 33,– pro Einheit)');

        $client->request('GET', '/admin/einstellungen');
        $client->submitForm('Speichern', ['settings[lessonPriceCents]' => '500,00', 'settings[plannedLessons]' => '16']);

        $client->request('GET', '/unterricht');
        self::assertSelectorTextContains('main', '€ 500,– pro Semester (16 Einheiten, entspricht € 31,25 pro Einheit)');
        self::assertSelectorTextContains('main', 'Ein Semester hat 16 Einheiten');

        // Sonja's own wording keeps the values from the settings
        $client->request('GET', '/admin/texte/lessons.prices.body');
        self::assertSelectorTextContains('#placeholders', '%price%, %lessons%, %per_lesson%');
        $client->submitForm('Speichern', ['page_text[body]' => 'Ein Semester kostet %price%.']);
        $client->request('GET', '/unterricht');
        self::assertSelectorTextContains('main', 'Ein Semester kostet € 500,–.');
    }

    public function testUnknownPlaceholderIsRejected(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/admin/texte/lessons.prices.body');
        $client->submitForm('Speichern', ['page_text[body]' => 'Kostet %preis%.']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', '%preis%');
        self::assertNull($this->stored('lessons.prices.body'));
    }

    public function testPriceFromTheSettingsPrefillsNewCourses(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/admin/einstellungen');
        $client->submitForm('Speichern', ['settings[lessonPriceCents]' => '510,00']);

        $crawler = $client->request('GET', '/admin/kurse/neu');
        self::assertSame('510,00', $crawler->filter('input[name="course[priceCents]"]')->attr('value'));
    }

    public function testUnknownKeyIsNotFound(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/admin/texte/lessons.does_not_exist');

        self::assertResponseStatusCodeSame(404);
    }

    public function testVisitorsGetNoEditingMarkup(): void
    {
        $client = self::createClient();
        $client->request('GET', '/unterricht');

        self::assertSelectorNotExists('[data-page-text]');
        self::assertSelectorNotExists('.sc-page-editor');
        self::assertSelectorNotExists('.sc-page-text-empty');
        // Only the "app" entrypoint, not Stimulus of the admin area
        self::assertStringNotContainsString('admin.js', (string) $client->getResponse()->getContent());
        self::assertResponseNotHasHeader('Set-Cookie');
    }

    public function testAdminsSeeEditingMarkupOnPublicPages(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/unterricht');

        self::assertSelectorExists('.sc-page-editor [data-page-editor-target="switch"]');
        self::assertSelectorExists('p[data-page-text="lessons.audience.body"]');
        self::assertSelectorExists('ul[data-page-text="lessons.content.items"]');
        self::assertSelectorExists('address[data-page-text="lessons.location.address"]');
        // Empty FAQ: hidden section with a placeholder to click in edit mode
        self::assertSelectorExists('section.sc-page-text-hidden [data-page-text="lessons.faq.heading"]');
        self::assertSelectorExists('.sc-page-text-empty[data-page-text="lessons.faq.body"]');

        $client->request('GET', '/datenschutz');
        self::assertSelectorExists('div[data-page-text="privacy.email.body"]');
        self::assertSelectorExists('span[data-page-text="imprint.provider.address"]');
    }

    public function testAdminAreaLinksToTheWebsiteOnlyWhenLoggedIn(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/login');
        self::assertSelectorNotExists('header a[href="/"]');

        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');
        $client->request('GET', '/admin');
        self::assertSelectorTextContains('header a[href="/"]', 'Zur Website');

        // ... and the bar on the public pages links back
        $client->request('GET', '/');
        self::assertSelectorExists('.sc-page-editor a[href="/admin"]');
    }

    public function testEditorLoadsTheRawText(): void
    {
        $client = $this->loggedInClient();
        $client->request('GET', '/admin/texte/lessons.prices.body/daten');

        self::assertResponseIsSuccessful();
        $data = $this->json($client);
        self::assertIsString($data['text']);
        self::assertStringContainsString('%price% pro Semester', $data['text']);
        self::assertSame(['%price%', '%lessons%', '%per_lesson%'], $data['placeholders']);
        self::assertSame('Unterricht › Preise · Text', $data['label']);
        self::assertNull($data['changed']);
    }

    public function testEditorSavesAndResets(): void
    {
        $client = $this->loggedInClient();
        $token = $this->editorToken($client);

        $this->postJson($client, '/admin/texte/lessons.audience.body/speichern', ['text' => "Für alle.\r\n\r\nWirklich."], $token);
        self::assertResponseIsSuccessful();
        self::assertSame("Für alle.\n\nWirklich.", $this->stored('lessons.audience.body')?->getBody());

        $client->request('GET', '/admin/texte/lessons.audience.body/daten');
        $changed = $this->json($client)['changed'];
        self::assertIsString($changed);
        self::assertStringContainsString('geändert am', $changed);

        $this->postJson($client, '/admin/texte/lessons.audience.body/zuruecksetzen.json', [], $token);
        self::assertResponseIsSuccessful();
        self::assertNull($this->stored('lessons.audience.body'));
    }

    public function testEditorRejectsUnknownPlaceholders(): void
    {
        $client = $this->loggedInClient();
        $this->postJson($client, '/admin/texte/lessons.prices.body/speichern', ['text' => 'Kostet %preis%.'], $this->editorToken($client));

        self::assertResponseStatusCodeSame(422);
        $errors = $this->json($client)['errors'];
        self::assertIsArray($errors);
        self::assertIsString($errors[0] ?? null);
        self::assertStringContainsString('%preis%', $errors[0]);
        self::assertNull($this->stored('lessons.prices.body'));
    }

    public function testEditorNeedsTheToken(): void
    {
        $client = $this->loggedInClient();
        $this->postJson($client, '/admin/texte/lessons.audience.body/speichern', ['text' => 'Neu.'], 'wrong');

        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->stored('lessons.audience.body'));
    }

    public function testEditorEndpointsNeedLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/texte/lessons.audience.body/daten');

        self::assertResponseRedirects('/admin/login');
    }

    /**
     * Session token from the bar on a public page, as the Stimulus controller reads it.
     */
    private function editorToken(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/unterricht');

        return (string) $crawler->filter('.sc-page-editor')->attr('data-page-editor-token-value');
    }

    /**
     * @param array<string, string> $body
     */
    private function postJson(KernelBrowser $client, string $url, array $body, string $token): void
    {
        $client->request('POST', $url, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $token,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<mixed>
     */
    private function json(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private function stored(string $key): ?PageText
    {
        return self::getContainer()->get(PageTextRepository::class)->findOneByKey($key);
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        return $client;
    }
}
