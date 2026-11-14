<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\Enum\LessonStatus;
use App\Entity\Settings;
use App\Repository\SettingsRepository;
use App\Tests\Factory\EnrollmentFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Settings (step C6).
 */
final class SettingsTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testThemeChangesThePublicSite(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/');
        self::assertSelectorExists('html[data-theme="classic"]');

        $client->request('GET', '/admin/einstellungen');
        $client->submitForm('Speichern', ['settings[theme]' => 'stage']);
        self::assertResponseRedirects('/admin/einstellungen');

        $client->request('GET', '/');
        self::assertSelectorExists('html[data-theme="stage"][data-bs-theme="dark"]');
        // Still no cookie on public pages
        self::assertResponseNotHasHeader('Set-Cookie');
    }

    public function testInvalidIbanIsRejected(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/admin/einstellungen');
        $client->submitForm('Speichern', ['settings[iban]' => 'AT00 1234']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#settings_iban.is-invalid');
    }

    public function testIbanIsStoredWithoutSpaces(): void
    {
        $client = $this->loggedInClient();

        $client->request('GET', '/admin/einstellungen');
        $client->submitForm('Speichern', ['settings[iban]' => 'at61 1904 3002 3457 3201']);
        self::assertResponseRedirects('/admin/einstellungen');

        $settings = self::getContainer()->get(SettingsRepository::class)->find(Settings::ID);
        self::assertInstanceOf(Settings::class, $settings);
        self::assertSame('AT611904300234573201', $settings->getIban());
    }

    public function testThresholdFromSettingsIsUsed(): void
    {
        $client = $this->loggedInClient();
        $enrollment = EnrollmentFactory::createOne();
        $enrollment->getLessons()->first() ?: self::fail('No lessons');
        $enrollment->getLessons()->first()->setStatus(LessonStatus::CancelledByTeacher);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId());
        self::assertSelectorTextContains('main', '1 Nachholstunde fällig');

        $client->request('GET', '/admin/einstellungen');
        $client->submitForm('Speichern', ['settings[makeUpThreshold]' => '1']);
        $client->request('GET', '/admin/teilnahmen/'.$enrollment->getId());
        self::assertSelectorTextNotContains('main', 'Nachholstunde fällig');
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->admin()->create(), 'admin');

        return $client;
    }
}
