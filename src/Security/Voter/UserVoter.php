<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may change the access of an account (role, deactivate, invitation).
 *
 * Only ROLE_SUPER_ADMIN, and never for the own account: otherwise Mike could take away his own super admin role
 * or lock himself out, and nobody would be left to manage accounts.
 *
 * @extends Voter<string, User>
 */
final class UserVoter extends Voter
{
    public const string CHANGE_ACCESS = 'USER_CHANGE_ACCESS';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Only our attribute with a User: everything else is not our business (abstain)
        return self::CHANGE_ACCESS === $attribute && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$this->security->isGranted(User::ROLE_SUPER_ADMIN)) {
            return false;
        }

        if ($subject === $token->getUser()) {
            $vote?->addReason('user.flash.own_account');

            return false;
        }

        return true;
    }
}
