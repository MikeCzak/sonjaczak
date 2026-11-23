<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Page "System" (docs/planung/wartung.md W4): only the super admin sees it.
 */
final class SystemTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testAdminWithoutSuperAdminRoleIsDenied(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        $client->request('GET', '/admin/system');
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/admin');
        self::assertSelectorNotExists('#admin-nav a[href="/admin/system"]');
    }

    public function testSuperAdminSeesThePage(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->superAdmin()->create(), 'admin');

        $client->request('GET', '/admin');
        self::assertSelectorExists('#admin-nav a[href="/admin/system"]');

        $client->request('GET', '/admin/system');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'System');
        // PHPUnit runs on the CLI, where OPcache is off by default: the page says so instead of showing zeros
        if (!(bool) \ini_get('opcache.enable_cli')) {
            self::assertSelectorExists('#opcache-off');
        }
    }
}
