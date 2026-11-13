<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Student;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Whether a student may be deleted (docs/planung/datenmodell.md 5).
 *
 * Invoices are receipts and must be kept, so a student with invoices must not be deleted. Invoices come with
 * step D1; until then, every ROLE_ADMIN may delete. The check belongs here already, so templates and controllers
 * ask the voter and need no change later.
 *
 * @extends Voter<string, Student>
 */
final class StudentVoter extends Voter
{
    public const string DELETE = 'STUDENT_DELETE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DELETE === $attribute && $subject instanceof Student;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // TODO D1: deny if the student has invoices ($vote?->addReason('student.delete.has_invoices'))
        return $this->security->isGranted(User::ROLE_ADMIN);
    }
}
