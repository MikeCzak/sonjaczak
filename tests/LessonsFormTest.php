<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The lessons form on /unterricht ("Du"), with the guardian rule for students under 18.
 * Shared behaviour (CSRF, honeypot, rate limit) is covered by ContactFormTest.
 */
final class LessonsFormTest extends WebTestCase
{
    private const array VALID = [
        'lessons_inquiry[name]' => 'Max Muster',
        'lessons_inquiry[email]' => 'max@example.com',
        'lessons_inquiry[age]' => '25',
        'lessons_inquiry[preferredTime]' => 'ab 16 Uhr',
    ];

    public function testAdultInquiryIsMailedWithoutGuardian(): void
    {
        $client = $this->openForm();
        // A guardian entered before changing the age to 18+ is not mailed
        $client->submitForm('Senden', self::VALID + ['lessons_inquiry[guardian]' => 'Erika Muster']);

        self::assertResponseRedirects('/unterricht?gesendet=1#anfrage', 303);
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailHeaderSame($email, 'Subject', 'Unterrichtsanfrage von Max Muster');
        self::assertEmailTextBodyContains($email, 'Alter: 25');
        self::assertEmailTextBodyContains($email, 'Gewünschte Uhrzeit: ab 16 Uhr');
        self::assertEmailTextBodyNotContains($email, 'Erziehungsberechtigte');
    }

    public function testMinorNeedsGuardian(): void
    {
        $client = $this->openForm();
        $client->submitForm('Senden', ['lessons_inquiry[age]' => '14'] + self::VALID);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
        self::assertSelectorTextContains('[data-guardian-field] .invalid-feedback', 'erziehungsberechtigten Person');
    }

    public function testMinorWithGuardianIsMailed(): void
    {
        $client = $this->openForm();
        $client->submitForm('Senden', ['lessons_inquiry[age]' => '14', 'lessons_inquiry[guardian]' => 'Erika Muster'] + self::VALID);

        self::assertResponseRedirects('/unterricht?gesendet=1#anfrage', 303);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailTextBodyContains($email, 'Erziehungsberechtigte Person: Erika Muster');
    }

    public function testConfirmationAddressesVisitorInformally(): void
    {
        $client = self::createClient();
        $client->request('GET', '/unterricht?gesendet=1');

        self::assertSelectorTextContains('#anfrage .alert-success', 'Danke für deine Nachricht');
    }

    private function openForm(): KernelBrowser
    {
        $client = self::createClient();
        $client->followRedirects(false);
        // The rate limiter counts per IP in the filesystem cache, also across tests: start each test at zero
        $client->getContainer()->get('cache.rate_limiter')->clear();
        $client->request('GET', '/unterricht');

        return $client;
    }
}
