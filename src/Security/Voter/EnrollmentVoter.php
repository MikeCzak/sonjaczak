<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Enrollment;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Whether an enrollment may be deleted (with its lessons). Invoices are created from an enrollment and must be kept,
 * so an enrollment with invoices cannot be deleted.
 *
 * @extends Voter<string, Enrollment>
 */
final class EnrollmentVoter extends Voter
{
    public const string DELETE = 'ENROLLMENT_DELETE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DELETE === $attribute && $subject instanceof Enrollment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$this->security->isGranted(User::ROLE_ADMIN)) {
            return false;
        }

        if (!$subject->getInvoices()->isEmpty()) {
            $vote?->addReason('enrollment.delete.has_invoices');

            return false;
        }

        return true;
    }
}
