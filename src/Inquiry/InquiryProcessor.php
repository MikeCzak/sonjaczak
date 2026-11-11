<?php

declare(strict_types=1);

namespace App\Inquiry;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Mails a validated inquiry to Sonja, after the honeypot and rate limit checks.
 *
 * Sent synchronously (no Messenger worker on the small server). Nothing from the form ends up in the log (GDPR).
 */
final readonly class InquiryProcessor
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        #[Target('inquiry')]
        private RateLimiterFactoryInterface $limiter,
        private LoggerInterface $logger,
        #[Autowire(param: 'app.contact_email')]
        private string $recipient,
        #[Autowire(param: 'kernel.secret')]
        private string $secret,
    ) {
    }

    /**
     * @param string $type selects the subject (mail.subject.<type>) and the template (email/inquiry/<type>.txt.twig)
     */
    public function process(Inquiry $inquiry, string $type, Request $request): InquiryStatus
    {
        // Probably a bot: pretend success, so it learns nothing
        if (null !== $inquiry->website) {
            $this->logger->info('Inquiry discarded by honeypot', ['type' => $type]);

            return InquiryStatus::Sent;
        }

        // The cache only ever sees a keyed hash of the client IP, not the address itself
        $key = hash_hmac('sha256', (string) $request->getClientIp(), $this->secret);
        if (!$this->limiter->create($key)->consume()->isAccepted()) {
            $this->logger->warning('Inquiry rate limit exceeded', ['type' => $type]);

            return InquiryStatus::RateLimited;
        }

        // From comes from config/packages/mailer.yaml; IONOS only accepts our own domain as sender
        $email = (new TemplatedEmail())
            ->to($this->recipient)
            ->replyTo(new Address((string) $inquiry->email, (string) $inquiry->name))
            ->subject($this->translator->trans('mail.subject.'.$type, ['%name%' => $inquiry->name]))
            ->textTemplate('email/inquiry/'.$type.'.txt.twig')
            ->context(['inquiry' => $inquiry]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Only the class: the exception message may quote addresses from the SMTP dialogue
            $this->logger->error('Inquiry could not be sent', ['type' => $type, 'exception' => $e::class]);

            return InquiryStatus::Failed;
        }

        return InquiryStatus::Sent;
    }
}
