<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Stops deactivated accounts and accounts without password (invitation not used yet) at login
 * (firewall option user_checker in config/packages/security.yaml). Runs after the account is found and before the
 * password is checked. An empty hash would never match anyway; this makes it explicit.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && (!$user->isActive() || '' === $user->getPassword())) {
            // Same text as for a wrong password, so nobody learns that this address has a (deactivated) account
            throw new CustomUserMessageAccountStatusException('Invalid credentials.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
