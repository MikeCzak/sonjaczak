<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Invoice;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Whether a credit note may be created for an invoice: not for a credit note itself, and only while something is
 * left to credit (the credits must not exceed the original amount).
 *
 * @extends Voter<string, Invoice>
 */
final class InvoiceVoter extends Voter
{
    public const string CREDIT = 'INVOICE_CREDIT';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::CREDIT === $attribute && $subject instanceof Invoice;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$this->security->isGranted(User::ROLE_ADMIN)) {
            return false;
        }

        if ($subject->isCreditNote()) {
            $vote?->addReason('credit.denied.is_credit_note');

            return false;
        }

        if ($subject->getCreditableCents() <= 0) {
            $vote?->addReason('credit.denied.fully_credited');

            return false;
        }

        return true;
    }
}
