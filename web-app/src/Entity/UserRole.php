<?php
// src/Entity/UserRole.php

namespace App\Entity;

enum UserRole: string
{
    case ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    case ROLE_SUB_ADMIN = 'ROLE_SUB_ADMIN';
    case ROLE_MANAGER = 'ROLE_MANAGER';
    case ROLE_STAFF = 'ROLE_STAFF';
}
