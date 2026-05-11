<?php
// src/EventListener/SessionTimeoutListener.php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;

/**
 * SessionTimeoutListener - Handles session inactivity timeout
 * 
 * Logs out users after 1 hour of inactivity
 */
class SessionTimeoutListener implements EventSubscriberInterface
{
    private const SESSION_TIMEOUT = 3600; // 1 hour in seconds
    private const LAST_ACTIVITY_KEY = 'last_activity';

    public function __construct(private RouterInterface $router) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        // Skip timeout check for login and public routes
        $path = $request->getPathInfo();
        if (in_array($path, ['/login', '/role-select', '/password-recovery', '/logout'], true)) {
            return;
        }

        // If session hasn't started, start it
        if (!$session->isStarted()) {
            $session->start();
        }

        $lastActivity = $session->get(self::LAST_ACTIVITY_KEY);

        if ($lastActivity === null) {
            // First request, set last activity time
            $session->set(self::LAST_ACTIVITY_KEY, time());
            return;
        }

        $now = time();
        $elapsed = $now - $lastActivity;

        if ($elapsed > self::SESSION_TIMEOUT) {
            // Session has expired, destroy it and redirect to login
            $session->invalidate();
            $loginUrl = $this->router->generate('app_role_select');
            $event->setResponse(new RedirectResponse($loginUrl, 302));
            return;
        }

        // Update last activity time
        $session->set(self::LAST_ACTIVITY_KEY, $now);
    }
}
