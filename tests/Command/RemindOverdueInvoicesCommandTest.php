<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Enum\PaymentMethod;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Tests\Factory\InvoiceFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Reminder of overdue invoices (step D4). The date is fixed with a mock clock, so the tests do not depend on today.
 */
final class RemindOverdueInvoicesCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    /**
     * Mails sent before the last run; the mailer logger counts all mails since the kernel was booted.
     */
    private int $mailsBefore = 0;

    public function testOneMailPerDeadline(): void
    {
        self::bootKernel();
        // Instalments: 1st by 13.11., everything by 31.12.
        $invoice = InvoiceFactory::createOne([
            'number' => 16,
            'amountCents' => 49500,
            'dueOn' => new \DateTimeImmutable('2026-11-13'),
            'secondInstallmentDueOn' => new \DateTimeImmutable('2026-12-31'),
        ]);

        // On the due date itself nothing is overdue yet
        $this->runOn('2026-11-13');
        self::assertSame(0, $this->lastRun()[0]);

        // The day after: reminder for the first instalment
        $this->runOn('2026-11-14');
        [$count, $email] = $this->lastRun();
        self::assertSame(1, $count);
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailHeaderSame($email, 'To', 'music@sonjaczak.com');
        self::assertEmailHeaderSame($email, 'Subject', 'Eine Honorarnote ist überfällig');
        self::assertEmailTextBodyContains($email, 'Honorarnote 16/2026');
        self::assertEmailTextBodyContains($email, '495,00 € offen, fällig am 13.11.2026');
        self::assertEmailTextBodyContains($email, '/admin/honorarnoten/'.$invoice->getId());

        // Not again for the same deadline
        $this->runOn('2026-11-15');
        self::assertSame(0, $this->lastRun()[0]);

        // First instalment paid: nothing due until the second date, then a new reminder
        $this->pay($invoice, 24750);
        $this->runOn('2026-12-31');
        self::assertSame(0, $this->lastRun()[0]);
        $this->runOn('2027-01-01');
        [$count, $email] = $this->lastRun();
        self::assertSame(1, $count);
        self::assertEmailTextBodyContains($email ?? self::fail('No mail'), '247,50 € offen, fällig am 31.12.2026');
    }

    public function testPaidAndCreditedInvoicesAreNotReminded(): void
    {
        self::bootKernel();
        $paid = InvoiceFactory::createOne(['amountCents' => 10000, 'dueOn' => new \DateTimeImmutable('2026-11-13')]);
        $this->pay($paid, 10000);
        $original = InvoiceFactory::createOne(['amountCents' => 10000, 'dueOn' => new \DateTimeImmutable('2026-11-13')]);
        // Fully credited before payment: nothing owed
        InvoiceFactory::createOne(['amountCents' => -10000, 'credits' => $original, 'enrollment' => $original->getEnrollment(), 'dueOn' => new \DateTimeImmutable('2026-11-13')]);

        $this->runOn('2026-12-01');
        self::assertSame(0, $this->lastRun()[0]);
    }

    public function testDryRunSendsAndChangesNothing(): void
    {
        self::bootKernel();
        InvoiceFactory::createOne(['dueOn' => new \DateTimeImmutable('2026-11-13')]);

        $output = $this->runOn('2026-12-01', ['--dry-run' => true]);
        self::assertSame(0, $this->lastRun()[0]);
        self::assertStringContainsString('1 invoice(s) newly overdue (dry run', $output);

        $this->runOn('2026-12-01');
        self::assertSame(1, $this->lastRun()[0]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runOn(string $day, array $options = []): string
    {
        self::mockTime(new \DateTimeImmutable($day.' 07:00'));
        $this->mailsBefore = \count(self::getMailerMessages());

        $tester = new CommandTester(new Application(self::$kernel ?? self::fail('No kernel'))->find('app:invoices:remind-overdue'));
        $tester->execute($options);
        $tester->assertCommandIsSuccessful();
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return $tester->getDisplay();
    }

    /**
     * Number of mails sent by the last run, and the last of them.
     *
     * @phpstan-impure the result changes with every run, PHPStan must not reuse an earlier one
     *
     * @return array{int, ?Email}
     */
    private function lastRun(): array
    {
        $messages = self::getMailerMessages();
        $last = end($messages);

        return [\count($messages) - $this->mailsBefore, $last instanceof Email ? $last : null];
    }

    private function pay(Invoice $invoice, int $cents): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $managed = $entityManager->find(Invoice::class, $invoice->getId()) ?? self::fail('Invoice not found');
        $entityManager->persist(new Payment($managed, $cents, new \DateTimeImmutable('2026-11-20'), PaymentMethod::Transfer));
        $entityManager->flush();
        $entityManager->clear();
    }
}
