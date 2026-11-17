<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Passkey;
use App\Entity\User;
use App\Repository\PasskeyRepository;
use App\Security\PasskeyCreatedHandler;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Passkeys in "Mein Konto" (umsetzung-admin.md B5b). The device dialog and the signature cannot run here (no
 * authenticator); tested are the options the server hands out, listing, deleting and naming.
 */
final class PasskeyTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testAccountListsOnlyOwnPasskeys(): void
    {
        [$client, $user] = $this->loggedIn();
        $client->request('GET', '/admin/konto');
        self::assertSelectorTextContains('#passkeys', 'Noch kein Passkey eingerichtet.');
        self::assertSelectorExists('#passkeys form[data-passkey-register-options-url-value="/admin/konto/passkeys/optionen"]');

        $this->passkey($user, 'own', 'MacBook');
        $this->passkey(UserFactory::new()->admin()->create(), 'other', 'Fremdes Handy');

        $client->request('GET', '/admin/konto');
        self::assertSelectorTextContains('#passkeys', 'MacBook');
        self::assertSelectorTextContains('#passkeys', 'noch nicht genutzt');
        self::assertSelectorTextNotContains('#passkeys', 'Fremdes Handy');
    }

    public function testDeleteOnlyOwnPasskey(): void
    {
        [$client, $user] = $this->loggedIn();
        $own = $this->passkey($user, 'own', 'MacBook');
        $other = $this->passkey(UserFactory::new()->admin()->create(), 'other', 'Fremdes Handy');

        $client->request('POST', '/admin/konto/passkeys/'.$other->getId().'/loeschen');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/admin/konto');
        $client->submit($client->getCrawler()->filter('#passkeys details form')->form());
        self::assertResponseRedirects('/admin/konto');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Passkey gelöscht.');

        $repository = self::getContainer()->get(PasskeyRepository::class);
        self::assertNull($repository->find((int) $own->getId()));
        self::assertNotNull($repository->find((int) $other->getId()));
    }

    /**
     * What the browser gets before the device dialog: our account handle, fingerprint required, stored on the
     * device, and the existing passkeys so the same device is not registered twice.
     */
    public function testCreationOptions(): void
    {
        [$client, $user] = $this->loggedIn();
        $this->passkey($user, 'own', 'MacBook');

        $client->jsonRequest('POST', '/admin/konto/passkeys/optionen', []);
        self::assertResponseIsSuccessful();
        /** @var array{rp: array{id: string, name: string}, user: array{id: string, name: string}, authenticatorSelection: array{userVerification: string, residentKey: string}, excludeCredentials: list<array{id: string}>, challenge: string} $options */
        $options = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        // Browsers refuse options without rp (found in Chrome, B5d)
        self::assertSame(['id' => 'localhost', 'name' => 'localhost'], $options['rp']);
        self::assertSame($this->base64Url($user->getWebauthnHandle()), $options['user']['id']);
        self::assertSame($user->getEmail(), $options['user']['name']);
        self::assertSame('required', $options['authenticatorSelection']['userVerification']);
        self::assertSame('required', $options['authenticatorSelection']['residentKey']);
        self::assertSame([$this->base64Url('own')], array_column($options['excludeCredentials'], 'id'));
        self::assertGreaterThanOrEqual(32, \strlen((string) base64_decode(strtr($options['challenge'], '-_', '+/'))));
    }

    public function testEndpointsRequireLogin(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/admin/konto/passkeys/optionen', []);
        self::assertResponseRedirects('/admin/login');
    }

    public function testInvalidResultSavesNothing(): void
    {
        [$client] = $this->loggedIn();
        $client->jsonRequest('POST', '/admin/konto/passkeys/optionen', []);
        $client->jsonRequest('POST', '/admin/konto/passkeys', ['id' => 'AQID', 'rawId' => 'AQID', 'type' => 'public-key', 'response' => [], 'passkeyName' => 'MacBook']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, self::getContainer()->get(PasskeyRepository::class)->count());
    }

    /**
     * After the bundle saved the new passkey: it gets the typed name, but only if it belongs to the logged-in account.
     */
    public function testHandlerNamesTheNewPasskey(): void
    {
        self::bootKernel();
        $user = UserFactory::new()->admin()->create();
        $own = $this->passkey($user, "\x01own", 'Passkey vom 17.11.2026');
        $other = $this->passkey(UserFactory::new()->admin()->create(), "\x01other", 'Passkey vom 17.11.2026');
        self::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'admin', $user->getRoles()));
        $handler = self::getContainer()->get(PasskeyCreatedHandler::class);

        foreach (["\x01own", "\x01other"] as $id) {
            $response = $handler->onSuccess(new Request(content: json_encode(['id' => $this->base64Url($id), 'passkeyName' => '  MacBook  '], \JSON_THROW_ON_ERROR)));
            self::assertSame(201, $response->getStatusCode());
        }

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repository = self::getContainer()->get(PasskeyRepository::class);
        self::assertSame('MacBook', $repository->find((int) $own->getId())?->getName());
        self::assertSame('Passkey vom 17.11.2026', $repository->find((int) $other->getId())?->getName());
    }

    /**
     * @return array{KernelBrowser, User}
     */
    private function loggedIn(): array
    {
        $client = self::createClient();
        $user = UserFactory::new()->admin()->create();
        $client->loginUser($user, 'admin');

        return [$client, $user];
    }

    private function passkey(User $user, string $credentialId, string $name): Passkey
    {
        $passkey = Passkey::fromRecord(new CredentialRecord(
            $credentialId,
            'public-key',
            ['internal'],
            'none',
            EmptyTrustPath::create(),
            Uuid::v4(),
            'public key',
            $user->getWebauthnHandle(),
            0,
        ), $name);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($passkey);
        $entityManager->flush();

        return $passkey;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
