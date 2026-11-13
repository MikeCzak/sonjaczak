<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Security\PasswordRules;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Creates an admin account. Needed once for the very first account (nobody can log in to create it in the app)
 * and in emergencies; accounts for Sonja and later students are created in the admin area (docs/planung/rollen.md 4.1).
 *
 * The password is asked for hidden and twice, never passed as an argument, so it ends up neither in the shell
 * history nor in the process list.
 */
#[AsCommand(name: 'app:user:create', description: 'Creates an admin account (asks for the password)')]
final readonly class CreateUserCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'E-mail address, used to log in')] string $email,
        #[Option(description: 'Give the account ROLE_SUPER_ADMIN instead of ROLE_ADMIN')] bool $superAdmin = false,
    ): int {
        $user = new User($email);
        $user->setRoles([$superAdmin ? User::ROLE_SUPER_ADMIN : User::ROLE_ADMIN]);

        $violations = $this->validator->validate($user);
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error((string) $violation->getMessage());
            }

            return Command::FAILURE;
        }

        $password = $this->askPassword($io);
        if (null === $password) {
            return Command::FAILURE;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf('Account %s created with %s.', $user->getEmail(), $user->getRoles()[0]));

        return Command::SUCCESS;
    }

    private function askPassword(SymfonyStyle $io): ?string
    {
        $password = $io->askHidden('Password (at least '.PasswordRules::MIN_LENGTH.' characters)');
        $violations = $this->validator->validate($password, PasswordRules::constraints());
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error((string) $violation->getMessage());
            }

            return null;
        }

        if ($io->askHidden('Repeat password') !== $password) {
            $io->error('The passwords do not match.');

            return null;
        }

        return \is_string($password) ? $password : null;
    }
}
