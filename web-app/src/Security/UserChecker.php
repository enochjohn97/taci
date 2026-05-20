<?php
// src/Security/UserChecker.php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getStatus() === 'suspended') {
            throw new CustomUserMessageAccountStatusException('Your account has been suspended. Please contact the administrator.');
        }

        if ($user->getStatus() === 'inactive') {
            throw new CustomUserMessageAccountStatusException('Your account is currently inactive.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No checks needed post-auth
    }
}
