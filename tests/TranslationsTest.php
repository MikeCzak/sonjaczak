<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Every translation key used in a template exists in the German translation files.
 */
final class TranslationsTest extends KernelTestCase
{
    public function testNoTranslationIsMissing(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('debug:translation'));

        // Exit code 65 (TranslationDebugCommand::EXIT_CODE_MISSING) when a key is missing
        $tester->execute(['locale' => 'de', '--only-missing' => true]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
    }
}
