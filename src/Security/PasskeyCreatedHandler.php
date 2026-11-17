<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Passkey;
use App\Entity\User;
use App\Repository\PasskeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webauthn\Bundle\Security\Handler\SuccessHandler;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialOptions;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Runs after the WebAuthn bundle has checked and saved a new passkey from "Mein Konto" (config/packages/webauthn.yaml,
 * controllers.creation.account). The bundle only passes the request, so the passkey is found by its credential ID
 * from the request body, and gets the name the account holder typed (field passkeyName, added by
 * assets/controllers/passkey_register_controller.js; the bundle ignores fields it does not know).
 */
final readonly class PasskeyCreatedHandler implements SuccessHandler
{
    public function __construct(
        private PasskeyRepository $passkeys,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function onSuccess(
        Request $request,
        ?PublicKeyCredential $publicKeyCredential = null,
        ?PublicKeyCredentialOptions $publicKeyCredentialOptions = null,
        ?PublicKeyCredentialUserEntity $userEntity = null,
    ): Response {
        $data = $request->toArray();
        $name = \is_string($data['passkeyName'] ?? null) ? trim($data['passkeyName']) : '';
        $id = $this->decodeId($data['id'] ?? null);
        $user = $this->security->getUser();

        if ('' !== $name && null !== $id && $user instanceof User) {
            $passkey = $this->passkeys->findOneByCredentialId($id);
            // Only the own one: the bundle created it for the logged-in account, checked here once more
            if ($passkey instanceof Passkey && $passkey->userHandle === $user->getWebauthnHandle()) {
                $passkey->rename($name);
                $this->entityManager->flush();
            }
        }

        return new JsonResponse(['status' => 'ok'], Response::HTTP_CREATED);
    }

    /**
     * The credential ID as the browser sends it: base64url without padding (WebAuthn JSON, also @simplewebauthn).
     */
    private function decodeId(mixed $id): ?string
    {
        try {
            return \is_string($id) ? sodium_base642bin($id, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING) : null;
        } catch (\SodiumException) {
            return null;
        }
    }
}
