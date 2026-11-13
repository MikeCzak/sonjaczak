<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * "Mein Konto": change the own password.
 */
final class AccountTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const string NEW_PASSWORD = 'Geige-Fagott-Triangel-7';

    public function testPasswordChangeNeedsTheCurrentPassword(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        $client->request('GET', '/admin/konto');
        $client->submitForm('Passwort ändern', [
            'change_password[currentPassword]' => 'falsch',
            'change_password[newPassword][first]' => self::NEW_PASSWORD,
            'change_password[newPassword][second]' => self::NEW_PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Das aktuelle Passwort stimmt nicht');
    }

    public function testPasswordIsChanged(): void
    {
        $client = self::createClient();
        $user = UserFactory::new()->admin()->create();
        $client->loginUser($user, 'admin');

        $client->request('GET', '/admin/konto');
        $client->submitForm('Passwort ändern', [
            'change_password[currentPassword]' => UserFactory::PASSWORD,
            'change_password[newPassword][first]' => self::NEW_PASSWORD,
            'change_password[newPassword][second]' => self::NEW_PASSWORD,
        ]);

        self::assertResponseRedirects('/admin/konto');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Das Passwort ist geändert');
    }
}
