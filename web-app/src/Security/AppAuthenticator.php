<?php
// src/Security/AppAuthenticator.php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\LoginAttempt;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use App\Entity\User;

class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $em,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->getString('username', '');
        $password = $request->request->getString('password', '');
        $csrfToken = $request->request->getString('_csrf_token', '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        $role = $request->attributes->get('role') ?? $request->query->get('role');
        if ($role) {
            $normalizedRole = str_replace('_', '-', strtolower($role));
            $request->getSession()->set('LAST_LOGIN_ROLE', $normalizedRole);
        }

        return new Passport(
            new UserBadge($username, function (string $userIdentifier) use ($request) {
                $user = $this->em->getRepository(User::class)->findOneBy(['username' => $userIdentifier]);
                if (!$user) {
                    throw new UserNotFoundException();
                }

                $requestedRole = $request->attributes->get('role') ?? $request->query->get('role');
                if ($requestedRole) {
                    $normalizedRole = str_replace('_', '-', strtolower($requestedRole));
                    $userRoleValue = $user->getRole()->value;
                    $expectedRole = match ($normalizedRole) {
                        'super-admin' => 'ROLE_SUPER_ADMIN',
                        'sub-admin' => 'ROLE_SUB_ADMIN',
                        'manager' => 'ROLE_MANAGER',
                        'staff' => 'ROLE_STAFF',
                        default => null,
                    };

                    if ($expectedRole && $userRoleValue !== $expectedRole) {
                        throw new CustomUserMessageAuthenticationException(sprintf(
                            'Your account does not have permission to log in as %s.',
                            str_replace('-', ' ', $requestedRole)
                        ));
                    }
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $user = $token->getUser();
        
        // Add success flash message
        $request->getSession()->getFlashBag()->add('success', 'Login successful! Welcome ' . $user->getUsername() . '.');
        
        // Log successful login attempt
        $attempt = new LoginAttempt();
        $attempt->setUsername($user->getUsername());
        $attempt->setIpAddress($request->getClientIp() ?? '0.0.0.0');
        $attempt->setSuccessful(true);
        $this->em->persist($attempt);

        // Update last login
        $user->setLastLogin(new \DateTime());
        $this->em->persist($user);
        $this->em->flush();

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            $path = parse_url($targetPath, PHP_URL_PATH);
            $roles = $user->getRoles();
            $isAuthorized = true;

            if (str_contains($path, '/dashboard/super-admin') || str_contains($path, '/admin') || str_contains($path, '/settings/admin')) {
                if (!in_array('ROLE_SUPER_ADMIN', $roles, true)) {
                    $isAuthorized = false;
                }
            } elseif (str_contains($path, '/dashboard/sub-admin')) {
                if (!in_array('ROLE_SUB_ADMIN', $roles, true)) {
                    $isAuthorized = false;
                }
            } elseif (str_contains($path, '/dashboard/manager')) {
                if (!in_array('ROLE_MANAGER', $roles, true)) {
                    $isAuthorized = false;
                }
            } elseif (str_contains($path, '/dashboard/staff')) {
                if (!in_array('ROLE_STAFF', $roles, true)) {
                    $isAuthorized = false;
                }
            }

            if ($isAuthorized) {
                return new RedirectResponse($targetPath);
            }
            
            $this->removeTargetPath($request->getSession(), $firewallName);
        }

        $roles = $user->getRoles();
        
        // Redirect based on role
        if (in_array('ROLE_SUPER_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard_super_admin'));
        }
        
        if (in_array('ROLE_SUB_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard_sub_admin'));
        }
        
        if (in_array('ROLE_MANAGER', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard_manager'));
        }
        
        if (in_array('ROLE_STAFF', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_store_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_role_select'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $username = $request->request->getString('username', '');
        $ipAddress = $request->getClientIp() ?? '0.0.0.0';
        $role = $request->attributes->get('role') ?? $request->query->get('role') ?? 'staff';

        // Add error flash message
        $request->getSession()->getFlashBag()->add('error', 'Login failed. Invalid username or password.');

        $attempt = new LoginAttempt();
        $attempt->setUsername($username);
        $attempt->setIpAddress($ipAddress);
        $attempt->setSuccessful(false);
        $this->em->persist($attempt);
        $this->em->flush();

        $fifteenMinutesAgo = (new \DateTimeImmutable())->modify('-15 minutes');
        $failedAttempts = (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(LoginAttempt::class, 'a')
            ->where('a.username = :username')
            ->andWhere('a.ipAddress = :ipAddress')
            ->andWhere('a.successful = false')
            ->andWhere('a.attemptedAt > :since')
            ->setParameters([
                'username' => $username,
                'ipAddress' => $ipAddress,
                'since' => $fifteenMinutesAgo,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        if ($failedAttempts >= 5) {
            $request->getSession()->getFlashBag()->add('error', 'Too many failed login attempts. Please try again in 15 minutes.');
        }

        $routeParams = [];
        $role = $request->attributes->get('role') ?? $request->query->get('role');
        if ($role) {
            $routeParams['role'] = str_replace('_', '-', strtolower($role));
        }

        if (empty($routeParams)) {
            return new RedirectResponse($this->urlGenerator->generate('app_role_select'));
        }

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE, $routeParams));
    }

    protected function getLoginUrl(Request $request): string
    {
        $role = $request->attributes->get('role') ?? $request->query->get('role');
        if ($role) {
            return $this->urlGenerator->generate(self::LOGIN_ROUTE, ['role' => str_replace('_', '-', strtolower($role))]);
        }

        return $this->urlGenerator->generate('app_role_select');
    }
}
