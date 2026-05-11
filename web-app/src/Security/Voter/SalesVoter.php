<?php
// src/Security/Voter/SalesVoter.php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SalesVoter extends Voter
{
    public const CREATE_SALE = 'create_sale';
    public const VIEW_SALES = 'view_sales';
    public const DELETE_SALE = 'delete_sale';
    public const PROCESS_PAYMENT = 'process_payment';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::CREATE_SALE,
            self::VIEW_SALES,
            self::DELETE_SALE,
            self::PROCESS_PAYMENT,
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
            self::CREATE_SALE => in_array('ROLE_STAFF', $userRoles) || in_array('ROLE_MANAGER', $userRoles) || in_array('ROLE_SUB_ADMIN', $userRoles) || in_array('ROLE_SUPER_ADMIN', $userRoles),
            self::VIEW_SALES => in_array('ROLE_STAFF', $userRoles) || in_array('ROLE_MANAGER', $userRoles) || in_array('ROLE_SUB_ADMIN', $userRoles) || in_array('ROLE_SUPER_ADMIN', $userRoles),
            self::DELETE_SALE => in_array('ROLE_SUPER_ADMIN', $userRoles) || in_array('ROLE_SUB_ADMIN', $userRoles),
            self::PROCESS_PAYMENT => in_array('ROLE_STAFF', $userRoles) || in_array('ROLE_MANAGER', $userRoles) || in_array('ROLE_SUB_ADMIN', $userRoles) || in_array('ROLE_SUPER_ADMIN', $userRoles),
            default => false,
        };
    }
}
