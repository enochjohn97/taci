<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Provides rich flash feedback messages for login and logout events.
 */
class SecurityEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private RequestStack $requestStack) {}

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
            LogoutEvent::class                => 'onLogout',
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if (!$user instanceof User) {
            return;
        }

        $session = $this->requestStack->getSession();

        $roleLabel = match ($user->getRole()?->value) {
            'ROLE_SUPER_ADMIN' => 'Super Admin',
            'ROLE_SUB_ADMIN'   => 'Sub-Admin',
            'ROLE_MANAGER'     => 'Manager',
            default            => 'Staff',
        };

        $session->getFlashBag()->add(
            'success',
            "Welcome back, {$user->getUsername()}! You are logged in as {$roleLabel}."
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        $username = $user instanceof User ? $user->getUsername() : 'User';

        $session = $this->requestStack->getSession();
        $session->getFlashBag()->add(
            'info',
            "Goodbye, {$username}! You have been logged out successfully."
        );
    }
}
