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
 * Whether a course may be deleted: only while nobody is enrolled (rule added with enrollments in step C3).
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
        // TODO C3: deny while the course has enrollments
        return $this->security->isGranted(User::ROLE_ADMIN);
    }
}
