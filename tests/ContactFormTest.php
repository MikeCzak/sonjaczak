<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The contact form mails valid inquiries to Sonja and works without session or cookies.
 */
final class ContactFormTest extends WebTestCase
{
    private const array VALID = [
        'contact_inquiry[name]' => 'Erika Muster',
        'contact_inquiry[email]' => 'erika@example.com',
        'contact_inquiry[phone]' => '+43 660 1234567',
        'contact_inquiry[message]' => 'Hallo <Sonja> & Team',
    ];

    public function testFormIsShownWithoutSettingCookies(): void
    {
        $client = self::createClient();
        $client->request('GET', '/kontakt');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#anfrage form[name="contact_inquiry"]');
        self::assertResponseNotHasHeader('Set-Cookie');
    }

    public function testValidInquiryIsMailedToSonja(): void
    {
        $client = $this->openForm();
        $client->submitForm('Senden', self::VALID);

        // Do not follow the redirect: the mail is only visible to the assertions of this request
        self::assertResponseRedirects('/kontakt?gesendet=1#anfrage', 303);
        self::assertResponseNotHasHeader('Set-Cookie');
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertNotNull($email);
        // The display name is quoted because of the dot (RFC 5322)
        self::assertEmailHeaderSame($email, 'From', '"Website sonjaczak.com" <anfrage@sonjaczak.com>');
        self::assertEmailHeaderSame($email, 'To', 'music@sonjaczak.com');
        self::assertEmailHeaderSame($email, 'Reply-To', 'Erika Muster <erika@example.com>');
        self::assertEmailHeaderSame($email, 'Subject', 'Kontaktanfrage von Erika Muster');
        // Plain text mail: the message arrives as typed, not HTML-escaped
        self::assertEmailTextBodyContains($email, 'Hallo <Sonja> & Team');
        self::assertEmailTextBodyContains($email, '+43 660 1234567');
    }

    public function testConfirmationReplacesTheForm(): void
    {
        $client = self::createClient();
        $client->request('GET', '/kontakt?gesendet=1');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#anfrage .alert-success', 'Danke für Ihre Nachricht');
        self::assertSelectorNotExists('#anfrage form');
    }

    public function testInvalidInquiryIsNotSent(): void
    {
        $client = $this->openForm();
        $client->submitForm('Senden', [
            'contact_inquiry[name]' => '',
            'contact_inquiry[email]' => 'keine-adresse',
            'contact_inquiry[phone]' => 'ruf mich an',
            'contact_inquiry[message]' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
        self::assertSelectorCount(4, '#anfrage .invalid-feedback');
        // Entered values stay in the form
        self::assertInputValueSame('contact_inquiry[email]', 'keine-adresse');
    }

    public function testHoneypotPretendsSuccessButSendsNothing(): void
    {
        $client = $this->openForm();
        $client->submitForm('Senden', self::VALID + ['contact_inquiry[website]' => 'https://spam.example']);

        self::assertResponseRedirects('/kontakt?gesendet=1#anfrage', 303);
        self::assertEmailCount(0);
    }

    public function testSubmissionFromForeignSiteIsRejected(): void
    {
        $client = $this->openForm();
        $form = $client->getCrawler()->selectButton('Senden')->form(self::VALID);
        // Stateless CSRF check: the browser reports where the form was submitted from
        $client->request('POST', '/kontakt', $form->getPhpValues(), [], [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_REFERER' => 'https://evil.example/',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
    }

    public function testTooManyInquiriesAreRateLimited(): void
    {
        $client = $this->openForm();

        for ($i = 0; $i < 5; ++$i) {
            $client->request('GET', '/kontakt');
            $client->submitForm('Senden', self::VALID);
            self::assertResponseRedirects();
        }

        $client->request('GET', '/kontakt');
        $client->submitForm('Senden', self::VALID);

        self::assertResponseStatusCodeSame(429);
        self::assertSelectorTextContains('#anfrage .alert-danger', 'music@sonjaczak.com');
        self::assertInputValueSame('contact_inquiry[name]', 'Erika Muster');
    }

    private function openForm(): KernelBrowser
    {
        $client = self::createClient();
        $client->followRedirects(false);
        // The rate limiter counts per IP in the filesystem cache, also across tests: start each test at zero
        $client->getContainer()->get('cache.rate_limiter')->clear();
        $client->request('GET', '/kontakt');

        return $client;
    }
}
