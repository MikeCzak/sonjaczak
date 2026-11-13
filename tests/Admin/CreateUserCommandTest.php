<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CreateUserCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testCreatesSuperAdminWithHashedPassword(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['Lerche-Amsel-Kanon-42', 'Lerche-Amsel-Kanon-42']);
        $tester->execute(['email' => ' Mike@Example.com ', '--super-admin' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'mike@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame([User::ROLE_SUPER_ADMIN, 'ROLE_USER'], $user->getRoles());
        self::assertStringStartsWith('$2y$', $user->getPassword(), 'bcrypt hash expected');
    }

    public function testRejectsWeakPassword(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['passwort1234']);
        $tester->execute(['email' => 'sonja@example.com']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('zu leicht zu erraten', $tester->getDisplay());
    }

    public function testRejectsExistingAddress(): void
    {
        UserFactory::createOne(['email' => 'sonja@example.com']);

        $tester = $this->tester();
        $tester->execute(['email' => 'Sonja@example.com']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('bereits ein Konto', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::bootKernel()))->find('app:user:create'));
    }
}
