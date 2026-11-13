<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * "Passwort vergessen": link per mail, valid once (docs/planung/rollen.md 4.2).
 */
final class PasswordResetTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const string NEW_PASSWORD = 'Geige-Fagott-Triangel-7';

    public function testUnknownAddressGetsTheSameAnswerButNoMail(): void
    {
        $client = self::createClient();
        $this->requestLink($client, 'niemand@example.com');

        self::assertResponseRedirects('/admin/passwort-vergessen/gesendet');
        self::assertEmailCount(0);
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Falls es zu dieser Adresse ein Konto gibt');
    }

    public function testLinkSetsANewPasswordOnce(): void
    {
        $client = self::createClient();
        UserFactory::new()->admin()->create(['email' => 'sonja@example.com']);

        $this->requestLink($client, 'Sonja@Example.com');
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailHeaderSame($email, 'To', 'sonja@example.com');
        self::assertEmailHeaderSame($email, 'Subject', 'Neues Passwort für sonjaczak.com');
        $link = $this->linkFrom((string) $email->getTextBody());

        // The token moves from the URL into the session
        $client->request('GET', $link);
        self::assertResponseRedirects('/admin/passwort-setzen');
        $client->followRedirect();
        $client->submitForm('Passwort speichern', [
            'new_password[newPassword][first]' => self::NEW_PASSWORD,
            'new_password[newPassword][second]' => self::NEW_PASSWORD,
        ]);
        self::assertResponseRedirects('/admin/login');

        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Das Passwort ist gesetzt');
        $client->submitForm('Anmelden', ['email' => 'sonja@example.com', 'password' => self::NEW_PASSWORD]);
        self::assertResponseRedirects('/admin');

        // Used once, the link is gone
        $client->request('GET', $link);
        $client->followRedirect();
        self::assertResponseRedirects('/admin/passwort-vergessen');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Der Link ist abgelaufen');
    }

    public function testWeakPasswordIsRejected(): void
    {
        $client = self::createClient();
        UserFactory::new()->admin()->create(['email' => 'sonja@example.com']);
        $this->requestLink($client, 'sonja@example.com');
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);

        $client->request('GET', $this->linkFrom((string) $email->getTextBody()));
        $client->followRedirect();
        $client->submitForm('Passwort speichern', [
            'new_password[newPassword][first]' => 'sonja12345678',
            'new_password[newPassword][second]' => 'sonja12345678',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'zu leicht zu erraten');
    }

    private function requestLink(KernelBrowser $client, string $email): void
    {
        $client->request('GET', '/admin/passwort-vergessen');
        $client->submitForm('Link anfordern', ['password_request[email]' => $email]);
    }

    private function linkFrom(string $text): string
    {
        self::assertSame(1, preg_match('#/admin/passwort-setzen/\S+#', $text, $match), 'No password link in the mail.');

        return $match[0];
    }
}
