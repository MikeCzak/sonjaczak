<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Course;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Whether a course may be deleted: only while nobody is enrolled. The foreign key enrollment.course_id would refuse
 * it anyway; the voter hides the button and gives a readable answer instead of a database error.
 *
 * @extends Voter<string, Course>
 */
final class CourseVoter extends Voter
{
    public const string DELETE = 'COURSE_DELETE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DELETE === $attribute && $subject instanceof Course;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$this->security->isGranted(User::ROLE_ADMIN)) {
            return false;
        }

        if (!$subject->getEnrollments()->isEmpty()) {
            $vote?->addReason('course.delete.has_enrollments');

            return false;
        }

        return true;
    }
}
