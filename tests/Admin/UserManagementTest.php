<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * User management (step B4): only ROLE_SUPER_ADMIN, invitation instead of password, never the own access.
 */
final class UserManagementTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testAdminWithoutSuperAdminRoleIsDenied(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        $client->request('GET', '/admin/benutzer');
        self::assertResponseStatusCodeSame(403);

        // The menu does not offer what is denied
        $client->request('GET', '/admin');
        self::assertSelectorNotExists('#admin-nav a[href="/admin/benutzer"]');
    }

    public function testNewAccountGetsAnInvitationAndCannotLogInBeforeSettingAPassword(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->superAdmin()->create(), 'admin');

        $client->request('GET', '/admin/benutzer/neu');
        $client->submitForm('Anlegen und einladen', [
            'user[email]' => 'Sonja@Example.com',
            'user[role]' => User::ROLE_ADMIN,
        ]);

        self::assertResponseRedirects('/admin/benutzer');
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailHeaderSame($email, 'To', 'sonja@example.com');
        self::assertEmailHeaderSame($email, 'Subject', 'Dein Zugang zu sonjaczak.com');
        self::assertEmailTextBodyContains($email, 'Der Link läuft in 3 Tagen ab');

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'sonja@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame(User::ROLE_ADMIN, $user->getAdminRole());
        self::assertSame('', $user->getPassword());

        $client->followRedirect();
        self::assertSelectorTextContains('table', 'eingeladen, Passwort noch nicht gesetzt');
    }

    public function testDuplicateAddressIsRejected(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->superAdmin()->create(), 'admin');
        UserFactory::createOne(['email' => 'sonja@example.com']);

        $client->request('GET', '/admin/benutzer/neu');
        $client->submitForm('Anlegen und einladen', ['user[email]' => 'sonja@example.com']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'bereits ein Konto');
    }

    public function testDeactivatedAccountCannotLogIn(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->superAdmin()->create(), 'admin');
        UserFactory::new()->admin()->create(['email' => 'sonja@example.com']);

        $client->request('GET', '/admin/benutzer');
        $client->submitForm('Deaktivieren');
        self::assertResponseRedirects('/admin/benutzer');
        $client->followRedirect();
        self::assertSelectorTextContains('table', 'deaktiviert');

        $client->submitForm('Abmelden');
        $client->request('GET', '/admin/login');
        $client->submitForm('Anmelden', ['email' => 'sonja@example.com', 'password' => UserFactory::PASSWORD]);
        $client->followRedirect();
        // Same message as for a wrong password
        self::assertSelectorTextContains('.alert-danger', 'Fehlerhafte Zugangsdaten');
    }

    public function testOwnAccessCannotBeChanged(): void
    {
        $client = self::createClient();
        $me = UserFactory::new()->superAdmin()->create();
        $client->loginUser($me, 'admin');

        $client->request('GET', '/admin/benutzer/'.$me->getId());
        self::assertSelectorExists('select[name="user[role]"][disabled]');

        // Not even with a crafted request: the voter denies it
        $client->request('POST', '/admin/benutzer/'.$me->getId().'/deaktivieren');
        self::assertResponseStatusCodeSame(403);
    }
}
