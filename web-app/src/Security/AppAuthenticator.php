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
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserLoadingBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\LoginAttempt;

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
        $username = $request->getPayload()->getString('username');
        $password = $request->getPayload()->getString('password');
        $csrf_token = $request->getPayload()->getString('_csrf_token');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        return new Passport(
            new UserLoadingBadge('username', $username),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrf_token),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $user = $token->getUser();
        
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
            return new RedirectResponse($targetPath);
        }

        // Redirect based on role
        $roles = $user->getRoles();
        if (in_array('ROLE_SUPER_ADMIN', $roles)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        } elseif (in_array('ROLE_SUB_ADMIN', $roles)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        } else {
            return new RedirectResponse($this->urlGenerator->generate('app_store_dashboard'));
        }
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $username = $request->getPayload()->getString('username');
        
        // Log failed login attempt
        $attempt = new LoginAttempt();
        $attempt->setUsername($username);
        $attempt->setIpAddress($request->getClientIp() ?? '0.0.0.0');
        $attempt->setSuccessful(false);
        $this->em->persist($attempt);
        $this->em->flush();

        // Check for rate limiting (5 failed attempts in 15 minutes)
        $fifteenMinutesAgo = (new \DateTime())->modify('-15 minutes');
        $failedAttempts = $this->em->getRepository(LoginAttempt::class)->count([
            'username' => $username,
            'ipAddress' => $request->getClientIp() ?? '0.0.0.0',
            'successful' => false,
        ]);

        if ($failedAttempts >= 5) {
            throw new AuthenticationException('Too many failed login attempts. Please try again in 15 minutes.');
        }

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
