<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelper;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

/**
 * Mails a one-time link to set a password: after "Passwort vergessen" or as invitation for a new account.
 * Sender comes from config/packages/mailer.yaml.
 */
final readonly class PasswordLinkMailer
{
    /** Invitations are valid longer than reset links: the new account holder may not read the mail at once */
    public const int INVITATION_LIFETIME = 3 * 24 * 3600;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws TooManyPasswordRequestsException when the last link for this account is younger than throttle_limit
     */
    public function sendResetLink(User $user): ResetPasswordToken
    {
        return $this->send($user, 'reset', null);
    }

    /**
     * @throws TooManyPasswordRequestsException when the last link for this account is younger than throttle_limit
     */
    public function sendInvitation(User $user): ResetPasswordToken
    {
        return $this->send($user, 'invitation', self::INVITATION_LIFETIME);
    }

    private function send(User $user, string $kind, ?int $lifetime): ResetPasswordToken
    {
        // The interface declares the lifetime argument only as a comment (bundle 1.x); the class has it
        $token = $this->resetPasswordHelper instanceof ResetPasswordHelper
            ? $this->resetPasswordHelper->generateResetToken($user, $lifetime)
            : $this->resetPasswordHelper->generateResetToken($user);

        $this->mailer->send((new TemplatedEmail())
            ->to($user->getEmail())
            ->subject($this->translator->trans('password_mail.'.$kind.'.subject', [], 'admin'))
            ->textTemplate('email/account/password_link.txt.twig')
            ->context(['kind' => $kind, 'resetToken' => $token]));

        return $token;
    }
}
