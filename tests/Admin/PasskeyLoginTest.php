<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Passkey;
use App\Entity\User;
use App\Tests\Factory\UserFactory;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\UnsignedIntegerObject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\save;

/**
 * Login with a passkey (umsetzung-admin.md B5c). The test plays the device: it holds a real P-256 key pair and
 * signs the server's challenge like an authenticator would (WebAuthn assertion: signature over authenticator data and
 * the hash of the client data). So the whole check of the library runs: origin, relying party, user verification,
 * counter, signature; and our part: finding the account, deactivated accounts, "zuletzt genutzt", redirect.
 */
final class PasskeyLoginTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const string CREDENTIAL_ID = "\x07passkey-macbook";

    public function testLoginPageOffersPasskey(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/login');

        self::assertSelectorTextContains('#passkey-login button', 'Mit Passkey anmelden');
        self::assertSelectorExists('#passkey-login[data-passkey-login-options-url-value="/admin/login/passkey/optionen"]');
        // The login page itself still starts no session; the passkey options do (challenge in the session)
        self::assertNull($client->getCookieJar()->get('PHPSESSID', '/admin'));
    }

    public function testLoginWithPasskey(): void
    {
        $client = self::createClient();
        $user = UserFactory::new()->admin()->create(['email' => 'sonja@example.com']);
        $key = $this->registerPasskey($user);

        // Asked for /admin/kurse first: after the login it goes there, like with the password
        $client->request('GET', 'https://localhost/admin/kurse');
        self::assertResponseRedirects('https://localhost/admin/login');

        $options = $this->options($client);
        self::assertSame('localhost', $options['rpId']);
        self::assertSame('required', $options['userVerification']);
        self::assertSame([], $options['allowCredentials'] ?? []);

        $client->jsonRequest('POST', 'https://localhost/admin/login/passkey', $this->assertion($key, $options['challenge'], $user));
        self::assertResponseIsSuccessful();
        self::assertSame(['redirect' => 'https://localhost/admin/kurse'], json_decode((string) $client->getResponse()->getContent(), true));

        $client->request('GET', 'https://localhost/admin/konto');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#passkeys', 'zuletzt genutzt');

        $passkey = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Passkey::class)->findOneBy([]);
        self::assertSame(1, $passkey?->counter);
    }

    public function testWrongSignatureIsRefused(): void
    {
        $client = self::createClient();
        $user = UserFactory::new()->admin()->create();
        $this->registerPasskey($user);

        // Signed with another key: the stored public key does not fit
        $options = $this->options($client);
        $client->jsonRequest('POST', 'https://localhost/admin/login/passkey', $this->assertion($this->newKey(), $options['challenge'], $user));
        self::assertResponseStatusCodeSame(401);

        $client->request('GET', 'https://localhost/admin');
        self::assertResponseRedirects('https://localhost/admin/login');
    }

    public function testDeactivatedAccountIsRefused(): void
    {
        $client = self::createClient();
        $user = UserFactory::new()->admin()->create();
        $key = $this->registerPasskey($user);
        save($user->deactivate());

        $options = $this->options($client);
        $client->jsonRequest('POST', 'https://localhost/admin/login/passkey', $this->assertion($key, $options['challenge'], $user));
        self::assertResponseStatusCodeSame(401);

        $client->request('GET', 'https://localhost/admin');
        self::assertResponseRedirects('https://localhost/admin/login');
    }

    /**
     * @return array{challenge: string, rpId: string, userVerification: string, allowCredentials?: list<mixed>}
     */
    private function options(KernelBrowser $client): array
    {
        $client->jsonRequest('POST', 'https://localhost/admin/login/passkey/optionen', []);
        self::assertResponseIsSuccessful();

        /** @var array{challenge: string, rpId: string, userVerification: string, allowCredentials?: list<mixed>} */
        return json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Stores the public half of a new key pair as the account's passkey, as after "Passkey hinzufügen".
     */
    private function registerPasskey(User $user): \OpenSSLAsymmetricKey
    {
        $key = $this->newKey();
        /** @var array{ec: array{x: string, y: string}} $details */
        $details = openssl_pkey_get_details($key);
        // COSE key (RFC 9053): kty 2 = EC2, alg -7 = ES256, crv 1 = P-256, x and y
        $coseKey = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($details['ec']['x']))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($details['ec']['y']));

        $passkey = Passkey::fromRecord(new CredentialRecord(
            self::CREDENTIAL_ID,
            'public-key',
            ['internal'],
            'none',
            EmptyTrustPath::create(),
            Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            (string) $coseKey,
            $user->getWebauthnHandle(),
            0,
        ), 'MacBook');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($passkey);
        $entityManager->flush();

        return $key;
    }

    /**
     * What the browser sends after the device signed (WebAuthn JSON, as @simplewebauthn/browser produces it).
     *
     * @return array<string, mixed>
     */
    private function assertion(\OpenSSLAsymmetricKey $key, string $challenge, User $user): array
    {
        $clientData = json_encode(['type' => 'webauthn.get', 'challenge' => $challenge, 'origin' => 'https://localhost', 'crossOrigin' => false], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        // RP ID hash, flags UP (0x01) + UV (0x04), signature counter 1
        $authenticatorData = hash('sha256', 'localhost', true)."\x05".pack('N', 1);
        openssl_sign($authenticatorData.hash('sha256', $clientData, true), $signature, $key, \OPENSSL_ALGO_SHA256);
        \assert(\is_string($signature));

        return [
            'id' => $this->base64Url(self::CREDENTIAL_ID),
            'rawId' => $this->base64Url(self::CREDENTIAL_ID),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => $this->base64Url($clientData),
                'authenticatorData' => $this->base64Url($authenticatorData),
                'signature' => $this->base64Url($signature),
                'userHandle' => $this->base64Url($user->getWebauthnHandle()),
            ],
            'clientExtensionResults' => [],
        ];
    }

    private function newKey(): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        \assert($key instanceof \OpenSSLAsymmetricKey);

        return $key;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
