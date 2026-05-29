<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Defense-in-depth path rules (longest prefix wins). Complements security.yaml access_control.
 */
class RoleRouteSubscriber implements EventSubscriberInterface
{
    /** @var array<string, list<string>> Longer paths must appear before shorter prefixes. */
    private const PATH_RULES = [
        '/engagement/api/add-points' => ['ROLE_SUPER_ADMIN'],
        '/engagement/api/redeem-points' => ['ROLE_SUPER_ADMIN', 'ROLE_MANAGER'],
        '/engagement/promotions' => ['ROLE_SUPER_ADMIN'],
        '/engagement/reports' => ['ROLE_SUPER_ADMIN'],
        '/engagement/wallet' => ['ROLE_SUPER_ADMIN', 'ROLE_SUB_ADMIN', 'ROLE_MANAGER', 'ROLE_STAFF'],
        '/engagement/customer' => ['ROLE_SUPER_ADMIN', 'ROLE_MANAGER'],
        '/engagement/tiers' => ['ROLE_SUPER_ADMIN', 'ROLE_MANAGER'],
        '/engagement' => ['ROLE_SUPER_ADMIN', 'ROLE_MANAGER'],
        '/loyalty/api/points-balance' => ['ROLE_SUPER_ADMIN', 'ROLE_SUB_ADMIN', 'ROLE_MANAGER', 'ROLE_STAFF'],
        '/loyalty/api/add-points' => ['ROLE_SUPER_ADMIN'],
        '/loyalty/api/redeem-points' => ['ROLE_SUPER_ADMIN', 'ROLE_MANAGER'],
        '/loyalty/api/create-promotion' => ['ROLE_SUPER_ADMIN'],
        '/fuel' => ['ROLE_SUPER_ADMIN', 'ROLE_SUB_ADMIN'],
        '/tracking' => ['ROLE_SUPER_ADMIN', 'ROLE_SUB_ADMIN'],
        '/reports' => ['ROLE_SUPER_ADMIN', 'ROLE_SUB_ADMIN'],
        '/settings/admin' => ['ROLE_SUPER_ADMIN'],
    ];

    public function __construct(private AuthorizationCheckerInterface $auth) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        foreach (self::PATH_RULES as $prefix => $roles) {
            if (!str_starts_with($path, $prefix)) {
                continue;
            }
            // Exact match for hub root; longer prefixes (wallet, api, etc.) matched above.
            if ($prefix === '/engagement' && $path !== '/engagement') {
                continue;
            }
            $this->assertAnyRole($roles, $path);
            return;
        }
    }

    private function assertAnyRole(array $roles, string $path): void
    {
        foreach ($roles as $role) {
            if ($this->auth->isGranted($role)) {
                return;
            }
        }
        throw new AccessDeniedException(sprintf('Access denied for path %s', $path));
    }
}
