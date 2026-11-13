<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Login to the admin area (docs/planung/rollen.md 3–4).
 */
final class LoginTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testLoginPageSetsNoCookie(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, nofollow"]');
        // Lazy firewall and stateless CSRF token: no session until the form is sent
        self::assertResponseNotHasHeader('Set-Cookie');
    }

    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        self::assertResponseRedirects('/admin/login');
    }

    public function testAdminLogsInWithAnyCaseOfTheEmailAddress(): void
    {
        $client = self::createClient();
        UserFactory::new()->admin()->create(['email' => 'sonja@example.com']);

        $client->request('GET', '/admin/login');
        $client->submitForm('Anmelden', ['email' => 'Sonja@Example.com', 'password' => UserFactory::PASSWORD]);

        self::assertResponseRedirects('/admin');
        // The session cookie is only sent back to /admin (framework.session.cookie_path). In tests the session
        // storage is a mock (framework.yaml, when@test) whose cookie is called MOCKSESSID instead of PHPSESSID.
        $cookie = $client->getCookieJar()->get('MOCKSESSID', '/admin');
        self::assertNotNull($cookie);
        self::assertSame('/admin', $cookie->getPath());
        self::assertTrue($cookie->isHttpOnly());

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Übersicht');
        self::assertSelectorTextContains('header', 'sonja@example.com');
    }

    public function testWrongPasswordIsRejected(): void
    {
        $client = self::createClient();
        UserFactory::new()->admin()->create(['email' => 'sonja@example.com']);

        $client->request('GET', '/admin/login');
        $client->submitForm('Anmelden', ['email' => 'sonja@example.com', 'password' => 'falsch']);
        $client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Fehlerhafte Zugangsdaten');
        // The address stays in the field
        self::assertInputValueSame('email', 'sonja@example.com');
    }

    public function testAccountWithoutAdminRoleIsDenied(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne(), 'admin');
        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminLogsOut(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        $client->request('GET', '/admin');
        $client->submitForm('Abmelden');
        self::assertResponseRedirects('/admin/login');

        $client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/login');
    }
}
