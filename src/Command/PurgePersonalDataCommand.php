<?php

declare(strict_types=1);

namespace App\Command;

use App\Privacy\PersonalDataPurger;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes personal data and invoices whose retention period has ended (App\Privacy\PersonalDataPurger,
 * umsetzung-admin.md F3). Meant to run once a day by cron on the server (docs/deployment.md); by hand only with
 * --dry-run, to see what the next run would delete.
 *
 * The output names only counts, no names: cron output may end up in mails or logs.
 */
#[AsCommand(name: 'app:privacy:purge', description: 'Deletes personal data and invoices past their retention period')]
final readonly class PurgePersonalDataCommand
{
    public function __construct(
        private PersonalDataPurger $purger,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Only count, delete nothing')] bool $dryRun = false,
    ): int {
        $result = $this->purger->purge(\DateTimeImmutable::createFromInterface($this->clock->now()), $dryRun);

        $io->success(\sprintf(
            '%s %d student(s), %d guardian(s), %d invoice(s) and credit note(s) past 7 years; %d enrollment(s) kept without person for their invoices.',
            $dryRun ? 'Dry run, would delete:' : 'Deleted:',
            $result->students,
            $result->guardians,
            $result->invoices,
            $result->detachedEnrollments,
        ));

        return Command::SUCCESS;
    }
}
