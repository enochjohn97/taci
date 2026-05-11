<?php
// src/Security/Voter/MemoVoter.php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class MemoVoter extends Voter
{
    public const CREATE_MEMO = 'create_memo';
    public const VIEW_MEMO = 'view_memo';
    public const DELETE_MEMO = 'delete_memo';
    public const FORWARD_MEMO = 'forward_memo';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::CREATE_MEMO,
            self::VIEW_MEMO,
            self::DELETE_MEMO,
            self::FORWARD_MEMO,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $userRoles = $user->getRoles();

        return match ($attribute) {
            self::CREATE_MEMO => in_array('ROLE_STAFF', $userRoles) || in_array('ROLE_MANAGER', $userRoles) || in_array('ROLE_SUB_ADMIN', $userRoles) || in_array('ROLE_SUPER_ADMIN', $userRoles),
            self::VIEW_MEMO => in_array('ROLE_STAFF', $userRoles) || in_array('ROLE_MANAGER', $userRoles) || in_array('ROLE_SUB_ADMIN', $userRoles) || in_array('ROLE_SUPER_ADMIN', $userRoles),
            self::DELETE_MEMO => in_array('ROLE_SUPER_ADMIN', $userRoles) || in_array('ROLE_SUB_ADMIN', $userRoles),
            self::FORWARD_MEMO => in_array('ROLE_SUPER_ADMIN', $userRoles) || in_array('ROLE_SUB_ADMIN', $userRoles),
            default => false,
        };
    }
}
