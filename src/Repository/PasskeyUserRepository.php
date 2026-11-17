<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Tells the WebAuthn bundle which account a passkey belongs to (config/packages/webauthn.yaml). Accounts are not
 * stored twice: the "user entity" of WebAuthn is built from User each time. Name = e-mail address (the identifier of
 * the firewall's user provider, used to load the account after a passkey login), id = User::$webauthnHandle.
 *
 * Deactivated accounts are not found: they cannot add passkeys or log in with one (also checked by
 * App\Security\UserChecker). No CanRegisterUserEntity: accounts are created in the user management only.
 */
final readonly class PasskeyUserRepository implements PublicKeyCredentialUserEntityRepositoryInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        return $this->toUserEntity($this->users->loadUserByIdentifier($username));
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        return $this->toUserEntity($this->users->findOneBy(['webauthnHandle' => $userHandle]));
    }

    private function toUserEntity(?User $user): ?PublicKeyCredentialUserEntity
    {
        if (null === $user || !$user->isActive()) {
            return null;
        }

        return PublicKeyCredentialUserEntity::create($user->getEmail(), $user->getWebauthnHandle(), $user->getEmail());
    }
}
