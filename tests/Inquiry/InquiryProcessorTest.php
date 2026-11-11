<?php

declare(strict_types=1);

namespace App\Tests\Inquiry;

use App\Inquiry\ContactInquiry;
use App\Inquiry\InquiryProcessor;
use App\Inquiry\InquiryStatus;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * Failure path of the mail transport, which the functional tests cannot trigger (they use null://null).
 */
final class InquiryProcessorTest extends TestCase
{
    public function testTransportFailureIsReportedWithoutPersonalDataInTheLog(): void
    {
        $mailer = new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new TransportException('550 Mailbox rejected: <erika@example.com>');
            }
        };
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = $message.' '.json_encode($context, \JSON_THROW_ON_ERROR);
            }
        };
        $limiter = new RateLimiterFactory(['id' => 'test', 'policy' => 'no_limit'], new InMemoryStorage());
        $processor = new InquiryProcessor($mailer, new IdentityTranslator(), $limiter, $logger, 'music@sonjaczak.com', 'secret');

        $inquiry = new ContactInquiry();
        $inquiry->name = 'Erika Muster';
        $inquiry->email = 'erika@example.com';
        $inquiry->message = 'Hallo';

        self::assertSame(InquiryStatus::Failed, $processor->process($inquiry, 'contact', new Request()));
        self::assertCount(1, $logger->records);
        self::assertStringNotContainsString('erika', $logger->records[0]);
        self::assertStringNotContainsString('Erika', $logger->records[0]);
    }
}
