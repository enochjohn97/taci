<?php
// src/Security/Voter/ApprovalVoter.php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ApprovalVoter extends Voter
{
    public const APPROVE = 'approve';
    public const VIEW_REQUESTS = 'view_requests';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::APPROVE, self::VIEW_REQUESTS]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Only Super Admin and Sub Admin can approve
        $allowedRoles = ['ROLE_SUPER_ADMIN', 'ROLE_SUB_ADMIN'];
        $userRoles = $user->getRoles();

        if (!array_intersect($userRoles, $allowedRoles)) {
            return false;
        }

        return match ($attribute) {
            self::APPROVE => $this->canApprove($user),
            self::VIEW_REQUESTS => $this->canViewRequests($user),
            default => false,
        };
    }

    private function canApprove(User $user): bool
    {
        $userRoles = $user->getRoles();
        return in_array('ROLE_SUPER_ADMIN', $userRoles) || in_array('ROLE_SUB_ADMIN', $userRoles);
    }

    private function canViewRequests(User $user): bool
    {
        $userRoles = $user->getRoles();
        return in_array('ROLE_SUPER_ADMIN', $userRoles) || in_array('ROLE_SUB_ADMIN', $userRoles);
    }
}
