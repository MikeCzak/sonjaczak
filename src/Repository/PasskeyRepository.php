<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Passkey;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webauthn\Bundle\Repository\DoctrineCredentialSourceRepository;
use Webauthn\CredentialRecord;

use function Symfony\Component\Clock\now;

/**
 * Passkeys for the WebAuthn bundle (config/packages/webauthn.yaml) and for "Mein Konto". The bundle finds them by
 * credential ID or user handle (parent class) and saves through saveCredentialRecord().
 *
 * @extends DoctrineCredentialSourceRepository<Passkey>
 */
class PasskeyRepository extends DoctrineCredentialSourceRepository
{
    public function __construct(ManagerRegistry $registry, private readonly TranslatorInterface $translator)
    {
        parent::__construct($registry, Passkey::class);
    }

    /**
     * Called by the bundle in two cases (vendor/web-auth/webauthn-symfony-bundle, 5.3.13):
     * - after a login with the stored passkey, whose counter and backup flags were just updated: it was used now;
     * - after a new registration, with a plain CredentialRecord: becomes a Passkey with a name by date.
     */
    #[\Override]
    public function saveCredentialRecord(CredentialRecord $credentialRecord): void
    {
        if ($credentialRecord instanceof Passkey) {
            $credentialRecord->markUsed();
            $passkey = $credentialRecord;
        } else {
            $passkey = Passkey::fromRecord($credentialRecord, $this->translator->trans(
                'account.passkey.default_name',
                ['%date%' => now()->format('d.m.Y')],
                'admin',
            ));
        }

        $this->getEntityManager()->persist($passkey);
        $this->getEntityManager()->flush();
    }

    /**
     * @return list<Passkey> newest first
     */
    public function findForUser(User $user): array
    {
        return $this->findBy(['userHandle' => $user->getWebauthnHandle()], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }
}
