<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload.php';

// Load .env files manually if Dotenv not available
if (!isset($_ENV['APP_ENV'])) {
    $envFile = dirname(__DIR__).'/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, ' "\'');
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Set default DATABASE_URL if not set (for demo)
if (!isset($_ENV['DATABASE_URL'])) {
    $_ENV['DATABASE_URL'] = 'sqlite:///:memory:';
    $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
    putenv('DATABASE_URL=sqlite:///:memory:');
}

$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? true));
$request = Request::createFromGlobals();

try {
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (\Exception $e) {
    // Fallback demo page if error
    http_response_code(200);
    include __DIR__ . '/demo.html';
}

