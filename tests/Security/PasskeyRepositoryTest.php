<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Passkey;
use App\Repository\PasskeyRepository;
use App\Repository\PasskeyUserRepository;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Storage of passkeys for the WebAuthn bundle (umsetzung-admin.md B5a). The signature checks themselves are the
 * bundle's; here only what we wrote: which account a passkey belongs to and how it is saved.
 */
final class PasskeyRepositoryTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    public function testAccountsAreFoundByEmailAndRandomHandle(): void
    {
        $user = UserFactory::new()->admin()->create(['email' => 'sonja@example.com']);
        $other = UserFactory::createOne();
        $repository = self::getContainer()->get(PasskeyUserRepository::class);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $user->getWebauthnHandle());
        self::assertNotSame($user->getWebauthnHandle(), $other->getWebauthnHandle());

        $entity = $repository->findOneByUsername('Sonja@Example.com');
        self::assertNotNull($entity);
        self::assertSame('sonja@example.com', $entity->name);
        self::assertSame($user->getWebauthnHandle(), $entity->id);
        self::assertSame('sonja@example.com', $repository->findOneByUserHandle($user->getWebauthnHandle())?->name);
        self::assertNull($repository->findOneByUserHandle(str_repeat('0', 64)));
    }

    public function testDeactivatedAccountsAreNotFound(): void
    {
        $user = UserFactory::new()->admin()->create(['email' => 'alt@example.com']);
        $user->deactivate();
        $repository = self::getContainer()->get(PasskeyUserRepository::class);

        self::assertNull($repository->findOneByUsername('alt@example.com'));
        self::assertNull($repository->findOneByUserHandle($user->getWebauthnHandle()));
    }

    public function testNewPasskeyGetsANameAndALoginMarksItUsed(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-11-17 10:00'));
        $user = UserFactory::new()->admin()->create();
        $repository = self::getContainer()->get(PasskeyRepository::class);

        // What the bundle hands over after a registration: a plain record, not our entity
        $repository->saveCredentialRecord(new CredentialRecord(
            "\x01\x02credential",
            'public-key',
            ['internal', 'hybrid'],
            'none',
            EmptyTrustPath::create(),
            Uuid::fromString('fbfc3007-154e-4ecc-8c0b-6e020557d7bd'),
            "\xa5public key",
            $user->getWebauthnHandle(),
            0,
            backupEligible: true,
            backupStatus: true,
        ));

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        [$passkey] = $repository->findForUser($user);
        self::assertSame('Passkey vom 17.11.2026', $passkey->getName());
        self::assertEquals(new \DateTimeImmutable('2026-11-17 10:00'), $passkey->getCreatedAt());
        self::assertNull($passkey->getLastUsedAt());
        // Binary values and the AAGUID survive the round trip through the bundle's column types
        self::assertSame("\x01\x02credential", $passkey->publicKeyCredentialId);
        self::assertSame('fbfc3007-154e-4ecc-8c0b-6e020557d7bd', $passkey->aaguid->toRfc4122());
        self::assertSame(['internal', 'hybrid'], $passkey->transports);

        // The bundle finds it by the raw credential ID, updates the counter and saves it again after a login
        $found = $repository->findOneByCredentialId("\x01\x02credential");
        self::assertInstanceOf(Passkey::class, $found);
        self::mockTime(new \DateTimeImmutable('2026-11-20 08:15'));
        $found->counter = 1;
        $repository->saveCredentialRecord($found);
        self::assertEquals(new \DateTimeImmutable('2026-11-20 08:15'), $found->getLastUsedAt());
        self::assertCount(1, $repository->findForUser($user));
        self::assertSame([], $repository->findForUser(UserFactory::createOne()));
    }
}
