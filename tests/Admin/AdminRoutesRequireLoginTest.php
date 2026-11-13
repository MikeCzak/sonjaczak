<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Safety net: every route under /admin, including ones added later, sends anonymous visitors to the login.
 * A controller that forgot #[IsGranted] is still covered by access_control; this test notices if that net breaks.
 */
final class AdminRoutesRequireLoginTest extends WebTestCase
{
    /** Routes that must be reachable without login (handled by the firewall itself or public by design) */
    private const array PUBLIC_ROUTES = ['admin_login', 'admin_logout'];

    public function testEveryAdminRouteRequiresLogin(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);

        $checked = 0;
        foreach ($router->getRouteCollection() as $name => $route) {
            if (!str_starts_with($route->getPath(), '/admin') || \in_array($name, self::PUBLIC_ROUTES, true)) {
                continue;
            }

            // Placeholders like {id} get a dummy value; access is checked before the object is loaded
            $path = (string) preg_replace('/\{[^}]+\}/', '1', $route->getPath());
            $methods = $route->getMethods();
            $client->request([] === $methods ? 'GET' : $methods[0], $path);

            self::assertResponseRedirects('/admin/login', null, \sprintf('Route "%s" (%s) is reachable without login.', $name, $path));
            ++$checked;
        }

        self::assertGreaterThan(0, $checked);
    }
}
