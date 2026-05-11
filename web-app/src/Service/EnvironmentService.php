<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Multi-Environment Configuration Helper
 * Automatically detects and handles both localhost and live hosting
 */
class EnvironmentService
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Get the current environment (dev/prod)
     */
    public function getEnvironment(): string
    {
        return $_ENV['APP_ENV'] ?? 'dev';
    }

    /**
     * Check if running in development
     */
    public function isDevelopment(): bool
    {
        return $this->getEnvironment() === 'dev';
    }

    /**
     * Check if running in production
     */
    public function isProduction(): bool
    {
        return $this->getEnvironment() === 'prod';
    }

    /**
     * Get the base URL (works for both localhost and live)
     */
    public function getBaseUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return $_ENV['APP_URL'] ?? 'http://localhost:8000';
        }

        $protocol = $request->isSecure() ? 'https' : 'http';
        $host = $request->getHost();
        
        return "{$protocol}://{$host}";
    }

    /**
     * Get API URL (use base URL for APIs)
     */
    public function getApiUrl(): string
    {
        return $this->getBaseUrl() . '/api';
    }

    /**
     * Get Mercure public URL (different for localhost vs production)
     */
    public function getMercureUrl(): string
    {
        if ($this->isDevelopment()) {
            return $_ENV['MERCURE_PUBLIC_URL'] ?? 'http://localhost:3000/.well-known/mercure';
        }
        
        return $_ENV['MERCURE_PUBLIC_URL'] ?? 'https://mercure.tacipetroleum.com/.well-known/mercure';
    }

    /**
     * Check if using secure cookies
     */
    public function isSecureCookies(): bool
    {
        if ($this->isProduction()) {
            return true;
        }
        
        return filter_var($_ENV['SESSION_COOKIE_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get database configuration
     */
    public function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL'] ?? 'postgresql://postgres:password@127.0.0.1:5432/taci_petroleum_db';
    }

    /**
     * Get file upload directory
     */
    public function getUploadDir(): string
    {
        return $_ENV['UPLOAD_DIR'] ?? 'public/uploads';
    }

    /**
     * Get documents directory
     */
    public function getDocumentsDir(): string
    {
        return $_ENV['DOCUMENTS_DIR'] ?? 'public/documents';
    }

    /**
     * Get allowed hosts for current environment
     */
    public function getAllowedHosts(): array
    {
        $hosts = $_ENV['ALLOWED_HOSTS'] ?? 'localhost,127.0.0.1';
        return array_map('trim', explode(',', $hosts));
    }

    /**
     * Check if host is allowed
     */
    public function isHostAllowed(string $host): bool
    {
        $allowedHosts = $this->getAllowedHosts();
        
        foreach ($allowedHosts as $allowed) {
            // Support wildcards
            if (strpos($allowed, '*') !== false) {
                $pattern = str_replace('*', '.*', preg_quote($allowed, '/'));
                if (preg_match('/^' . $pattern . '$/', $host)) {
                    return true;
                }
            } elseif ($host === $allowed) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get current host
     */
    public function getCurrentHost(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request?->getHost() ?? 'localhost';
    }

    /**
     * Get Paystack environment (test/live)
     */
    public function getPaystackEnvironment(): string
    {
        $secretKey = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';
        
        if (strpos($secretKey, 'sk_test_') === 0) {
            return 'test';
        }
        
        return 'live';
    }

    /**
     * Check if feature is enabled
     */
    public function isFeatureEnabled(string $feature): bool
    {
        $envKey = 'ENABLE_' . strtoupper($feature);
        return filter_var($_ENV[$envKey] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get max file upload size
     */
    public function getMaxFileSize(): int
    {
        return (int)($_ENV['MAX_FILE_SIZE'] ?? 10485760); // Default 10MB
    }

    /**
     * Get session timeout
     */
    public function getSessionTimeout(): int
    {
        return (int)($_ENV['SESSION_TIMEOUT'] ?? 3600); // Default 1 hour
    }

    /**
     * Get timezone
     */
    public function getTimezone(): string
    {
        return $_ENV['TIMEZONE'] ?? 'UTC';
    }
}
