<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends Sonja one mail with all invoices that became overdue since the last run (datenmodell.md 3.7). Meant to run
 * once a day by cron on the server (docs/deployment.md); each deadline is reported once (Invoice::$remindedFor).
 *
 * The output names only the count, no numbers or names: cron output may end up in mails or logs.
 */
#[AsCommand(name: 'app:invoices:remind-overdue', description: 'Mails Sonja the invoices that became overdue')]
final readonly class RemindOverdueInvoicesCommand
{
    public function __construct(
        private InvoiceRepository $invoices,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
        #[Autowire(param: 'app.contact_email')]
        private string $recipient,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Only count, send nothing and change nothing')] bool $dryRun = false,
    ): int {
        $today = \DateTimeImmutable::createFromInterface($this->clock->now())->setTime(0, 0);
        $overdue = array_values(array_filter(
            $this->invoices->findOriginalsWithPayments(),
            static fn ($invoice): bool => $invoice->needsReminder($today),
        ));

        if ([] === $overdue || $dryRun) {
            $io->success(\sprintf('%d invoice(s) newly overdue%s.', \count($overdue), $dryRun ? ' (dry run, nothing sent)' : ''));

            return Command::SUCCESS;
        }

        // Send first, then remember: if sending fails, the next run tries again
        $this->mailer->send(new TemplatedEmail()
            ->to($this->recipient)
            ->subject($this->translator->trans('reminder.subject', ['%count%' => \count($overdue)], 'admin'))
            ->textTemplate('email/invoice/overdue.txt.twig')
            ->context(['invoices' => $overdue, 'today' => $today]));

        foreach ($overdue as $invoice) {
            $deadline = $invoice->getMissedDeadline($today);
            if (null !== $deadline) {
                $invoice->markReminded($deadline);
            }
        }
        $this->entityManager->flush();

        $io->success(\sprintf('Reminder sent for %d invoice(s).', \count($overdue)));

        return Command::SUCCESS;
    }
}
