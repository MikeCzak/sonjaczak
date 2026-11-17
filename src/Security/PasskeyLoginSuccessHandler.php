<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * After a passkey login (umsetzung-admin.md B5c): the request came from JavaScript, so the answer is JSON with the
 * page to go to, like form_login's redirect: the admin page that asked for the login, otherwise the dashboard.
 */
final readonly class PasskeyLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    private const string FIREWALL = 'admin';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $target = $request->hasSession() ? $this->getTargetPath($request->getSession(), self::FIREWALL) : null;
        if ($request->hasSession()) {
            $this->removeTargetPath($request->getSession(), self::FIREWALL);
        }

        return new JsonResponse(['redirect' => $target ?? $this->urlGenerator->generate('admin_dashboard')]);
    }
}
