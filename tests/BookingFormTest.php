<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The booking form on /booking/solo. Shared behaviour (CSRF, honeypot, rate limit) is covered by ContactFormTest.
 */
final class BookingFormTest extends WebTestCase
{
    public function testValidInquiryIsMailedWithEventDetails(): void
    {
        $client = $this->openForm();
        $date = new \DateTimeImmutable('+3 months');
        $client->submitForm('Senden', $this->validValues($date));

        self::assertResponseRedirects('/booking/solo?gesendet=1#anfrage', 303);
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailHeaderSame($email, 'Subject', 'Booking-Anfrage (solo) von Erika Muster');
        self::assertEmailHeaderSame($email, 'Reply-To', 'Erika Muster <erika@example.com>');
        self::assertEmailTextBodyContains($email, 'Anlass: Trauung');
        self::assertEmailTextBodyContains($email, 'Datum: '.$date->format('d.m.Y'));
        self::assertEmailTextBodyContains($email, 'Ort: Stift Rein');
        self::assertEmailTextBodyContains($email, 'Telefon: –');
    }

    public function testDateInThePastIsRejected(): void
    {
        $client = $this->openForm();
        $client->submitForm('Senden', ['booking_inquiry[date]' => '2020-06-01'] + $this->validValues(new \DateTimeImmutable('+1 month')));

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
        self::assertSelectorTextContains('#anfrage .invalid-feedback', 'nicht in der Vergangenheit');
    }

    public function testConfirmationReplacesTheForm(): void
    {
        $client = self::createClient();
        $client->request('GET', '/booking/solo?gesendet=1');

        self::assertSelectorTextContains('#anfrage .alert-success', 'Danke für Ihre Nachricht');
        self::assertSelectorNotExists('#anfrage form');
    }

    /**
     * @return array<string, string>
     */
    private function validValues(\DateTimeImmutable $date): array
    {
        return [
            'booking_inquiry[name]' => 'Erika Muster',
            'booking_inquiry[email]' => 'erika@example.com',
            'booking_inquiry[occasion]' => 'wedding',
            'booking_inquiry[date]' => $date->format('Y-m-d'),
            'booking_inquiry[place]' => 'Stift Rein',
            'booking_inquiry[message]' => 'Trauung um 14 Uhr, danach Agape.',
        ];
    }

    private function openForm(): KernelBrowser
    {
        $client = self::createClient();
        $client->followRedirects(false);
        // The rate limiter counts per IP in the filesystem cache, also across tests: start each test at zero
        $client->getContainer()->get('cache.rate_limiter')->clear();
        $client->request('GET', '/booking/solo');

        return $client;
    }
}
