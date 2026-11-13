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
 * Not while enrolled: enrollments hold lessons and (from step D1) invoices, which are receipts and must be kept.
 * The foreign key enrollment.student_id would refuse it anyway; the voter hides the button instead of a database
 * error. Trial lessons are deleted with the student (a person who did not stay, datenmodell.md 5).
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
        if (!$this->security->isGranted(User::ROLE_ADMIN)) {
            return false;
        }

        if (!$subject->getEnrollments()->isEmpty()) {
            $vote?->addReason('student.delete.has_enrollments');

            return false;
        }

        return true;
    }
}
